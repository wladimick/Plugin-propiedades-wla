# Fase 3 — Import/Export

Estado: `IN_PROGRESS`  
Dependencias: Fase 1 `DONE`, Fase 2 `DONE`  
Issue de entrada: #43  
Versión: `0.1.0-alpha`

## Objetivo

Construir un sistema de importación seguro, repetible y usable para cientos o miles de propiedades, manteniendo una sola vía canónica de validación, identidad, escritura y checkpoint para CSV, JSON y XLSX.

Contrato funcional: `docs/IMPORT-EXPORT.md`.

## Estado canónico

| PR | Alcance | GitHub | Estado |
|---|---|---|---|
| 3.1 | Dominio de importación + CSV foundation | #46 | DONE |
| 3.2 | Mapping + validación + dry-run | #49 | DONE |
| 3.3 | Persistencia de identidad y batches | #51 | DONE |
| 3.4 | Executor idempotente de filas | #53 | DONE |
| 3.5 | Runner reanudable de batches | #55 | DONE |
| 3.6 | UI Importar + historial | #57 / #56 | DONE |
| 3.7 | JSON WLA versionado | #60 / #58 | DONE |
| 3.8 | XLSX streaming + ADR/benchmark | #62 / #61 | DONE |
| 3.9 | Media remota segura | #64 / #63 | DONE |
| 3.10 | Exportación CSV/XLSX | — | OMITTED / OUT_OF_SCOPE |
| 3.11 | Rollback seguro best-effort | #67 / #66 | QA_PASSED / READY_TO_MERGE |
| 3.12 | Quality Gate Fase 3 | pendiente | PLANNED |

La exportación CSV/XLSX se retiró explícitamente del alcance de Fase 3 el 2026-09-07. El número 3.10 se mantiene para auditoría y no se renumeran los hitos siguientes.

## Principios no negociables

1. **Dry-run obligatorio.** La simulación no muta WordPress ni descarga media.
2. **Identidad explícita.** `(source_key, external_id)` tiene prioridad y luego `property_code`; nunca título/dirección.
3. **Vacíos seguros.** Conservar por defecto; borrar requiere política explícita.
4. **Batches bounded.** Nunca procesar un dataset grande en un solo request.
5. **Resume seguro.** Solo desde checkpoints consistentes y con optimistic locking.
6. **Idempotencia.** Reintentos no crean duplicados silenciosos.
7. **Un solo pipeline.** CSV, JSON y XLSX convergen en mapping, dry-run, identidad, `BatchRunner` y `RowExecutor`.
8. **Media separada.** Cero HTTP durante dry-run y controles SSRF antes de descargar.
9. **Permisos reales.** Capability + nonce + autorización por objeto/operación.
10. **Observabilidad.** Identity, Search, Quality y Activity se mantienen coherentes.
11. **Sin producción.** Fixtures sintéticos y WordPress limpio durante Fase 3.
12. **Rollback fail-closed.** Si WLA no puede demostrar seguridad, no revierte.

## Pipeline canónico

```text
CSV | JSON | XLSX
      ↓
validación / normalización
      ↓
mapping canónico
      ↓
DryRunEngine
      ↓
confirmación hash + profile
      ↓
BatchRunner
      ↓
RowExecutor
      ↓
WordPress + Identity/Search/Quality
      ↓
checkpoint
```

JSON y XLSX se normalizan a NDJSON privado generado por servidor y reutilizan el mismo runner/executor.

## Estados del batch

Importación:

```text
uploaded
  ↓
mapped
  ↓
validated
  ↓
dry_run_ready
  ↓
confirmed
  ↓
processing
  ├──> paused ──> processing
  ├──> failed ──> processing
  └──> completed
```

Cancelación segura puede terminar en `cancelled` desde checkpoints permitidos.

Rollback 3.11:

```text
completed
  ↓ preview read-only + confirmación explícita
rollback_processing
  ├──> rolled_back
  └──> rollback_blocked
```

`rolled_back` y `rollback_blocked` son terminales.

## PR 3.1–3.6 — CSV, dominio, persistencia, runner y UI

Estado: `DONE`.

Quedaron implementados parser CSV incremental UTF-8, límites, `SourceKey`, `TargetRegistry`, `MappingProfile`, dry-run, resolución de identidad, constraints UNIQUE, `BatchRepository`, `RowExecutor`, `BatchRunner`, checkpoints, wizard CSV, workspace temporal, historial y janitor.

## PR 3.7 — JSON WLA versionado

Estado: `DONE`. PR #60 / Issue #58.

