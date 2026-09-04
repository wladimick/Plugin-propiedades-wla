# Estado del proyecto

Este documento es el registro vivo para auditorías rápidas. Debe actualizarse cuando una fase cambie de estado o cuando se cierre un hito relevante. La evidencia detallada de cada PR permanece en `docs/evidence/`.

## Estado general

- Proyecto: WLA Inmo
- Tema de referencia opcional: WLA Inmo Light
- Etapa actual: `PHASE-3 / IMPORT-EXPORT`
- Fase 0: `DONE`
- Fase 1: `DONE`
- Fase 2: `DONE`
- Fase 3: `PLANNING / ENTRY APPROVED`
- Código de producto: `0.1.0-alpha`
- Producción: no afectada
- Decisiones críticas: D01–D75 `ACCEPTED`
- Registro: `docs/decisions/DECISION-REGISTER.md`
- PR 1.1–1.8: `DONE`
- PR 2.1–2.10: `DONE`
- Próximo hito: definir backlog ejecutable de Fase 3 y abrir PR 3.1

## Fases

| Fase | Nombre | Estado | Evidencia principal |
|---|---|---|---|
| 0 | Gobierno y diseño | DONE | `/docs`, PR #1, ADR-001–ADR-013 |
| 1 | Core del plugin | DONE | PR #5/#8/#10/#12/#14/#16/#18/#20, `docs/evidence/phase-1/` |
| 2 | Administración | DONE | PR #24/#26/#28/#30/#32/#34/#36/#38/#40/#42, `docs/evidence/phase-2/` |
| 3 | Import/Export | PLANNING / ENTRY APPROVED | `docs/IMPORT-EXPORT.md`; backlog por crear |
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
- CI final de Fase 1: SUCCESS.

## Fase 2 — Administración

Estado: `DONE`.

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
| 2.8 | Actividad / historial base | #38 | DONE | `PR-2.8-ACTIVITY-HISTORY.md` |
| 2.9 | Dashboard / Resumen operativo | #40 | DONE | `PR-2.9-OPERATIONAL-DASHBOARD.md` |
| 2.10 | Quality Gate de Administración | #42 | DONE | `PR-2.10-ADMIN-QUALITY-GATE.md` |

### PR 2.1 — Admin shell

- PR #24;
- squash `50d3800477006af51cd4604009178105ed8002c0`;
- CI/Bootstrap: SUCCESS.

### PR 2.2 — Listado profesional

- PR #26;
- squash `15991b70d471fd2ba2ecbf88a762b2fdd9996b09`;
- WordPress 6.6.2/PHP 8.1 y latest/PHP 8.3: SUCCESS.

El índice público continúa almacenando solo propiedades publicadas.

### PR 2.3 — Editor guiado

- PR #28;
- squash `a02b0bd6fa0c0ceb3430d5410c0c2a46bc0f5b35`;
- CI e integración WordPress: SUCCESS.

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
- score interno explicable 0–100 basado en checks, sin presentarlo como ranking de Google;
- filtros operativos y pantalla Calidad del catálogo;
- CI/integración: SUCCESS.

### PR 2.6 — Centro de Ayuda y onboarding

- Issue #33: CLOSED;
- PR #34: MERGED;
- squash `56717aa3af97c74407d794f334b295216fe067f8`;
- Centro de Ayuda local, búsqueda, FAQ y glosario;
- onboarding persistido por usuario y protegido por nonce/capabilities;
- ayuda contextual en el editor;
- JS vanilla, sin llamadas remotas;
- CI/integración: SUCCESS.

### PR 2.7 — Ajustes UI

- Issue #35: CLOSED;
- PR #36: MERGED;
- squash `5c99ed02f4a71cf57716cc8d9a46cb4094856464`;
- pantalla real de Ajustes con ocho pestañas;
- contrato canónico de contacto y políticas de retención;
- `property_base` con pending state y aplicación controlada de rewrites;
- CI/integración: SUCCESS.

### PR 2.8 — Actividad e historial administrativo

- Issue #37: CLOSED;
- PR #38: MERGED;
- squash `6f8de10db3ef03256d8f1bf73c894370fb5ac4b8`;
- tabla versionada `wla_inmo_activity` con contexto allowlisted;
- bitácora de eventos operativos relevantes;
- pantalla Actividad e Historial operativo por propiedad;
- retención configurable, cron y limpieza por lotes;
- sin payloads completos, IP/user-agent, dirección privada, notas internas ni valores de contacto;
- CI/integración: SUCCESS.

### PR 2.9 — Dashboard / Resumen operativo

- Issue #39: CLOSED;
- PR #40: MERGED;
- squash `bcff7e17eeda5122d6845c3cc38f14a71d04b57c`;
- Resumen basado en datos reales y excepciones accionables;
- Dashboard con 5 queries principales sin Actividad y consultas bounded;
- privacidad explícita de campos internos;
- Dashboard Integration y regresiones heredadas: SUCCESS.

