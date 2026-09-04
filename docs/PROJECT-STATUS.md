# Estado del proyecto

Este documento es el registro vivo para auditorías rápidas. Debe actualizarse cuando una fase cambie de estado o cuando se cierre un hito relevante. La evidencia detallada de cada PR permanece en `docs/evidence/`.

## Estado general

- Proyecto: WLA Inmo
- Tema de referencia opcional: WLA Inmo Light
- Etapa actual: `PHASE-2 / ADMINISTRATION`
- Fase 0: `DONE`
- Fase 1: `DONE`
- Fase 2: `IN_PROGRESS`
- Código de producto: `0.1.0-alpha`
- Producción: no afectada
- Decisiones críticas: D01–D75 `ACCEPTED`
- Registro: `docs/decisions/DECISION-REGISTER.md`
- PR 1.1–1.8: `DONE`
- PR 2.1–2.7: `DONE`
- Próximo hito: `PR 2.8 — Actividad / historial administrativo base`

## Fases

| Fase | Nombre | Estado | Evidencia principal |
|---|---|---|---|
| 0 | Gobierno y diseño | DONE | `/docs`, PR #1, ADR-001–ADR-013 |
| 1 | Core del plugin | DONE | PR #5/#8/#10/#12/#14/#16/#18/#20, `docs/evidence/phase-1/` |
| 2 | Administración | IN_PROGRESS | PR #24/#26/#28/#30/#32/#34/#36, `docs/evidence/phase-2/` |
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

| PR | Alcance | GitHub | Estado | Evidencia |
|---|---|---|---|---|
| 2.1 | Admin shell y navegación | #24 | DONE | `PR-2.1-ADMIN-SHELL.md` |
| 2.2 | Listado profesional | #26 | DONE | `PR-2.2-PROPERTY-LIST.md` |
| 2.3 | Editor guiado | #28 | DONE | `PR-2.3-GUIDED-PROPERTY-EDITOR.md` |
| 2.4 | Multimedia y galería | #30 | DONE | `PR-2.4-PROPERTY-MEDIA.md` |
| 2.5 | Calidad del catálogo | #32 | DONE | `PR-2.5-CATALOGUE-QUALITY.md` |
| 2.6 | Centro de Ayuda y onboarding | #34 | DONE | `PR-2.6-HELP-CENTER.md` |
| 2.7 | Ajustes UI | #36 | DONE | `PR-2.7-SETTINGS-UI.md` |
| 2.8 | Actividad / historial base | pendiente | NEXT | pendiente |
| 2.9 | Dashboard / Resumen operativo | pendiente | PLANNED | pendiente |
| 2.10 | Quality Gate de Administración | pendiente | PLANNED | pendiente |

### PR 2.1 — Admin shell

- PR #24;
- squash `50d3800477006af51cd4604009178105ed8002c0`;
- CI `33827079706`: SUCCESS;
- Bootstrap Smoke `33827079713`: SUCCESS;
- Artifact `9920346563`.

### PR 2.2 — Listado profesional

- PR #26;
- squash `15991b70d471fd2ba2ecbf88a762b2fdd9996b09`;
- CI `33829256386`: SUCCESS;
- WordPress 6.6.2/PHP 8.1 y latest/PHP 8.3: SUCCESS;
- Artifact `9921060323`;
- ZIP SHA-256 `660253f9ddb801ca64471066234f7db05fdbec2c6fd6674a9f34edfd4af611bb`.

El índice público continúa almacenando solo propiedades publicadas.

### PR 2.3 — Editor guiado

- PR #28;
- squash `a02b0bd6fa0c0ceb3430d5410c0c2a46bc0f5b35`;
- CI `33830157300`: SUCCESS;
- WordPress 6.6.2/PHP 8.1 y latest/PHP 8.3: SUCCESS;
- Artifact `9921377798`;
- ZIP SHA-256 `3d3f6e68e27768cf10904fe468f90c4106382824ec0fd61d5c7915e5caf7660a`.

La ficha guiada usa el MetaSchema canónico, autorización por objeto, prevención de código duplicado y rollback lógico de meta/términos WLA.

### PR 2.4 — Multimedia y galería

