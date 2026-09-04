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
- Issue activo Fase 2.1: #23
- PR activa Fase 2.1: #24 `QA_PASSED / MERGE_PENDING`

## Fases

| Fase | Nombre | Estado | Evidencia principal |
|---|---|---|---|
| 0 | Gobierno y diseño | DONE | `/docs`, PR #1, ADR-001–ADR-013 |
| 1 | Core del plugin | DONE | PR #5/#8/#10/#12/#14/#16/#18/#20, `docs/evidence/phase-1/` |
| 2 | Administración | IN_PROGRESS | `PHASE-2-BACKLOG.md`, PR #24, `docs/evidence/phase-2/` |
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

Estado: `QA_PASSED / MERGE_PENDING`.

Issue: #23  
PR: #24  
Rama: `feat/phase2-admin-shell`

Implementado:

- `Admin\\ScreenRegistry` con las 16 secciones/enlaces documentados;
- menú raíz `WLA Inmo` protegido por `view_wla_inmo_dashboard`;
- CPT `wla_property` anidado mediante `show_in_menu = wla-inmo`;
- pantallas Propiedades/Nueva propiedad delegadas al mecanismo nativo de WordPress, sin registro duplicado;
- placeholders de módulos futuros solo con capability correspondiente;
- segundo control de capability para acceso directo por URL;
- Resumen inicial sin queries de métricas;
- patrón de ayuda contextual;
- CSS admin namespaced/condicional, sin JS ni React;
- smoke test `admin-shell.php`;
- integración WordPress real del parent del CPT;
- release ZIP actualizado con clases/assets Admin.

QA final:

- Phase 1 CI run `33827079706`: SUCCESS;
- WPCS security profile: SUCCESS;
- PHPStan 2.2: SUCCESS;
- PHPUnit: `3 tests / 40 assertions`;
- smoke tests: SUCCESS;
- WordPress 6.6.2 + PHP 8.1: SUCCESS;
- WordPress latest + PHP 8.3: SUCCESS;
- Bootstrap Smoke run `33827079713`: SUCCESS;
- Artifact `9920346563`;
- ZIP SHA-256 `f78779284caae48896a1c7f74de5f3d416fcac8eac2540a052ea0938fddfba6f`.

Evidencia: `docs/evidence/phase-2/PR-2.1-ADMIN-SHELL.md`.

Findings documentados y corregidos:

- WPCS solicitó nonce sobre GET de routing exclusivamente de lectura; se documentaron excepciones lineales después de confirmar que no existe mutación y se mantuvo sanitización;
- se evitó duplicación potencial de submenús al dejar Propiedades/Nueva propiedad bajo responsabilidad nativa de WordPress.

**No marcar PR 2.1 como DONE hasta que PR #24 esté efectivamente mergeada.**

### Orden restante previsto

- PR 2.2 listado profesional de Propiedades;
- PR 2.3 editor guiado de Propiedad;
- PR 2.4 multimedia/galería;
- PR 2.5 Calidad del catálogo;
- PR 2.6 Centro de Ayuda y ayuda contextual;
- PR 2.7 Ajustes UI;
- PR 2.8 Actividad/historial base;
- PR 2.9 Dashboard/Resumen operativo;
- PR 2.10 Quality Gate de Administración.

La implementación debe reutilizar capabilities, validators, settings e índice de Fase 1; no crear lógica de dominio paralela en el admin.

## Findings / deuda no bloqueante conocida

No existen findings críticos/altos abiertos conocidos dentro del alcance cerrado de Fase 1 ni de PR 2.1 en estado QA.

Deuda de prioridad baja heredada:

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

`propiedadesmartinez.cl` no ha sido modificado. Cualquier despliegue/migración productiva requerirá una solicitud explícita posterior y su propia evidencia.

## Regla de actualización

Un ítem solo pasa a `DONE` con PR, tests/evidencia o documento que lo sustente. Una decisión aceptada no cambia silenciosamente: requiere ADR/PR.

## Auditoría

Para auditoría completa usar `AUDIT-TRACEABILITY.md`. Para revisión rápida comenzar aquí y continuar por Decision Register, fases, PRs, catálogo de tests y evidencias.