### PR 2.10 — Quality Gate de Administración

- Issue #41: CLOSED;
- PR #42: MERGED;
- squash `5f8d314fe0cad79ba0d29c3feed7577ca5ec642b`;
- head funcional validado `190cdf8787e92c17c715ce195e7620cd55cf704d`;
- Administration Quality Gate `33874413262`: SUCCESS;
- Playwright: **8/8 SUCCESS, retries=0**;
- axe sobre UI WLA propia: sin findings serious/critical en los flujos cubiertos;
- teclado: disclosure del editor validado con focus + Enter;
- responsive: 360/390/768/1024/1440 en pantallas prioritarias;
- seguridad/autorización negativa: SUCCESS;
- assets condicionales: smoke SUCCESS;
- performance sintético:
  - Dashboard 100: 5 queries / 0,0033 s;
  - Dashboard 1k: 5 queries / 0,0037 s;
  - Dashboard 5k: 5 queries / 0,0085 s;
  - listado 5k: 2 queries / 0,0040 s;
  - Actividad: 2 queries / 0,0011 s;
- Phase 1 CI `33874412820`: SUCCESS;
- PHPUnit: 3 tests / 40 assertions;
- artifact plugin `9937251373`, digest `sha256:a699b9024db2932ee1a59b6940f0e0f7b53b397236831675087593f20a81a1a7`;
- ZIP SHA-256 `cb567d3a5abf320f49fbb238ec308ee64548303b0198e64667632e18876e2581`;
- artifact E2E `9937305918`, digest `sha256:4aee29b463c856e20b0084d270b8e03fd920f4e9dfbab3bb307112d894b23682`;
- findings responsive/test/UX detectados durante QA fueron corregidos y revalidados;
- evidencia final: `docs/evidence/phase-2/PR-2.10-ADMIN-QUALITY-GATE.md`.

Los tiempos son referencias sintéticas de CI, no promesas de rendimiento productivo.

## Fase 3 — Import/Export

Estado: `PLANNING / ENTRY APPROVED`.

Fuente funcional existente: `docs/IMPORT-EXPORT.md`.

El siguiente trabajo debe convertir ese contrato en un backlog de PR pequeños, con prioridad inicial en identidad/upsert, parser CSV, dry-run, batching e historial antes de añadir XLSX, imágenes remotas o exportaciones avanzadas.

Reglas de entrada ya aceptadas:

- formatos objetivo XLSX, CSV UTF-8 y JSON;
- flujo upload → detección → mapping → validación → dry-run → confirmación → batches → reporte;
- identidad de reimportación: `external_id` cuando el perfil de origen lo define, luego `property_code`; nunca título/dirección;
- vacíos preservan valor existente por defecto;
- dry-run no crea posts ni descarga imágenes;
- importación por lotes y reanudable;
- imágenes remotas con controles SSRF/MIME/tamaño/timeout;
- protección contra formula injection en exportaciones;
- historial y evidencia por batch;
- rollback únicamente donde sea técnicamente seguro y explicable.

## Findings / deuda no bloqueante conocida

No existen findings críticos o altos abiertos conocidos dentro del alcance cerrado de Fase 1 y Fase 2.

Deuda de prioridad baja heredada:

- `composer.lock` de tooling PHP aún no está versionado; resolver antes de Beta;
- PHPStan debe expandir cobertura progresivamente;
- warnings Node observados provienen de actions de terceros/GitHub, no del runtime del plugin.

El texto obsoleto del editor sobre PR 2.4/2.5/2.8 fue corregido en PR 2.10 y ya no se considera deuda abierta.

## Riesgos trasladados

1. Índices SQL se ajustarán con benchmarks reales.
2. La librería XLSX elegida para Fase 3 debe medirse antes de 1.0.
3. Proveedor OSM de tiles/geocoding se definirá para alto tráfico.
4. Adaptadores SEO se validarán en Fase 6.
5. Multisite se valida progresivamente.
6. Lighthouse ≥95 es budget de referencia; CWV reales requieren datos productivos.
7. Migraciones futuras que cambien slugs existentes deberán conservar URLs o definir 301 explícitas.
8. Una futura búsqueda indexada de borradores debe usar un mecanismo administrativo separado; no reutilizar el índice público.
9. La optimización final de imágenes, lightbox y prioridades de carga frontend corresponde a Fase 4/5.

## Producción

`propiedadesmartinez.cl` no ha sido modificado. Cualquier despliegue o migración productiva requiere una solicitud explícita posterior y su propia evidencia.

## Regla de actualización

Un ítem solo pasa a `DONE` con PR, tests/evidencia o documento que lo sustente. Una decisión aceptada no cambia silenciosamente: requiere ADR/PR.

## Auditoría

Para auditoría completa usar `AUDIT-TRACEABILITY.md`. Para revisión rápida comenzar aquí y continuar por Decision Register, fases, PRs, catálogo de tests y evidencias.
