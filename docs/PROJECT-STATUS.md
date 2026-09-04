# Estado del proyecto

Este documento es el registro vivo para auditorías rápidas. Debe actualizarse cuando una fase cambie de estado o cuando se cierre un hito relevante.

## Estado general

- Proyecto: WLA Inmo
- Tema de referencia: WLA Inmo Light
- Etapa actual: `PHASE-1 / CORE — QA_PASSED / MERGE_PENDING`
- Fase 0: `DONE`
- Fase 1: `IN_PROGRESS — PR 1.8 MERGE_PENDING`
- Código de producto: `0.1.0-alpha`
- Producción: no afectada
- Decisiones críticas: D01–D75 `ACCEPTED`
- Registro: `docs/decisions/DECISION-REGISTER.md`
- PR 1.1: #5 `DONE`
- PR 1.2: #8 `DONE`
- PR 1.3: #10 `DONE`
- PR 1.4: #12 `DONE`
- PR 1.5: #14 `DONE`
- PR 1.6: #16 `DONE`
- PR 1.7: #18 `DONE`
- Issue activo Fase 1.8: #19
- PR activa Fase 1.8: #20 `QA_PASSED / MERGE_PENDING`

## Fases

| Fase | Nombre | Estado | Evidencia principal |
|---|---|---|---|
| 0 | Gobierno y diseño | DONE | `/docs`, PR #1, ADR-001–ADR-013 |
| 1 | Core del plugin | IN_PROGRESS / MERGE_PENDING | PR #5/#8/#10/#12/#14/#16/#18/#20, `docs/evidence/phase-1/` |
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
`DONE` — PR #16, squash `b09292c3d30972e2a1c097306312c989b84e3f11`, CI SUCCESS.

### PR 1.7 — Settings y contratos públicos mínimos
`DONE`

- PR #18 — squash `2f0f215ee4d68501af11a0168b8be31c9a9144be`.
- CI final `33825238074`: SUCCESS.
- Settings namespaced, preset Chile desacoplado y contrato de templates para cualquier tema incorporados.
- Evidencia: `docs/evidence/phase-1/PR-1.7-SETTINGS-CONTRACTS.md`.

### PR 1.8 — Quality Gate y release `0.1.0-alpha`

Estado: `QA_PASSED / MERGE_PENDING`.

Issue: #19  
PR: #20  
Rama: `ci/phase1-quality-gate`

Quality Gate final:

- Phase 1 CI run `33826185833`: SUCCESS;
- Quality Gate PHP 8.1: SUCCESS;
- WPCS security profile: SUCCESS;
- PHPStan 2.2: SUCCESS;
- PHPUnit: `3 tests / 40 assertions`;
- smoke tests: SUCCESS;
- WordPress 6.6.2 + PHP 8.1: SUCCESS;
- WordPress latest + PHP 8.3: SUCCESS;
- desactivación conserva datos: SUCCESS;
- uninstall conserva datos: SUCCESS;
- Bootstrap Smoke run `33826185820`: SUCCESS.

Artefacto final QA:

- ID `9920034253`;
- `wla-inmo-0.1.0-alpha-quality`;
- artifact digest `sha256:fd4cc13c55f9dec8d8355b1836b429f76345d9f64c62777d3a5c60547e4ccd45`;
- ZIP instalable SHA-256 `c6189cd0a295fbec807c412e93ffe1c545df1b594e9219a8d18465db02767dde`.

Evidencia: `docs/evidence/phase-1/PR-1.8-QUALITY-GATE.md`.

**No marcar Fase 1 como DONE hasta que PR #20 esté efectivamente mergeada.**

## Findings / deuda no bloqueante conocida

No existen findings críticos/altos abiertos conocidos dentro del alcance de Fase 1.

Deuda de prioridad baja registrada:

- el `composer.lock` exacto de tooling del quality gate queda preservado dentro del artifact final, pero todavía no está versionado en el repositorio; debe evaluarse su incorporación antes de Beta para reforzar reproducibilidad del entorno de desarrollo;
- PHPStan parte en nivel 6 sobre contratos puros seleccionados y debe ampliar cobertura progresivamente;
- las advertencias deprecatorias de Node provienen de actions de terceros/GitHub y no del runtime del plugin.

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
9. El lock de tooling debe decidirse/versionarse antes de Beta.

## Regla de actualización

Un ítem solo pasa a `DONE` con PR, tests/evidencia o documento que lo sustente. Una decisión aceptada no cambia silenciosamente: requiere ADR/PR.

## Auditoría

Para auditoría completa usar `AUDIT-TRACEABILITY.md`. Para revisión rápida comenzar aquí y continuar por Decision Register, fases, PRs, catálogo de tests y evidencias.
