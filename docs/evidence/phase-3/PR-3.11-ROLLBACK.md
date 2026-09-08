# Evidencia — PR 3.11 Rollback seguro best-effort

Estado: `IN_PROGRESS / QA_RUNNING`.

Issue: #66  
PR: #67 (draft)  
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
7. Propiedades creadas usan un fingerprint conservador de estado nativo del post, toda su meta y taxonomías; cualquier cambio posterior detectable bloquea la eliminación.
8. Si no se puede demostrar seguridad, se bloquea; nunca se fuerza.
9. Attachments no se eliminan durante 3.11. Rollback de media restaura referencias de galería/featured y conserva archivos antiguos y nuevos.
10. Rollback se procesa por slices mediante `rollback_processing`, no en un request monolítico.
11. `rollback_wla_imports` es capability destructiva dedicada y queda solo para Administrador de WordPress por defecto.
12. Confirmación está ligada a `batch revision + preview_hash` fresco y se revalida antes de mutar.
13. Cada fila se vuelve a inspeccionar inmediatamente antes de restaurarla/eliminarla.
14. Un lock atómico con TTL impide dos runners de rollback concurrentes para el mismo batch.
15. Producción no participa en tests ni migraciones de esta fase.

## Arquitectura implementada

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
RollbackService::begin(revision, preview_hash)
  ↓
rollback_processing
  ↓ slices en orden inverso + lock TTL
RollbackInspector revalida cada fila
  ↓
RollbackRestorer
  ├── create: elimina solo si fingerprint completo coincide
  └── update: restaura solo targets tocados
       └── compensación local: si before falla, intenta restaurar after
  ↓
