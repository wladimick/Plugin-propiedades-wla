# Estado del proyecto

Este documento es el registro vivo para auditorías rápidas. Debe actualizarse cuando una fase cambie de estado o cuando se cierre un hito relevante.

## Estado general

- Proyecto: WLA Inmo
- Tema de referencia: WLA Inmo Light
- Etapa actual: `PHASE-1 / CORE`
- Fase 0: `DONE`
- Fase 1: `IN_PROGRESS`
- Código de producto: `0.1.0-alpha.1`
- Producción: no afectada
- Decisiones críticas: D01–D75 `ACCEPTED`
- Registro: `docs/decisions/DECISION-REGISTER.md`
- PR 1.1: #5 `DONE`
- PR 1.2: #8 `DONE`
- PR 1.3: #10 `DONE`
- PR 1.4: #12 `DONE`
- PR 1.5: #14 `DONE`
- PR 1.6: #16 `DONE`
- Issue activo Fase 1.7: #17

## Fases

| Fase | Nombre | Estado | Evidencia principal |
|---|---|---|---|
| 0 | Gobierno y diseño | DONE | `/docs`, PR #1, ADR-001–ADR-013 |
| 1 | Core del plugin | IN_PROGRESS | `PHASE-1-BACKLOG.md`, PR #5–#16, Issue #17, `docs/evidence/phase-1/` |
| 2 | Administración | PLANNED | pendiente |
| 3 | Import/Export | PLANNED | pendiente |
| 4 | Frontend agnóstico al tema | PLANNED | pendiente |
| 5 | WLA Inmo Light | PLANNED | pendiente |
| 6 | SEO/GEO/AEO | PLANNED | pendiente |
| 7 | Leads e indicadores | PLANNED | pendiente |
| 8 | Security hardening | PLANNED | pendiente |
| 9 | Migración Propiedades Martínez | PLANNED | pendiente |
| 10 | Release 1.0 | PLANNED | pendiente |

## Checklist de Fase 0

Fase 0 se encuentra `DONE`. Arquitectura, requisitos, modelo, stack, metodología, testing, quality gates, administración, seguridad, SEO/GEO/AEO, migración, ADR y decisiones D01–D75 están documentados. Ver `docs/decisions/DECISION-REGISTER.md` y `docs/evidence/`.

## Progreso de Fase 1

### PR 1.1 — Bootstrap y build
`DONE` — PR #5, squash `7ca5b05f6763a7f8dc83f60995b2dc0760f68114`.

### PR 1.2 — Entidad Property
`DONE` — PR #8, squash `da989ef50a9d066023ae2c00d776d05af3d3499c`.

### PR 1.3 — Taxonomías base
`DONE` — PR #10, squash `61954bfbab9827b6d07d6b6151b9095677951dee`.

### PR 1.4 — Meta schema canónico y validación
`DONE` — PR #12, squash `344a681653970c3c9a3237c15aef99fbb281bb4b`.

### PR 1.5 — Índice de búsqueda y sincronización
`DONE` — PR #14, squash `01560207b4872aa73d58e23f5ee2a58adfa6d0be`, CI SUCCESS.

### PR 1.6 — Roles y capabilities
`DONE`

- PR #16 — squash `b09292c3d30972e2a1c097306312c989b84e3f11`.
- CI run `33824793619`: SUCCESS.
- Roles/capabilities de mínimo privilegio incorporados.
- Evidencia: `docs/evidence/phase-1/PR-1.6-ROLES-CAPABILITIES.md`.

### PR 1.7 — Settings y contratos públicos mínimos
`IN_PROGRESS / QA PENDING`

Issue: #17  
Rama: `feat/phase1-settings-contracts`

Alcance en implementación:

- opción namespaced `wla_inmo_settings`;
- `Settings\\Schema`, `Repository` y `Registry`;
- preset Chile encapsulado en `Localization\\ChilePreset`;
- country/currency/unit/map provider/base de propiedades/branding opcional;
- autorización con `manage_wla_inmo_settings`;
- `property_base` configurable sin flush por request;
- `Frontend\\TemplateResolver` independiente del tema;
- overrides bajo `wla-inmo/` y fallback de plugin cuando exista;
- protección contra path traversal;
- hooks públicos mínimos para defaults/templates;
- `SETTINGS-CONTRACT.md` y `TEMPLATE-CONTRACT.md`;
- smoke tests del contrato.

No marcar PR 1.7 `DONE` hasta merge + CI verde.

## Decisiones aceptadas

ADR-001 a ADR-013; detalle D01–D75 en `docs/decisions/DECISION-REGISTER.md`.

## Riesgos trasladados

1. Índices SQL se ajustarán con benchmarks reales.
2. PhpSpreadsheet se medirá antes de 1.0.
3. Proveedor OSM de tiles/geocoding se definirá para alto tráfico.
4. Adaptadores SEO se validarán en Fase 6.
5. Multisite se valida progresivamente.
6. Lighthouse ≥95 es budget de referencia; CWV reales requieren datos productivos.
7. UX preventiva de códigos duplicados se reforzará en Admin/Import.
8. Cambiar `property_base` requerirá una operación controlada de rewrite en la futura UI.

## Regla de actualización

Un ítem solo pasa a `DONE` con PR, tests/evidencia o documento que lo sustente. Una decisión aceptada no cambia silenciosamente: requiere ADR/PR.

## Auditoría

Para auditoría completa usar `AUDIT-TRACEABILITY.md`. Para revisión rápida comenzar aquí y continuar por Decision Register, fases, PRs, catálogo de tests y evidencias.
