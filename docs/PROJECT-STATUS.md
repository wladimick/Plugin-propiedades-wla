# Estado del proyecto

Registro vivo para auditorías rápidas. La evidencia detallada permanece en `docs/evidence/`.

## Estado general

- Proyecto: WLA Inmo
- Tema de referencia opcional: WLA Inmo Light
- Etapa actual: `PHASE-4 / PR-4.1 FRONTEND FOUNDATION — FINAL_GATE_PENDING`
- Versión de producto: `0.1.0-alpha`
- Fase 0: `DONE`
- Fase 1: `DONE`
- Fase 2: `DONE`
- Fase 3: `DONE`
- Fase 4: `IN_PROGRESS`
- Producción: `NO AFECTADA`
- Decisiones críticas: D01–D75 `ACCEPTED`
- Registro de decisiones: `docs/decisions/DECISION-REGISTER.md`
- PR 3.10: `OMITTED / OUT_OF_SCOPE`
- PR 3.12: `DONE` — PR #70 / Issue #69 / squash `7152f43d0d0d2df3991d095102307755f2c85602`
- Cierre documental Fase 3: PR #71 / squash `05d92e82bcab0dbcb6b1ebe6fdfbe9635d909c00`
- Fase 4 entry gate: Issue #72 / PR #73
- PR 4.1: PR #75 / Issue #74 / `FINAL_GATE_PENDING`
- Siguiente hito después del cierre 4.1: **PR 4.2 — Archive + property card + pagination**

## Fases

| Fase | Nombre | Estado | Evidencia principal |
|---|---|---|---|
| 0 | Gobierno y diseño | DONE | `/docs`, PR #1, ADR-001–ADR-014 |
| 1 | Core del plugin | DONE | `docs/evidence/phase-1/` |
| 2 | Administración | DONE | `docs/evidence/phase-2/` |
| 3 | Import/Export | DONE | `docs/PHASE-3-BACKLOG.md`, `docs/evidence/phase-3/PR-3.12-CLOSURE.md` |
| 4 | Frontend agnóstico al tema | IN_PROGRESS | `docs/PHASE-4-BACKLOG.md`, `docs/evidence/phase-4/` |
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

El cierre documental post-merge quedó integrado mediante PR #71 como `05d92e82bcab0dbcb6b1ebe6fdfbe9635d909c00`.

## Fase 4 — Frontend agnóstico al tema

Estado: `IN_PROGRESS / PR-4.1-FINAL-GATE`.

Issue de entrada: #72  
Backlog: `docs/PHASE-4-BACKLOG.md`  
Contrato frontend: `docs/FRONTEND.md`  
Evidencia: `docs/evidence/phase-4/`

Objetivo: exponer archive/single/filtros/componentes frontend desde el plugin sin depender de WLA Inmo Light ni del tema activo, conservando override controlado desde tema y aislamiento CSS.

La fase implementa decisiones ya aceptadas D15–D17, D20–D26, D62–D66 y D74–D75. No existe una nueva decisión estructural pendiente para PR 4.1.

Secuencia:

| PR | Alcance | GitHub | Estado | Evidencia |
|---|---|---|---|---|
| 4.1 | Frontend foundation / template resolver / assets | #75 / #74 | FINAL_GATE_PENDING | `PR-4.1-FRONTEND-FOUNDATION.md` |
| 4.2 | Archive + property card + pagination | — | PLANNED | pendiente |
| 4.3 | Search + filtros GET | — | PLANNED | pendiente |
| 4.4 | Single + detalles + galería | — | PLANNED | pendiente |
| 4.5 | Mapa + privacidad de ubicación | — | PLANNED | pendiente |
| 4.6 | CTA + API de presentación | — | PLANNED | pendiente |
| 4.7 | Theme overrides + compatibility matrix | — | PLANNED | pendiente |
| 4.8 | Quality Gate final Fase 4 | — | PLANNED | pendiente |

### PR 4.1 — scope implementado antes del gate final

- `Frontend\TemplateResolver` fail-closed y allowlisted;
- precedencia child → parent → plugin bajo `wla-inmo/`;
- `Frontend\Bootstrap` para routing archive/single únicamente;
- `Frontend\Renderer` con `$wla_args`, sin `extract()`, filtro de args y hooks before/after;
- `Frontend\Assets` con CSS condicional y sin dependencias;
- fallbacks mínimos archive/single;
- sin JS placeholder;
- sin acceso arbitrario a meta privada;
- workflow dedicado WP/PHP mínimo + latest;
- release smoke + PHPStan Frontend + smoke/integration específicos.

La primera ejecución de QA detectó dos defectos de harness/tests heredados y un faltante de scope (Renderer). Se corrigieron sin relajar seguridad ni runtime. El head documental final debe repetir la suite completa antes de quitar draft o mergear.

Regla de implementación de Fase 4: SSR primero, Vanilla JS progresivo, CSS `wla-inmo-*`, assets condicionales, datos privados fuera de templates públicos y compatibilidad sin Elementor/WooCommerce/ACF/jQuery.

## Findings / deuda no bloqueante

No existen findings críticos/altos abiertos conocidos al cierre de Fase 3.

Deuda baja conocida:

- ampliar progresivamente PHPStan fuera de los dominios hoy gated;
- reevaluar PHP 8.1 antes de Beta/1.0 según soporte de dependencias;
- performance sintético de CI no constituye SLA productivo;
- `LOW-CI-NODE-RUNTIME`: warnings de transición Node 20 → Node 24 emitidos por acciones oficiales de GitHub; no bloquearon los gates previos.

## Regla de producción

`propiedadesmartinez.cl` permanece sin cambios hasta Fase 9 o una solicitud explícita posterior.