rolled_back | rollback_blocked
```

## Persistencia

Nueva tabla `wla_import_rollback_journal`.

Schema actual: `DB_VERSION = 2`.

Clave física de fila: `source_row`, con UNIQUE `(batch_uuid,source_row)`. El dominio sigue exponiendo `row_number`; el nombre físico se mantiene aislado en `RollbackJournalRepository`.

Campos principales:

- `batch_uuid`;
- `source_row`;
- `property_id`;
- `original_action`;
- `targets_json`;
- `before_json`;
- `after_json` + `after_hash`;
- `created_object_hash`;
- `journal_state`;
- `rollback_status` + razón estable;
- timestamps.

Los snapshots no contienen la fila de origen completa. Solo guardan el mínimo estado canónico necesario para restaurar los targets tocados. Para decidir si una propiedad creada puede borrarse, el estado más amplio se conserva únicamente como SHA-256.

## Crash safety

El intent se registra antes de `RowExecutor`. Si el proceso cae después de crear WordPress pero antes de finalizar journal/checkpoint:

1. la fila conserva `original_action=created` en estado `prepared`;
2. el retry puede resolverla como `update` por identidad sin crear duplicado;
3. `finalize()` consulta la acción original del journal, no la clasificación del retry;
4. genera `created_object_hash` y deja la fila `ready`;
5. rollback posterior sigue tratándola como objeto creado por ese batch.

Existe integración WordPress/MySQL específica para este escenario.

## Seguridad de datos

- meta inexistente y meta existente vacía son estados distintos;
- identidad/source key se restaura junto a `external_id` cuando corresponde;
- taxonomías se guardan por IDs canónicos;
- galería se restaura mediante `gallery_ids` internos y preserva el bit `exists`;
- featured image se restaura mediante attachment ID;
- attachments remotos/Media Library no se borran;
- Search, Quality e Identity se sincronizan después de cada reversa;
- edición posterior en target tocado bloquea update rollback;
- edición posterior fuera del scope de un update se conserva y no bloquea por sí sola;
- properties creadas usan fingerprint de campos editoriales nativos, meta completa y taxonomías;
- propiedades históricas sin journal completo no pueden obtener rollback automático;
- Activity no recibe snapshots, UUID crudo ni payload privado.

## Autorización y UI

- capability dedicada: `rollback_wla_imports`;
- Administrador WordPress: sí por defecto;
- `wla_inmo_manager`, editor y lead manager: no por defecto;
- `RoleManager::VERSION = 2` fuerza reconciliación en instalaciones existentes;
- tres acciones separadas: preview / confirm / run;
- tres nonce-actions independientes;
- batch ownership o `manage_tools` para impedir IDOR;
- preview guardado por usuario/batch con TTL;
- confirmación no confía en hidden fields: `RollbackService::begin()` recalcula preview/revision/hash;
- procesamiento reanudable por slices;
- UI no muestra snapshots.

## Observabilidad

Activity registra únicamente eventos sanitizados a nivel batch:

- rollback previewed;
- rollback started;
- rollback completed;
- rollback blocked.

El contexto usa referencia hash del batch, conteos/estado/código estable. No registra snapshots ni datos privados.

## Tests implementados

### Unit / smoke

- codec determinista y hash estable;
- inexistente vs vacío;
- schema y UNIQUE;
- guard contra columna física MySQL `row_number`;
- estado `rollback_processing` y terminales;
- capability Administrator-only;
- preview `canConfirm` solo sin blocked/errors;
- orden `journal prepare → executor → finalize → checkpoint`;
- ausencia de `wp_delete_attachment` en restorer;
- compensation path presente.

### WordPress + MySQL 8

`tests/integration/assert-import-rollback.php`:

- create → preview safe → rollback → propiedad/identidad/proyecciones eliminadas;
- rollback repetido no muta;
- update → rollback restaura título/precio;
- cambio manual fuera de scope se conserva;
- cambio manual en target tocado bloquea y conserva dato humano;
- create modificada después bloquea eliminación;
- preview stale bloquea confirmación antes de transición/mutación;
- schema físico usa `source_row`.

`tests/integration/assert-import-rollback-crash.php`:

- caída después de create y antes de checkpoint;
- retry resuelve UPDATE sin duplicado;
- journal conserva `original_action=created`;
- rollback posterior elimina el objeto originalmente creado.

`tests/integration/assert-import-rollback-media.php`:

- galería anterior restaurada;
- featured anterior restaurado;
- attachments anteriores y nuevos permanecen en Media Library.

`tests/integration/assert-rollback-admin-access.php` + administración:

- capability destructiva separada;
- migración de roles v1 → v2;
- manager/editor/lead manager sin rollback por defecto;
- ownership / `manage_tools` para IDOR;
- tres nonce actions distintas;
- nonce inválido no verifica.

Workflow dedicado: `.github/workflows/import-rollback-integration.yml` con matriz:

- WordPress 6.6.2 / PHP 8.1 / MySQL 8;
- WordPress latest / PHP 8.3 / MySQL 8.

## Findings de QA corregidos

### F-3.11-01 — MySQL 8 / `row_number`

**Síntoma:** `dbDelta()` fallaba creando el journal y las importaciones terminaban en `rollback_journal_prepare_failed`.

**Causa:** `row_number` colisiona con `ROW_NUMBER()`/gramática de MySQL 8.

**Corrección:** columna física renombrada a `source_row`, mapeada a `row_number` solo en el dominio. Schema incrementado a v2 para forzar upgrade en instalaciones de desarrollo que hubieran registrado la versión anterior.

**Estado:** FIXED; pendiente confirmar toda la matriz final verde.

### F-3.11-02 — WPCS / exception chaining

**Síntoma:** el security profile interpretaba argumentos `Throwable` de excepciones internas como salida sin escapar.

**Corrección:** supresiones quirúrgicas `WordPress.Security.EscapeOutput.ExceptionNotEscaped` exclusivamente alrededor de chaining/rethrow interno, con justificación inline. No se relajó el estándar global ni se modificó escaping de UI.

**Estado:** FIXED; pendiente confirmar quality gate final verde.

### F-3.11-03 — Migración de capability

**Síntoma detectado en auditoría:** agregar la capability a `RoleMatrix` no alcanzaba a Administradores existentes porque `RoleManager::VERSION` permanecía en 1.

**Corrección:** `RoleManager::VERSION = 2` + integración que simula upgrade v1 → v2 y comprueba mínimo privilegio.

**Estado:** FIXED.

## CI actual

El workflow dedicado de rollback y las regresiones existentes están ejecutándose sobre el head actual de PR #67. No se declara QA aprobada hasta tener matrices finales verdes y revisar logs/reviews.

## Pendiente antes de READY_TO_MERGE

- confirmar workflow `Import Rollback Integration` verde en ambas matrices;
- confirmar regressions CSV/JSON/XLSX/runner/admin/core verdes;
- revisar PHPStan/WPCS finales;
- actualizar catálogo de tests/IMPORT-EXPORT/backlog/status;
- revisar PR comments/threads y P0/P1;
- registrar run IDs/head final/artifacts cuando existan;
- cambiar a `QA_PASSED / READY_TO_MERGE` solo con evidencia final.

## Producción

`propiedadesmartinez.cl` permanece sin cambios.
