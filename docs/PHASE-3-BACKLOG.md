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
| 3.11 | Rollback seguro best-effort | #67 / #66 | DONE |
| 3.12 | Quality Gate Fase 3 | #70 / #69 | QA_PASSED / READY_TO_MERGE |

La exportación CSV/XLSX se retiró explícitamente del alcance de Fase 3 el 2026-09-07. El número 3.10 se mantiene para auditoría y no se renumeran los hitos siguientes.

> La Fase 3 permanece `IN_PROGRESS` hasta que PR #70 sea mergeado en `main`. El estado `QA_PASSED / READY_TO_MERGE` describe únicamente el hito 3.12 pre-merge.

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

Estado: `DONE`. PR #67 / Issue #66. Squash `4e53a89af1f3fd1a20d139ed5894552b223bf0f5`.

Implementa D38 con enfoque conservador: journal persistente por fila, snapshots mínimos, preview read-only, stale protection, slices reanudables, lock TTL, revalidación antes de mutar, restauración scoped, media por IDs canónicos, attachments preservados, capability destructiva separada, nonces independientes, protección IDOR, Activity sanitizada y crash recovery create-before-checkpoint.

Evidencia QA: `docs/evidence/phase-3/PR-3.11-ROLLBACK.md`.  
Cierre post-merge: `docs/evidence/phase-3/PR-3.11-CLOSURE.md`.

## PR 3.12 — Quality Gate Fase 3

Estado: `QA_PASSED / READY_TO_MERGE`. PR #70 / Issue #69.

### Diseño

Los 16 workflows existentes mantienen sus triggers normales y agregan `workflow_call`. El nuevo `.github/workflows/phase3-quality-gate.yml` los compone sin duplicar la lógica de prueba y genera un summary único que falla si cualquier child gate no termina en `success`.

### QA pre-merge auditada

Run: `34276667461` (`Phase 3 Quality Gate`, run #2) sobre head `aec897f658cbe382cd3667108e3d0026721bd36c`.

- 16/16 child gates: `SUCCESS`;
- Phase 1 CI / WPCS / PHPStan / PHPUnit / smoke / build: `SUCCESS`;
- WordPress 6.6.2 / PHP 8.1 / MySQL 8: `SUCCESS`;
- WordPress latest (7.1 observado) / PHP 8.3 / MySQL 8: `SUCCESS`;
- CSV / JSON / XLSX / media / rollback / Core / Admin: `SUCCESS`;
- Playwright Admin + Import UI: 14/14;
- responsive: 1440 / 1024 / 768 / 390 / 360;
- axe WCAG 2.2 AA: sin findings `serious`/`critical` en superficies cubiertas;
- review comments: 0;
- reviews: 0;
- inline threads: 0;
- P0/P1 abiertos conocidos: 0.

Performance observada, solo como regresión de CI:

- JSON 5k WP 6.6.2/PHP 8.1: 1079.68 ms, peak delta 8 MiB;
- JSON 5k WP 7.1/PHP 8.3: 975.29 ms, peak delta 8 MiB;
- dashboard 5k: 5 queries / 0.0080 s;
- property list 5k: 2 queries / 0.0039 s.

Artifacts del mismo run:

- summary `10076114220`, digest `sha256:e2ac168091fe7a9732db6d6e70fbbc998f12416f9e550b815cee63a3a064da7d`;
- quality ZIP `10076055577`, digest `sha256:51001c3b3653a0aa70eac5c7945c639dace20b77dc6cb7f7157afa26127cbb6a`;
- installable ZIP `10076011626`, digest `sha256:e737694a4de9bcd7f1d4852f74f0c47896d99427a697919aad1d73994a6e2c3e`;
- Admin E2E `10076060248`, digest `sha256:9d765fb30330ee73d17239d006fb7ad2c86a35bfd2cc32b7a90bb60f5f3fc267`.

Evidencia: `docs/evidence/phase-3/PR-3.12-PHASE-3-QUALITY-GATE.md`.

### Deuda no bloqueante

GitHub Actions emitió warnings de transición Node 20 → Node 24 en acciones oficiales. Se registra como deuda `LOW`; no produjo fallos ni findings P0/P1 y debe revisarse cuando las acciones upstream publiquen/estabilicen sus runtimes actualizados.

## Producción

`propiedadesmartinez.cl` permanece sin cambios hasta Fase 9 o una solicitud explícita posterior.
