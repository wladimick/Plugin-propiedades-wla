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
- PR 1.1–1.8: `DONE`
- PR 2.1: #24 `DONE`
- PR 2.2: #26 `DONE`
- Issue activo Fase 2.3: #27
- Rama activa Fase 2.3: `feat/phase2-guided-property-editor`

## Fases

| Fase | Nombre | Estado | Evidencia principal |
|---|---|---|---|
| 0 | Gobierno y diseño | DONE | `/docs`, PR #1, ADR-001–ADR-013 |
| 1 | Core del plugin | DONE | PR #5/#8/#10/#12/#14/#16/#18/#20, `docs/evidence/phase-1/` |
| 2 | Administración | IN_PROGRESS | `PHASE-2-BACKLOG.md`, PR #24/#26, Issue #27, `docs/evidence/phase-2/` |
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

- PR #5 — Bootstrap/build.
- PR #8 — Entidad Property.
- PR #10 — Taxonomías.
- PR #12 — Meta schema/validación.
- PR #14 — Índice/sincronización.
- PR #16 — Roles/capabilities.
- PR #18 — Settings/contrato con temas.
- PR #20 — Quality Gate/release `0.1.0-alpha`, squash `a142a4373ef37e14cd20b5a99105abeab0c1778d`.
- CI final `33826185833`: SUCCESS.
- Artifact QA `9920034253`.
- ZIP SHA-256 `c6189cd0a295fbec807c412e93ffe1c545df1b594e9219a8d18465db02767dde`.

## Fase 2 — Administración

Estado: `IN_PROGRESS`.

Backlog: `docs/PHASE-2-BACKLOG.md`.

### PR 2.1 — Admin shell, navegación y screen registry

Estado: `DONE`.

- PR #24, squash `50d3800477006af51cd4604009178105ed8002c0`;
- CI `33827079706`: SUCCESS;
- Bootstrap Smoke `33827079713`: SUCCESS;
- Artifact `9920346563`;
- evidencia `docs/evidence/phase-2/PR-2.1-ADMIN-SHELL.md`.

### PR 2.2 — Listado profesional de Propiedades

Estado: `DONE`.

- Issue #25: CLOSED;
- PR #26: MERGED;
- squash `15991b70d471fd2ba2ecbf88a762b2fdd9996b09`;
- CI `33829256386`: SUCCESS;
- Bootstrap Smoke `33829256549`: SUCCESS;
- WordPress 6.6.2 + PHP 8.1: SUCCESS;
- WordPress latest + PHP 8.3: SUCCESS;
- Artifact `9921060323`;
- Artifact digest `sha256:37bf4b613fd59c221126307f0147cc2241f476d3dfa4fe76301abc9d2f54dcae`;
- ZIP SHA-256 `660253f9ddb801ca64471066234f7db05fdbec2c6fd6674a9f34edfd4af611bb`;
- evidencia `docs/evidence/phase-2/PR-2.2-PROPERTY-LIST.md`.

El índice derivado continúa almacenando solo propiedades publicadas. No se incluyeron borradores ni datos privados para facilitar filtros administrativos.

### PR 2.3 — Editor guiado de Propiedad

Estado: `IN_PROGRESS / QA PENDING`.

Issue: #27  
Rama: `feat/phase2-guided-property-editor`

Alcance implementado en la rama:

- ficha guiada de 12 secciones;
- editor clásico nativo habilitado exclusivamente para `wla_property`, sin plugin externo y sin afectar otros post types;
- título, descripción, publicación, imagen destacada y revisiones siguen siendo nativos de WordPress;
- UI de campos apoyada en `MetaSchema`, `Sanitizer` y `Validator` sin segundo schema de dominio;
- operación, tipo, región, comuna y sector integrados dentro de la ficha;
- metaboxes de taxonomías dispersos retirados del editor de propiedades;
- campos `external_id`, `private_address` e `internal_notes` identificados como privados;
- nonce + `edit_post` + capability de términos en escrituras;
- autosaves/revisiones ignorados por el handler propio;
- validación completa antes de persistir meta/términos WLA;
- prevención de `property_code` duplicado incluyendo borradores, sin meter borradores al índice público;
- snapshot/rollback de meta y términos si una escritura de taxonomía falla;
- errores accesibles con resumen y error asociado al campo;
- valores ingresados se conservan de forma segura temporalmente después de un error;
- cero framework JS nuevo;
- smoke test `tests/smoke/property-editor.php`;
- integración WordPress ampliada con casos de nonce inválido, código duplicado y usuario sin permiso;
- release smoke exige `Admin\\PropertyEditor`.

Pendiente:

- abrir PR;
- ejecutar WPCS/PHPStan/PHPUnit/smoke/integración real;
- corregir findings;
- registrar artifact/checksum;
- squash merge;
- actualizar evidencia a `DONE`.

Evidencia en progreso: `docs/evidence/phase-2/PR-2.3-GUIDED-PROPERTY-EDITOR.md`.

### Orden restante previsto

- PR 2.4 multimedia/galería;
- PR 2.5 Calidad del catálogo;
- PR 2.6 Centro de Ayuda y ayuda contextual;
- PR 2.7 Ajustes UI;
- PR 2.8 Actividad/historial base;
- PR 2.9 Dashboard/Resumen operativo;
- PR 2.10 Quality Gate de Administración.

## Findings / deuda no bloqueante conocida

No existen findings críticos/altos abiertos conocidos dentro del alcance ya cerrado de Fase 1, PR 2.1 y PR 2.2.

Deuda de prioridad baja heredada:

- `composer.lock` de tooling aún no está versionado; resolver antes de Beta;
- PHPStan debe expandir cobertura progresivamente;
- warnings Node observados provienen de actions de terceros/GitHub, no del runtime del plugin.

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
10. Una futura búsqueda indexada de borradores debe usar un mecanismo administrativo separado; no reutilizar el índice público.

## Producción

`propiedadesmartinez.cl` no ha sido modificado. Cualquier despliegue/migración productiva requerirá una solicitud explícita posterior y su propia evidencia.

## Regla de actualización

Un ítem solo pasa a `DONE` con PR, tests/evidencia o documento que lo sustente. Una decisión aceptada no cambia silenciosamente: requiere ADR/PR.

## Auditoría

Para auditoría completa usar `AUDIT-TRACEABILITY.md`. Para revisión rápida comenzar aquí y continuar por Decision Register, fases, PRs, catálogo de tests y evidencias.
