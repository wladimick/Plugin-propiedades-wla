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
- Fase 3: `IN_PROGRESS` — cierre 3.12 `QA_PASSED / READY_TO_MERGE`
- Producción: `NO AFECTADA`
- Decisiones críticas: D01–D75 `ACCEPTED`
- Registro de decisiones: `docs/decisions/DECISION-REGISTER.md`
- PR 3.1–3.9: `DONE`
- PR 3.10: `OMITTED / OUT_OF_SCOPE`
- PR 3.11: `DONE` — PR #67 / Issue #66 / squash `4e53a89af1f3fd1a20d139ed5894552b223bf0f5`
- PR 3.12: `QA_PASSED / READY_TO_MERGE` — PR #70 / Issue #69
- Próxima transición: merge de PR #70 y cierre post-merge de Fase 3; recién entonces Fase 3 pasa a `DONE`.

## Fases

| Fase | Nombre | Estado | Evidencia principal |
|---|---|---|---|
| 0 | Gobierno y diseño | DONE | `/docs`, PR #1, ADR-001–ADR-014 |
| 1 | Core del plugin | DONE | `docs/evidence/phase-1/` |
| 2 | Administración | DONE | `docs/evidence/phase-2/` |
| 3 | Import/Export | IN_PROGRESS / QA_PASSED | `docs/PHASE-3-BACKLOG.md`, `docs/evidence/phase-3/PR-3.12-PHASE-3-QUALITY-GATE.md` |
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

Estado: `IN_PROGRESS`; PR 3.12 está `QA_PASSED / READY_TO_MERGE`.

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
| 3.11 | Rollback seguro best-effort | #67 / #66 | DONE | `PR-3.11-ROLLBACK.md` + `PR-3.11-CLOSURE.md` |
| 3.12 | Quality Gate Fase 3 | #70 / #69 | QA_PASSED / READY_TO_MERGE | `PR-3.12-PHASE-3-QUALITY-GATE.md` |

### PR 3.12 — Quality Gate Fase 3

El gate maestro compone 16 suites reales mediante `workflow_call`; no duplica la lógica de los tests y genera un manifest auditable que exige `success` en todas las suites.

QA pre-merge validada en run `34276667461` sobre head `aec897f658cbe382cd3667108e3d0026721bd36c`:

- 16/16 child gates `SUCCESS`;
- WPCS / PHPStan / PHPUnit / smoke / build `SUCCESS`;
- WP 6.6.2/PHP 8.1/MySQL 8 y WP latest/PHP 8.3/MySQL 8 `SUCCESS`;
- CSV/JSON/XLSX/media/rollback/Core/Admin `SUCCESS`;
- Playwright 14/14;
- responsive 1440/1024/768/390/360;
- axe WCAG 2.2 AA sin findings serious/critical cubiertos;
- comments/reviews/threads: 0/0/0;
- P0/P1 abiertos conocidos: 0.

Artifact summary: `10076114220`, digest `sha256:e2ac168091fe7a9732db6d6e70fbbc998f12416f9e550b815cee63a3a064da7d`.

La Fase 3 no se declara `DONE` en una rama pre-merge. El cierre definitivo se registra después de integrar PR #70 en `main`.

## Findings / deuda no bloqueante

No existen findings críticos/altos abiertos conocidos de Fase 1, Fase 2 o PR 3.1–3.12.

Deuda baja conocida:

- ampliar progresivamente PHPStan fuera de los dominios hoy gated;
- reevaluar PHP 8.1 antes de Beta/1.0 según soporte de dependencias;
- performance sintético de CI no constituye SLA productivo;
- warnings de transición Node 20 → Node 24 emitidos por acciones oficiales de GitHub; no bloquearon el gate y deben revisarse como mantenimiento de CI.

## Regla de producción

`propiedadesmartinez.cl` permanece sin cambios hasta Fase 9 o una solicitud explícita posterior.
