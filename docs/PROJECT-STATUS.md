# Estado del proyecto

Registro vivo para auditorías rápidas. La evidencia detallada permanece en `docs/evidence/`.

## Estado general

- Proyecto: WLA Inmo
- Tema de referencia opcional: WLA Inmo Light
- Etapa actual: `PHASE-4 / FRONTEND AGNÓSTICO — READY_TO_START`
- Versión de producto: `0.1.0-alpha`
- Fase 0: `DONE`
- Fase 1: `DONE`
- Fase 2: `DONE`
- Fase 3: `DONE`
- Producción: `NO AFECTADA`
- Decisiones críticas: D01–D75 `ACCEPTED`
- Registro de decisiones: `docs/decisions/DECISION-REGISTER.md`
- PR 3.10: `OMITTED / OUT_OF_SCOPE`
- PR 3.12: `DONE` — PR #70 / Issue #69 / squash `7152f43d0d0d2df3991d095102307755f2c85602`
- Siguiente hito: **Fase 4 — Frontend agnóstico al tema**

## Fases

| Fase | Nombre | Estado | Evidencia principal |
|---|---|---|---|
| 0 | Gobierno y diseño | DONE | `/docs`, PR #1, ADR-001–ADR-014 |
| 1 | Core del plugin | DONE | `docs/evidence/phase-1/` |
| 2 | Administración | DONE | `docs/evidence/phase-2/` |
| 3 | Import/Export | DONE | `docs/PHASE-3-BACKLOG.md`, `docs/evidence/phase-3/PR-3.12-CLOSURE.md` |
| 4 | Frontend agnóstico al tema | READY_TO_START | pendiente |
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

PR #24–#42 cubren shell/navegación, listado, editor guiado, multimedia, calidad del catálogo, ayuda/onboarding, ajustes, actividad, dashboard y Quality Gate de Administración.

Cierre de Fase 2: Administration Gate, Playwright, WordPress mínimo/latest, seguridad, responsive, axe y performance sintético validados.

## Fase 3 — Import/Export

Estado: `DONE`.

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
| 3.12 | Quality Gate Fase 3 | #70 / #69 | DONE | `PR-3.12-PHASE-3-QUALITY-GATE.md` + `PR-3.12-CLOSURE.md` |

### Cierre 3.12

PR #70 fue squash-mergeado en `main` como `7152f43d0d0d2df3991d095102307755f2c85602`. Issue #69 quedó `closed / completed`.

Head validado pre-merge: `387bcfb63e598cfcee17f0a7b7c2771706549664`.

Gate final:
- Phase 3 Quality Gate run #4 / `34278880095`: `SUCCESS`;
- 16/16 child gates: `SUCCESS`;
- 16/16 workflows individuales: `SUCCESS`;
- Playwright 14/14;
- WP 6.6.2/PHP 8.1/MySQL 8 + WP latest/PHP 8.3/MySQL 8: PASS;
- review comments/reviews/threads: 0/0/0;
- P0/P1 abiertos conocidos: 0;
- summary artifact `10077046518`;
- digest `sha256:4b3f771564f8b009e9a1a332b939a0e7afd820e89918282ed3c5d8c65c9bd3bb`.

Evidencia de cierre: `docs/evidence/phase-3/PR-3.12-CLOSURE.md`.

## Fase 4 — Frontend agnóstico al tema

Estado: `READY_TO_START`.

Objetivo de alto nivel: exponer archive/single/filtros/componentes frontend desde el plugin sin depender de WLA Inmo Light ni del tema activo, conservando override controlado desde tema y aislamiento CSS. El desglose formal de PRs se abrirá al iniciar la fase.

## Findings / deuda no bloqueante

No existen findings críticos/altos abiertos conocidos al cierre de Fase 3.

Deuda baja conocida:

- ampliar progresivamente PHPStan fuera de los dominios hoy gated;
- reevaluar PHP 8.1 antes de Beta/1.0 según soporte de dependencias;
- performance sintético de CI no constituye SLA productivo;
- `LOW-CI-NODE-RUNTIME`: warnings de transición Node 20 → Node 24 emitidos por acciones oficiales de GitHub; no bloquearon el gate.

## Regla de producción

`propiedadesmartinez.cl` permanece sin cambios hasta Fase 9 o una solicitud explícita posterior.
