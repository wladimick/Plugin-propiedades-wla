# Estado del proyecto

Este documento es el registro vivo para auditorías rápidas. Debe actualizarse cuando una fase cambie de estado o cuando se cierre un hito relevante.

## Estado general

- Proyecto: WLA Inmo
- Tema de referencia: WLA Inmo Light
- Etapa actual: `PHASE-2 / ADMINISTRATION — ENTRY APPROVED`
- Fase 0: `DONE`
- Fase 1: `DONE`
- Fase 2: `PLANNED / ENTRY APPROVED`
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
- PR 1.8: #20 `DONE`
- Issue Fase 1.8: #19 `CLOSED`
- Entrada Fase 2: aprobada y documentada en `docs/PHASE-2-BACKLOG.md`

## Fases

| Fase | Nombre | Estado | Evidencia principal |
|---|---|---|---|
| 0 | Gobierno y diseño | DONE | `/docs`, PR #1, ADR-001–ADR-013 |
| 1 | Core del plugin | DONE | PR #5/#8/#10/#12/#14/#16/#18/#20, `docs/evidence/phase-1/` |
| 2 | Administración | PLANNED / ENTRY APPROVED | `PHASE-2-BACKLOG.md`, `ADMIN-SECTIONS.md` |
| 3 | Import/Export | PLANNED | pendiente |
| 4 | Frontend agnóstico al tema | PLANNED | pendiente |
| 5 | WLA Inmo Light | PLANNED | pendiente |
| 6 | SEO/GEO/AEO | PLANNED | pendiente |
| 7 | Leads e indicadores | PLANNED | pendiente |
| 8 | Security hardening | PLANNED | pendiente |
| 9 | Migración Propiedades Martínez | PLANNED | pendiente |
| 10 | Release 1.0 | PLANNED | pendiente |

## Fase 0 — Gobierno y diseño

Estado: `DONE`.

Arquitectura, requisitos, modelo, stack, metodología, testing, quality gates, administración, seguridad, SEO/GEO/AEO, migración, ADR y decisiones D01–D75 están documentados.

## Fase 1 — Core del plugin

Estado: `DONE`.

### PR 1.1 — Bootstrap y build

PR #5 — squash `7ca5b05f6763a7f8dc83f60995b2dc0760f68114`.

### PR 1.2 — Entidad Property

PR #8 — squash `da989ef50a9d066023ae2c00d776d05af3d3499c`.

### PR 1.3 — Taxonomías base

PR #10 — squash `61954bfbab9827b6d07d6b6151b9095677951dee`.

### PR 1.4 — Meta schema canónico y validación

PR #12 — squash `344a681653970c3c9a3237c15aef99fbb281bb4b`.

### PR 1.5 — Índice de búsqueda y sincronización

PR #14 — squash `01560207b4872aa73d58e23f5ee2a58adfa6d0be`, CI SUCCESS.

### PR 1.6 — Roles y capabilities

PR #16 — squash `b09292c3d30972e2a1c097306312c989b84e3f11`, CI SUCCESS.

### PR 1.7 — Settings y contratos públicos mínimos

PR #18 — squash `2f0f215ee4d68501af11a0168b8be31c9a9144be`, CI SUCCESS.

### PR 1.8 — Quality Gate y release `0.1.0-alpha`

Estado: `DONE`.

- Issue #19: CLOSED.
- PR #20: MERGED.
- Squash merge: `a142a4373ef37e14cd20b5a99105abeab0c1778d`.
- Phase 1 CI final: `33826185833` — SUCCESS.
- Bootstrap Smoke final: `33826185820` — SUCCESS.
- WPCS security profile: SUCCESS.
- PHPStan 2.2: SUCCESS.
- PHPUnit: `3 tests / 40 assertions`.
- WordPress 6.6.2 + PHP 8.1: SUCCESS.
- WordPress latest + PHP 8.3: SUCCESS.
- Desactivación conserva datos: SUCCESS.
- Uninstall conserva datos: SUCCESS.

Artefacto QA:

- Artifact ID `9920034253`.
- Nombre `wla-inmo-0.1.0-alpha-quality`.
- Artifact digest `sha256:fd4cc13c55f9dec8d8355b1836b429f76345d9f64c62777d3a5c60547e4ccd45`.
- ZIP instalable SHA-256 `c6189cd0a295fbec807c412e93ffe1c545df1b594e9219a8d18465db02767dde`.
- Evidencia `docs/evidence/phase-1/PR-1.8-QUALITY-GATE.md`.

### Criterio de salida de Fase 1

Cumplido:

- PR 1.1–1.8 mergeadas;
- CI relevante verde;
- integración WordPress real verde;
- datos conservados al desactivar/uninstall;
- artifact instalable generado;
- evidencia disponible;
- sin findings críticos/altos abiertos conocidos del alcance.

## Fase 2 — Administración

Estado: `PLANNED / ENTRY APPROVED`.

Backlog: `docs/PHASE-2-BACKLOG.md`.

Orden previsto:

- PR 2.1 Admin shell, navegación y screen registry;
- PR 2.2 listado profesional de Propiedades;
- PR 2.3 editor guiado de Propiedad;
- PR 2.4 multimedia/galería;
- PR 2.5 Calidad del catálogo;
- PR 2.6 Centro de Ayuda y ayuda contextual;
- PR 2.7 Ajustes UI;
- PR 2.8 Actividad/historial base;
- PR 2.9 Dashboard/Resumen operativo;
- PR 2.10 Quality Gate de Administración.

La implementación debe reutilizar las capabilities, validators, settings e índice de Fase 1; no crear lógica de dominio paralela en el admin.

## Findings / deuda no bloqueante conocida

No existen findings críticos/altos abiertos conocidos dentro del alcance cerrado de Fase 1.

Deuda de prioridad baja:

- el `composer.lock` exacto del quality gate quedó archivado dentro del artifact, pero no está versionado en el repositorio; decidir/incorporar antes de Beta;
- PHPStan nivel 6 cubre inicialmente contratos puros seleccionados y debe expandirse progresivamente;
- warnings deprecatorios Node observados provienen de actions de terceros/GitHub, no del runtime del plugin.

## Riesgos trasladados

1. Índices SQL se ajustarán con benchmarks reales.
2. PhpSpreadsheet se medirá antes de 1.0.
3. Proveedor OSM de tiles/geocoding se definirá para alto tráfico.
4. Adaptadores SEO se validarán en Fase 6.
5. Multisite se valida progresivamente.
6. Lighthouse ≥95 es budget de referencia; CWV reales requieren datos productivos.
7. UX preventiva de códigos duplicados se refuerza desde Fase 2 y Fase 3.
8. Cambiar `property_base` requerirá una operación controlada de rewrite en la UI.
9. Lock de tooling debe resolverse antes de Beta.

## Producción

`propiedadesmartinez.cl` no ha sido modificado por las Fases 0–1 ni por el cierre documental. Cualquier despliegue/migración productiva requerirá una solicitud explícita posterior y su propia evidencia.

## Regla de actualización

Un ítem solo pasa a `DONE` con PR, tests/evidencia o documento que lo sustente. Una decisión aceptada no cambia silenciosamente: requiere ADR/PR.

## Auditoría

Para auditoría completa usar `AUDIT-TRACEABILITY.md`. Para revisión rápida comenzar aquí y continuar por Decision Register, fases, PRs, catálogo de tests y evidencias.
