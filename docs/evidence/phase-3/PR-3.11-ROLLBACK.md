# Evidencia — PR 3.11 Rollback seguro best-effort

Estado: `IN_PROGRESS / QA_PENDING`.

Issue: #66  
Rama: `feat/phase3-safe-rollback`  
Base: `main` post cierre 3.9 / PR #65

## Objetivo

Implementar D38: rollback best-effort con protección ante cambios posteriores, sin prometer reversión absoluta ni sobrescribir trabajo manual posterior.

## Invariantes

1. Preview es estrictamente read-only.
2. Solo batches `completed` con journal completo pueden iniciar rollback.
3. Cada mutación exitosa se journaliza por fila.
4. El intent del journal existe **antes** de ejecutar la mutación.
5. El estado `after` se finaliza **antes** del checkpoint del batch.
6. Updates comparan únicamente el scope realmente tocado por esa fila.
7. Propiedades creadas usan un fingerprint más conservador que incluye estado del post, meta y taxonomías; cambios posteriores bloquean la eliminación.
8. Si no se puede demostrar seguridad, se bloquea; nunca se fuerza.
9. Attachments no se eliminan durante 3.11 salvo prueba futura de exclusividad y ausencia de referencias; la implementación inicial los conserva.
10. Rollback se procesa por slices mediante `rollback_processing`, no en un request monolítico.
11. `rollback_wla_imports` es capability destructiva dedicada y queda solo para Administrador de WordPress por defecto.
12. Producción no participa en tests ni migraciones de esta fase.

## Arquitectura en implementación

```text
BatchRunner
  ↓ antes de RowExecutor
RollbackJournalRecorder::prepare()
  ├── original_action created|updated
  ├── targets tocados
  └── before snapshot para update
  ↓
RowExecutor
  ↓ éxito
RollbackJournalRecorder::finalize()
  ├── property_id
  ├── after snapshot + SHA-256
  └── created_object_hash para creates
  ↓
BatchCheckpoint
```

Rollback:

```text
completed batch
  ↓
RollbackPreviewService (read-only)
  ├── journal completo
  ├── current == after
  ├── safe / noop / blocked / error
  └── preview_hash fresco
  ↓ confirmación explícita
rollback_processing
  ↓ slices en orden inverso
RollbackInspector revalida cada fila
  ↓
RollbackRestorer
  ├── create: elimina solo si fingerprint completo coincide
  └── update: restaura solo targets tocados
       └── compensación local: si before falla, restaura after
  ↓
rolled_back | rollback_blocked
```

## Persistencia

Nueva tabla `wla_import_rollback_journal` con UNIQUE `(batch_uuid,row_number)` y campos para acción original, scope, before/after snapshots, hashes, estado del journal, estado/razón de rollback y timestamps.

Los snapshots no contienen la fila de origen completa. Solo guardan el mínimo estado canónico necesario para restaurar los targets tocados. Para decidir si una propiedad creada puede borrarse, el estado adicional se conserva únicamente como SHA-256.

## Seguridad de datos

- meta inexistente y meta existente vacía son estados distintos;
- identidad/source key se restaura junto a `external_id` cuando corresponde;
- taxonomías se guardan por IDs canónicos;
- galería se restaura mediante `gallery_ids` internos;
- featured image se restaura mediante attachment ID;
- attachments remotos descargados no se borran;
- Search, Quality e Identity deben quedar sincronizados después de cada reversa.

## Implementado hasta ahora

- Issue #66 y rama temática;
- schema/version del journal;
- codec determinista JSON + SHA-256;
- snapshotter por scope;
- fingerprint conservador para propiedades creadas;
- repository del journal con queries bounded;
- recorder prepare/finalize integrado antes del checkpoint;
- estado `rollback_processing`;
- capability `rollback_wla_imports`, admin-only por defecto;
- inspector fail-closed;
- preview read-only con hash de frescura y conteos;
- restorer con compensación local para updates;
- attachments preservados;
- unit foundation y smoke guards iniciales.

## Pendiente

- servicio de confirmación/ejecución reanudable;
- token/preview freshness server-side;
- integración admin/history con nonce dedicado;
- Activity sin payload privado;
- integración WordPress/MySQL create/update/manual-change;
- tests security capability/nonce/IDOR;
- workflow dedicado/matrices mínimas/latest;
- actualización final de TESTING/TEST-CASE-CATALOG/IMPORT-EXPORT/status/backlog;
- revisión de findings y QA completa;
- artifacts/checksum;
- `QA_PASSED / READY_TO_MERGE` solo cuando todo esté verde.

## Producción

`propiedadesmartinez.cl` permanece sin cambios.