- Issue #29: CLOSED;
- PR #30: MERGED;
- squash `af5c0f2ee59639ae749a0daec81d329e46eef6cc`;
- Biblioteca de Medios nativa, galería ordenable, ALT protegido por capability y videos como URLs seguras;
- no existe borrado físico al desasociar una imagen;
- assets limitados al editor de `wla_property`;
- integración WordPress y release smoke: SUCCESS.

### PR 2.5 — Calidad del catálogo

- Issue #31: CLOSED;
- PR #32: MERGED;
- squash `a3e28e0984e6bea30828baba636dae3abde08d98`;
- proyección administrativa `wp_wla_property_quality` separada del índice público;
- score interno explicable 0–100 basado en 11 checks, sin presentarlo como ranking de Google;
- filtros Incompletas/Completas/Sin precio/Sin imagen;
- pantalla Calidad del catálogo y rebuild seguro;
- CI `33833270755`: SUCCESS;
- Catalogue Quality Integration `33833270754`: SUCCESS;
- Artifact `9922420818`;
- ZIP SHA-256 `26a0f6ea5589a8febe8cd0a0d7c16f233f2fcd647aca23161b41e7374a5eb22a`.

### PR 2.6 — Centro de Ayuda y onboarding

- Issue #33: CLOSED;
- PR #34: MERGED;
- squash `56717aa3af97c74407d794f334b295216fe067f8`;
- Centro de Ayuda local con 13+ temas, búsqueda, FAQ y glosario;
- módulos futuros etiquetados como `Próximamente`;
- onboarding de 6 pasos persistido por usuario, reversible y protegido por nonce/capabilities;
- ayuda contextual dentro del editor de propiedades;
- JS vanilla, sin llamadas remotas;
- Phase 1 CI `33858810497`: SUCCESS;
- Help Center Integration `33858810499`: SUCCESS;
- Catalogue Quality Integration `33858810510`: SUCCESS;
- Bootstrap Smoke `33858810523`: SUCCESS;
- Artifact `9931277294`;
- ZIP SHA-256 `cc7b2f3b325fb187eb11d0501e21ea70db86edb607149a66dd43690efaf3fb66`.

### PR 2.7 — Ajustes UI

- Issue #35: CLOSED;
- PR #36: MERGED;
- squash `5c99ed02f4a71cf57716cc8d9a46cb4094856464`;
- pantalla real de Ajustes con 8 pestañas;
- contrato canónico de contacto y políticas de retención;
- `property_base` con pending state y aplicación controlada de rewrites, sin flush en cada request ni durante sanitización;
- cobertura del primer guardado de settings mediante `add_option_wla_inmo_settings`;
- Phase 1 CI `33861845409`: SUCCESS;
- Settings UI Integration `33861845590`: SUCCESS en WordPress 6.6.2/PHP 8.1 y latest/PHP 8.3;
- Catalogue Quality Integration `33861845599`: SUCCESS;
- Help Center Integration `33861845705`: SUCCESS;
- Bootstrap Smoke `33861845445`: SUCCESS;
- Artifact `9932423348`;
- ZIP SHA-256 `a04e74b6c19a1a296f9960e5237785be6c3352b042a4b91cb01317137b18e6a9`.

## Findings / deuda no bloqueante conocida

No existen findings críticos o altos abiertos conocidos dentro del alcance cerrado de Fase 1 y PR 2.1–2.7.

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
7. Migraciones futuras que cambien slugs existentes deberán conservar URLs o definir 301 explícitas; el cambio manual de `property_base` ya usa operación controlada.
8. Una futura búsqueda indexada de borradores debe usar un mecanismo administrativo separado; no reutilizar el índice público.
9. La optimización final de imágenes, lightbox y prioridades de carga frontend corresponde a Fase 4/5.

## Producción

`propiedadesmartinez.cl` no ha sido modificado. Cualquier despliegue o migración productiva requiere una solicitud explícita posterior y su propia evidencia.

## Regla de actualización

Un ítem solo pasa a `DONE` con PR, tests/evidencia o documento que lo sustente. Una decisión aceptada no cambia silenciosamente: requiere ADR/PR.

## Auditoría

Para auditoría completa usar `AUDIT-TRACEABILITY.md`. Para revisión rápida comenzar aquí y continuar por Decision Register, fases, PRs, catálogo de tests y evidencias.