- `format_version=1`;
- shape/targets allowlisted;
- límites de bytes/profundidad/propiedades;
- JSON → NDJSON privado `0600`;
- SHA-256 + resume físico;
- mapping server-side read-only;
- export JSON bounded sin privados por defecto;
- round-trip;
- benchmark 100/1k/5k.

Evidencia: `docs/evidence/phase-3/PR-3.7-JSON-WLA.md`.

## PR 3.8 — XLSX

Estado: `DONE`. PR #62 / Issue #61. Squash `a51cb361f4534935f13c94c72b5d961a88f7a743`.

- ADR-014 implementa D31;
- PhpSpreadsheet 3.10.7 exacta;
- preflight ZIP/OOXML bounded;
- Zip Slip/archive expansion/macros/relationships externos bloqueados;
- selección explícita de hoja;
- chunks de 500 filas;
- XLSX → NDJSON privado;
- matriz PHP 8.1/8.3 y benchmark.

Evidencia: `docs/evidence/phase-3/PR-3.8-XLSX.md`.

## PR 3.9 — Media remota segura

Estado: `DONE`. PR #64 / Issue #63. Squash `1067d227ac0dea5b1a8a248b57cd217490e4031e`.

- targets portables de galería/featured;
- cero HTTP en dry-run;
- SSRF/DNS/IP/redirect hardening;
- streaming bounded;
- JPEG/PNG/WebP con validación real;
- deduplicación SHA-256;
- referencias WordPress canónicas;
- retries acotados y checkpoint seguro.

Evidencia: `docs/evidence/phase-3/PR-3.9-REMOTE-MEDIA.md`.

## PR 3.10 — Exportación CSV/XLSX

Estado: `OMITTED / OUT_OF_SCOPE`.

Registro: `docs/decisions/PHASE-3-SCOPE-2026-09-07.md`.

JSON WLA export permanece disponible. Una eventual exportación CSV/XLSX requerirá alcance futuro específico.

## PR 3.11 — Rollback seguro best-effort

Estado: `QA_PASSED / READY_TO_MERGE`. PR #67 / Issue #66.

Implementa D38 con enfoque conservador:

- journal persistente por fila, preparado antes de `RowExecutor`;
- snapshot `after` finalizado antes del checkpoint;
- snapshots mínimos `before/after` por scope de updates;
- fingerprint conservador para propiedades creadas;
- detección de cambios posteriores;
- preview read-only `safe/noop/blocked/error`;
- confirmación ligada a revision + `preview_hash` fresco;
- `rollback_processing` reanudable por slices;
- lock atómico con TTL;
- revalidación inmediatamente antes de mutar;
- restauración solo del scope tocado;
- compensación local en updates;
- galería/featured restaurados por IDs canónicos;
- attachments preservados;
- capability `rollback_wla_imports`, Administrator-only por defecto;
- nonces separados preview/confirm/run;
- ownership / `manage_tools` contra IDOR;
- Activity sin snapshots ni payload privado;
- crash recovery después de create y antes del checkpoint;
- schema MySQL 8 con `source_row` físico.

QA final pre-merge:

- head funcional `f9dab169d02ee700417dbe375d03f585d9bb1075`;
- **16/16 workflows SUCCESS**;
- rollback WP 6.6.2/PHP 8.1: SUCCESS;
- rollback WP latest/PHP 8.3: SUCCESS;
- CSV/JSON/XLSX/Remote Media/runner/admin/core: SUCCESS;
- comments/reviews/threads: 0;
- P0/P1 abiertos conocidos: 0;
- artifact `wla-inmo-0.1.0-alpha-quality` con digest `sha256:67a33a0584146041e3ecc777cc77240e61259eebea9e2ca5d5449719d38c6347`.

Evidencia: `docs/evidence/phase-3/PR-3.11-ROLLBACK.md`.

El estado pasa a `DONE` solo después del merge y cierre de Issue #66.

## PR 3.12 — Quality Gate Fase 3

Estado: `PLANNED`.

Debe cubrir como mínimo:

- regresión Fase 1/2;
- CSV/JSON/XLSX;
- archivos malformados/encoding/duplicados/conflictos;
- stale dry-run;
- resume/idempotencia;
- media/SSRF;
- rollback safe/blocked/crash recovery;
- capability/nonce/IDOR;
- datasets 100/1k/5k y peak memory;
- accesibilidad/responsive del importador;
- artifact/checksum;
- review final sin P0/P1;
- evidencia de salida de Fase 3.

## Producción

`propiedadesmartinez.cl` permanece sin cambios hasta Fase 9 o una solicitud explícita posterior.