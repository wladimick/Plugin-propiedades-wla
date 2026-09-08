# Estado del proyecto

Registro vivo para auditorías rápidas. La evidencia detallada permanece en `docs/evidence/`.

## Estado general

- Proyecto: WLA Inmo
- Tema de referencia opcional: WLA Inmo Light
- Etapa actual: `PHASE-3 / IMPORT-EXPORT`
- Versión de producto: `0.1.0-alpha`
- Fase 0: `DONE`
- Fase 1: `DONE`
- Fase 2: `DONE`
- Fase 3: `IN_PROGRESS`
- Producción: `NO AFECTADA`
- Decisiones críticas: D01–D75 `ACCEPTED`
- Registro de decisiones: `docs/decisions/DECISION-REGISTER.md`
- PR 3.1–3.9: `DONE`
- PR 3.10: `OMITTED / OUT_OF_SCOPE`
- PR 3.11: `QA_PASSED / READY_TO_MERGE` — PR #67 / Issue #66
- Siguiente hito después del merge: **PR 3.12 — Quality Gate Fase 3**

## Fases

| Fase | Nombre | Estado | Evidencia principal |
|---|---|---|---|
| 0 | Gobierno y diseño | DONE | `/docs`, PR #1, ADR-001–ADR-014 |
| 1 | Core del plugin | DONE | `docs/evidence/phase-1/` |
| 2 | Administración | DONE | `docs/evidence/phase-2/` |
| 3 | Import/Export | IN_PROGRESS | `docs/PHASE-3-BACKLOG.md`, `docs/evidence/phase-3/` |
| 4 | Frontend agnóstico al tema | PLANNED | pendiente |
| 5 | WLA Inmo Light | PLANNED | pendiente |
| 6 | SEO/GEO/AEO | PLANNED | pendiente |
| 7 | Leads e indicadores | PLANNED | pendiente |
| 8 | Security hardening | PLANNED | pendiente |
| 9 | Migración Propiedades Martínez | PLANNED | pendiente |
| 10 | Release 1.0 | PLANNED | pendiente |

## Fase 0 — Gobierno y diseño

Estado: `DONE`.

Arquitectura, requisitos, modelo, stack, metodología, testing, quality gates, administración, seguridad, SEO/GEO/AEO, migración, ADR y decisiones D01–D75 documentados. ADR-014 implementa D31 para XLSX mediante PhpSpreadsheet 3.10.7 exacta y lectura bounded.

## Fase 1 — Core

Estado: `DONE`.

PR #5/#8/#10/#12/#14/#16/#18/#20 cubren bootstrap, Property, taxonomías, meta schema, índices, roles/capabilities, settings y Quality Gate de Core.

## Fase 2 — Administración

Estado: `DONE`.

| PR | Alcance | Estado |
|---|---|---|
| #24 | Admin shell/navegación | DONE |
| #26 | Listado profesional | DONE |
| #28 | Editor guiado | DONE |
| #30 | Multimedia/galería | DONE |
| #32 | Calidad del catálogo | DONE |
| #34 | Ayuda/onboarding | DONE |
| #36 | Ajustes UI | DONE |
| #38 | Actividad/historial | DONE |
| #40 | Dashboard | DONE |
| #42 | Quality Gate Administración | DONE |

Cierre de Fase 2: Administration Gate, Playwright, WordPress mínimo/latest, seguridad, responsive, axe y performance sintético quedaron validados en su evidencia.

## Fase 3 — Import/Export

Estado: `IN_PROGRESS`.

Backlog: `docs/PHASE-3-BACKLOG.md`  
Contrato: `docs/IMPORT-EXPORT.md`  
Evidencia: `docs/evidence/phase-3/`

