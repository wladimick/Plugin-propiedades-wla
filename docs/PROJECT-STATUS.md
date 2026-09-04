# Estado del proyecto

Este documento es el registro vivo para auditorías rápidas. Debe actualizarse cuando una fase cambie de estado o cuando se cierre un hito relevante.

## Estado general

- Proyecto: WLA Inmo
- Tema de referencia: WLA Inmo Light
- Etapa actual: `PHASE-2 / ADMINISTRATION`
- Fase 0: `DONE`
- Fase 1: `DONE`
- Fase 2: `IN_PROGRESS`
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
- PR 2.1: #24 `DONE`
- Issue activo Fase 2.2: #25
- PR activa Fase 2.2: #26 `QA_PASSED / MERGE_PENDING`

## Fases

| Fase | Nombre | Estado | Evidencia principal |
|---|---|---|---|
| 0 | Gobierno y diseño | DONE | `/docs`, PR #1, ADR-001–ADR-013 |
| 1 | Core del plugin | DONE | PR #5/#8/#10/#12/#14/#16/#18/#20, `docs/evidence/phase-1/` |
| 2 | Administración | IN_PROGRESS | `PHASE-2-BACKLOG.md`, PR #24/#26, `docs/evidence/phase-2/` |
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

- PR #5 — 1.1 Bootstrap/build.
- PR #8 — 1.2 Entidad Property.
- PR #10 — 1.3 Taxonomías.
- PR #12 — 1.4 Meta schema/validación.
- PR #14 — 1.5 Índice/sincronización.
- PR #16 — 1.6 Roles/capabilities.
- PR #18 — 1.7 Settings/contrato con temas.
- PR #20 — 1.8 Quality Gate/release `0.1.0-alpha`, squash `a142a4373ef37e14cd20b5a99105abeab0c1778d`.
- Phase 1 CI final `33826185833`: SUCCESS.
- WordPress 6.6.2 + PHP 8.1: SUCCESS.
- WordPress latest + PHP 8.3: SUCCESS.
- Artifact QA: ID `9920034253`.
- ZIP SHA-256: `c6189cd0a295fbec807c412e93ffe1c545df1b594e9219a8d18465db02767dde`.

Evidencia completa en `docs/evidence/phase-1/`.

## Fase 2 — Administración

Estado: `IN_PROGRESS`.

Backlog: `docs/PHASE-2-BACKLOG.md`.

### PR 2.1 — Admin shell, navegación y screen registry

Estado: `DONE`.

- Issue #23: CLOSED.
- PR #24: MERGED.
- Squash merge: `50d3800477006af51cd4604009178105ed8002c0`.
- CI final `33827079706`: SUCCESS.
- Bootstrap Smoke `33827079713`: SUCCESS.
- WPCS, PHPStan, PHPUnit y smoke tests: SUCCESS.
- WordPress 6.6.2 + PHP 8.1: SUCCESS.
- WordPress latest + PHP 8.3: SUCCESS.
- Artifact `9920346563`.
- ZIP SHA-256 `f78779284caae48896a1c7f74de5f3d416fcac8eac2540a052ea0938fddfba6f`.
- Evidencia: `docs/evidence/phase-2/PR-2.1-ADMIN-SHELL.md`.

### PR 2.2 — Listado profesional de Propiedades

Estado: `QA_PASSED / MERGE_PENDING`.

Issue: #25  
PR: #26  
Rama: `feat/phase2-property-list`

Implementado:

- columnas profesionales: foto, título, código, operación, tipo, ubicación, precio, estado, destacada y actualización;
- precio administrativo basado únicamente en campos canónicos, respetando `hide_price`, `price_on_request` y `currency_primary`;
- filtros por operación, tipo, región, comuna, sector, estado y destacada;
- búsqueda ampliada por código y `external_id`, sin renderizar `external_id` en la tabla;
- filtros y orden por código apoyados en `wp_wla_property_index`;
- `LEFT JOIN` limitado al main query administrativo de `wla_property` y solo cuando hace falta;
- paginación nativa de WordPress preservada;
- DB schema version 2 con índices específicos para región, sector y estado/destacada;
- estilos responsivos y miniaturas sin cargar galerías completas;
- smoke test `tests/smoke/property-list.php`;
- integración WordPress real del upgrade de índice y presentación canónica;
- release smoke actualizado para exigir `Admin\\PropertyList` dentro del ZIP.

QA final sobre head `9baffcbcb54af6ac32a50fe037b042a73a9bab2f`:

- CI run `33829256386`: SUCCESS;
- Quality Gate PHP 8.1: SUCCESS;
- WPCS security profile: SUCCESS;
- PHPStan 2.2: SUCCESS;
- PHPUnit: `3 tests / 40 assertions`;
- smoke tests: SUCCESS;
- WordPress 6.6.2 + PHP 8.1: SUCCESS;
- WordPress latest + PHP 8.3: SUCCESS;
- Bootstrap Smoke `33829256549`: SUCCESS;
- Artifact `9921060323`;
- Artifact digest `sha256:37bf4b613fd59c221126307f0147cc2241f476d3dfa4fe76301abc9d2f54dcae`;
- ZIP SHA-256 `660253f9ddb801ca64471066234f7db05fdbec2c6fd6674a9f34edfd4af611bb`.

Findings corregidos:

- WPCS no seguía la sanitización/whitelist de dos filtros GET a través de variables intermedias; se conservaron los controles reales y se documentaron únicamente esas dos lecturas mediante excepciones lineales;
- la aserción de precio CLP de integración se hizo independiente del locale del WordPress de CI sin cambiar el render productivo.

El índice derivado mantiene la regla de Fase 1 y contiene solo propiedades publicadas. No se debilita ese contrato para permitir búsqueda de borradores.

Evidencia: `docs/evidence/phase-2/PR-2.2-PROPERTY-LIST.md`.

**No marcar PR 2.2 como DONE hasta que PR #26 esté efectivamente squash-mergeada.**

### Orden restante previsto

- PR 2.3 editor guiado de Propiedad;
- PR 2.4 multimedia/galería;
- PR 2.5 Calidad del catálogo;
- PR 2.6 Centro de Ayuda y ayuda contextual;
- PR 2.7 Ajustes UI;
- PR 2.8 Actividad/historial base;
- PR 2.9 Dashboard/Resumen operativo;
- PR 2.10 Quality Gate de Administración.

La implementación debe reutilizar capabilities, validators, settings e índice del Core; no crear lógica de dominio paralela en el admin.

## Findings / deuda no bloqueante conocida

No existen findings críticos/altos abiertos conocidos dentro del alcance cerrado de Fase 1, PR 2.1 ni PR 2.2 en estado QA.

Deuda de prioridad baja heredada:

- el `composer.lock` exacto del quality gate queda archivado dentro de cada artifact, pero aún no está versionado en el repositorio; decidir/incorporar antes de Beta;
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
10. Si el administrador necesita búsqueda indexada de borradores, debe diseñarse un índice administrativo separado; no reutilizar el índice público.

## Producción

`propiedadesmartinez.cl` no ha sido modificado. Cualquier despliegue/migración productiva requerirá una solicitud explícita posterior y su propia evidencia.

## Regla de actualización

Un ítem solo pasa a `DONE` con PR, tests/evidencia o documento que lo sustente. Una decisión aceptada no cambia silenciosamente: requiere ADR/PR.

## Auditoría

Para auditoría completa usar `AUDIT-TRACEABILITY.md`. Para revisión rápida comenzar aquí y continuar por Decision Register, fases, PRs, catálogo de tests y evidencias.