| PR | Alcance | GitHub | Estado | Evidencia |
|---|---|---|---|---|
| 3.1 | Import domain / CSV foundation | #46 | DONE | `PR-3.1-IMPORT-DOMAIN-CSV.md` |
| 3.2 | Mapping + validation + dry-run | #49 | DONE | `docs/evidence/phase-3/` |
| 3.3 | Persistencia identidad/batches | #51 | DONE | `docs/evidence/phase-3/` |
| 3.4 | Row executor idempotente | #53 | DONE | `PR-3.4-ROW-EXECUTOR.md` |
| 3.5 | Batch runner reanudable | #55 | DONE | `PR-3.5-BATCH-RUNNER.md` |
| 3.6 | UI importador + historial | #57 / #56 | DONE | `PR-3.6-IMPORT-UI.md` |
| 3.7 | JSON WLA versionado | #60 / #58 | DONE | `PR-3.7-JSON-WLA.md` |
| 3.8 | XLSX streaming | #62 / #61 | DONE | `PR-3.8-XLSX.md` |
| 3.9 | Media remota segura | #64 / #63 | DONE | `PR-3.9-REMOTE-MEDIA.md` |
| 3.10 | Export CSV/XLSX | — | OMITTED / OUT_OF_SCOPE | `PHASE-3-SCOPE-2026-09-07.md` |
| 3.11 | Rollback seguro best-effort | #67 / #66 | QA_PASSED / READY_TO_MERGE | `PR-3.11-ROLLBACK.md` |
| 3.12 | Quality Gate Fase 3 | pendiente | PLANNED | pendiente |

### PR 3.7 — JSON WLA

Estado `DONE`.

JSON v1 se valida y normaliza a NDJSON privado server-side, conserva un único `BatchRunner`/`RowExecutor`, exporta bounded sin privados por defecto, soporta round-trip y tiene benchmark 100/1k/5k.

### PR 3.8 — XLSX

Estado `DONE`. Squash `a51cb361f4534935f13c94c72b5d961a88f7a743`.

PhpSpreadsheet 3.10.7 exacta, preflight ZIP/OOXML, límites/archive hardening, selección de hoja, chunks de 500 filas y normalización a NDJSON privado.

### PR 3.9 — Media remota

Estado `DONE`. Squash `1067d227ac0dea5b1a8a248b57cd217490e4031e`.

SSRF hardening, streaming bounded, validación real de imágenes, deduplicación SHA-256, galería/featured canónicos y retry/checkpoint seguro.

### PR 3.10 — Exportación CSV/XLSX

Estado `OMITTED / OUT_OF_SCOPE` por decisión aprobada el 2026-09-07. La importación CSV/XLSX y export JSON WLA permanecen. No se renumeran 3.11/3.12.

### PR 3.11 — Rollback seguro best-effort

Estado: `QA_PASSED / READY_TO_MERGE`. PR #67 / Issue #66.

Implementado:

- journal persistente por fila;
- intent antes de mutación y `after` antes del checkpoint;
- snapshots mínimos por scope para updates;
- fingerprint conservador para creates;
- preview read-only + stale protection;
- `rollback_processing` reanudable;
- lock TTL y revalidación por fila;
- rollback de updates sin pisar campos fuera del scope;
- creates solo se eliminan si el fingerprint sigue coincidiendo;
- media restaura referencias y preserva attachments;
- capability `rollback_wla_imports`, admin-only por defecto;
- nonces separados e IDOR protegido;
- Activity sanitizada;
- crash recovery create-before-checkpoint;
- schema MySQL 8 portable.

QA final pre-merge:

- head funcional: `f9dab169d02ee700417dbe375d03f585d9bb1075`;
- 16/16 workflows: SUCCESS;
- rollback WP 6.6.2 / PHP 8.1: SUCCESS;
- rollback WP latest / PHP 8.3: SUCCESS;
- artifact: `wla-inmo-0.1.0-alpha-quality`;
- digest: `sha256:67a33a0584146041e3ecc777cc77240e61259eebea9e2ca5d5449719d38c6347`;
- review comments: 0;
- reviews: 0;
- inline threads: 0;
- P0/P1 abiertos conocidos: 0.

Evidencia: `docs/evidence/phase-3/PR-3.11-ROLLBACK.md`.

`DONE` se registrará solo después del merge y del cierre de Issue #66.

### PR 3.12 — Quality Gate Fase 3

Estado: `PLANNED`.

Será el cierre transversal de todo Import/Export: CSV/JSON/XLSX/media/rollback, regresiones Core/Admin, seguridad, performance 100/1k/5k, accesibilidad/responsive, artifact/checksum y review final.

## Findings / deuda no bloqueante

No existen findings críticos/altos abiertos conocidos de Fase 1, Fase 2 o PR 3.1–3.11 listo para merge.

Deuda baja conocida:

- ampliar progresivamente PHPStan fuera de los dominios hoy gated;
- reevaluar PHP 8.1 antes de Beta/1.0 según soporte de dependencias;
- performance sintético de CI no constituye SLA productivo.

## Regla de producción

`propiedadesmartinez.cl` permanece sin cambios hasta Fase 9 o una solicitud explícita posterior.