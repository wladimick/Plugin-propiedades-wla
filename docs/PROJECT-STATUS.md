# Estado del proyecto

Este documento es el registro vivo para auditorías rápidas. Debe actualizarse cuando una fase cambie de estado o cuando se cierre un hito relevante. La evidencia detallada de cada PR permanece en `docs/evidence/`.

## Estado general

- Proyecto: WLA Inmo
- Tema de referencia opcional: WLA Inmo Light
- Etapa actual: `PHASE-3 / IMPORT-EXPORT`
- Fase 0: `DONE`
- Fase 1: `DONE`
- Fase 2: `DONE`
- Fase 3: `IN_PROGRESS`
- Código de producto: `0.1.0-alpha`
- Producción: no afectada
- Decisiones críticas: D01–D75 `ACCEPTED`
- Registro: `docs/decisions/DECISION-REGISTER.md`
- PR 1.1–1.8: `DONE`
- PR 2.1–2.10: `DONE`
- PR 3.1–3.7: `DONE`
- PR 3.8: `QA_PENDING` — PR #62 / Issue #61
- Próximo alcance después de 3.8: **PR 3.9 — Media remota segura**

## Fases

| Fase | Nombre | Estado | Evidencia principal |
|---|---|---|---|
| 0 | Gobierno y diseño | DONE | `/docs`, PR #1, ADR-001–ADR-014 |
| 1 | Core del plugin | DONE | PR #5/#8/#10/#12/#14/#16/#18/#20, `docs/evidence/phase-1/` |
| 2 | Administración | DONE | PR #24/#26/#28/#30/#32/#34/#36/#38/#40/#42, `docs/evidence/phase-2/` |
| 3 | Import/Export | IN_PROGRESS | PR #46/#49/#51/#53/#55/#57/#60/#62, `docs/PHASE-3-BACKLOG.md`, `docs/evidence/phase-3/` |
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

ADR-014 concreta D31 para XLSX sin reemplazarla: PhpSpreadsheet 3.10.7 exacta, encapsulada en Import/Export y lectura bounded.

## Fase 1 — Core del plugin

Estado: `DONE`.

- PR #5 — Bootstrap/build.
- PR #8 — Entidad Property.
- PR #10 — Taxonomías.
- PR #12 — Meta schema/validación.
- PR #14 — Índice/sincronización.
- PR #16 — Roles/capabilities.
- PR #18 — Settings/contrato con temas.
- PR #20 — Quality Gate/release `0.1.0-alpha`.
- CI final de Fase 1: SUCCESS.

Evidencia completa: `docs/evidence/phase-1/`.

## Fase 2 — Administración

Estado: `DONE`.

Backlog: `docs/PHASE-2-BACKLOG.md`.

| PR | Alcance | GitHub | Estado |
|---|---|---|---|
| 2.1 | Admin shell y navegación | #24 | DONE |
| 2.2 | Listado profesional | #26 | DONE |
| 2.3 | Editor guiado | #28 | DONE |
| 2.4 | Multimedia y galería | #30 | DONE |
| 2.5 | Calidad del catálogo | #32 | DONE |
| 2.6 | Centro de Ayuda y onboarding | #34 | DONE |
| 2.7 | Ajustes UI | #36 | DONE |
| 2.8 | Actividad / historial base | #38 | DONE |
| 2.9 | Dashboard / Resumen operativo | #40 | DONE |
| 2.10 | Quality Gate de Administración | #42 | DONE |

### Cierre de Fase 2

PR #42 validó el conjunto administrativo completo:

- Administration Quality Gate: SUCCESS;
- Playwright: 8/8 SUCCESS, retries=0;
- WordPress 6.6.2/PHP 8.1 y WordPress latest/PHP 8.3: SUCCESS;
- autorización positiva/negativa, nonce e IDOR: cubiertos;
- responsive 360/390/768/1024/1440 en pantallas prioritarias;
- axe sin findings serious/critical en UI propia cubierta;
- performance sintético estable para catálogo hasta 5k;
- artifacts/checksums registrados en `docs/evidence/phase-2/PR-2.10-ADMIN-QUALITY-GATE.md`.

## Fase 3 — Import/Export

Estado: `IN_PROGRESS`.

Backlog canónico: `docs/PHASE-3-BACKLOG.md`.  
Contrato funcional: `docs/IMPORT-EXPORT.md`.  
Evidencia: `docs/evidence/phase-3/`.

La numeración original fue refinada durante implementación. Persistencia, executor y runner se separaron antes de exponer UI; Issue #56 formalizó que la UI pasara a 3.6 y que los hitos restantes se renumeraran sin cambiar su alcance.

| PR | Alcance | GitHub | Estado | Evidencia |
|---|---|---|---|---|
| 3.1 | Import domain / CSV foundation | #46 | DONE | `PR-3.1-IMPORT-DOMAIN-CSV.md` |
| 3.2 | Mapping + validation + dry-run | #49 | DONE | `docs/evidence/phase-3/` |
| 3.3 | Persistencia de identidad y batches | #51 | DONE | `docs/evidence/phase-3/` |
| 3.4 | Executor idempotente de filas | #53 | DONE | `PR-3.4-ROW-EXECUTOR.md` |
| 3.5 | Runner reanudable de batches | #55 | DONE | `PR-3.5-BATCH-RUNNER.md` |
| 3.6 | UI Importar + historial | #57 | DONE | `PR-3.6-IMPORT-UI.md` |
| 3.7 | JSON WLA versionado | #60 / #58 | DONE | `PR-3.7-JSON-WLA.md` |
| 3.8 | XLSX streaming + ADR/benchmark | #62 / #61 | QA_PENDING | `PR-3.8-XLSX.md` |
| 3.9 | Media remota segura | pendiente | NEXT | pendiente |
| 3.10 | Exportación CSV/XLSX | pendiente | PLANNED | pendiente |
| 3.11 | Rollback seguro | pendiente | PLANNED | pendiente |
| 3.12 | Quality Gate Fase 3 | pendiente | PLANNED | pendiente |

### PR 3.1 — Import domain / CSV foundation

Estado: `DONE`. PR #46.

- dominio `WLA\Inmo\Import`;
- state machine de batches;
- `source_key` normalizado;
- resolución read-only de identidad;
- CSV incremental UTF-8 con límites;
- delimitadores soportados y headers duplicados rechazados;
- CI/integración WordPress: SUCCESS.

### PR 3.2 — Mapping + validation + dry-run

Estado: `DONE`. PR #49.

- `TargetRegistry` allowlisted;
- `MappingProfile` versionado;
- normalización/validación tipada;
- dry-run read-only;
- duplicados intra-file e identity conflicts;
- taxonomías desconocidas sin creación automática;
- serialización pública sin meta privada.

### PR 3.3 — Persistencia de identidad y batches

Estado: `DONE`. PR #51.

- proyección `wla_import_identity` con constraints UNIQUE;
- `IdentityRepository` / `IdentityIndexer`;
- `wla_import_batches` con UUID, hash, profile snapshot, estado, cursor, contadores, timestamps y revision;
- optimistic locking;
- WordPress post/meta permanece como fuente canónica.

### PR 3.4 — Executor idempotente de filas

Estado: `DONE`. PR #53.

- `RowExecutor` re-resuelve identidad antes de escribir;
- create/update inequívoco;
- retry idempotente;
- sanitización canónica;
- rollback local de escrituras parciales;
- checkpoint solo tras éxito.

### PR 3.5 — Runner reanudable de batches

Estado: `DONE`. PR #55.

- `BatchRunner` por slices;
- SHA-256 y lock sobre source;
- resume por `cursor_row`, `cursor_offset` y revision;
- optimistic locking por checkpoint;
- pausa limpia por presupuesto;
- WordPress/MySQL integration.

### PR 3.6 — UI Importar + historial

Estado: `DONE`. PR #57 / Issue #56.

Squash en `main`: `d983034bb40a369eaf9bebaeef977548ca752e54`.

- wizard CSV completo Subir → Mapear → Validar → Simular → Confirmar → Procesar → Informe;
- capability + nonces;
- workspace temporal server-controlled;
- 10 MiB / 10.000 filas;
- preview bounded;
- dry-run obligatorio;
- processing por `BatchRunner`;
- historial paginado;
- janitor de drafts;
- CI final completamente verde.

Evidencia: `docs/evidence/phase-3/PR-3.6-IMPORT-UI.md`.

### PR 3.7 — JSON WLA versionado

Estado: `DONE`. PR #60 / Issue #58.

Head funcional validado: `0eaeda0f02da44ae25018b6ba167b8b1dddda5a4`.

- `format_version = 1` documentado y validado;
- fixture v1 versionado UTF-8;
- shape y targets allowlisted;
- límites de bytes, profundidad, propiedades y línea normalizada;
- JSON → NDJSON privado 0600 generado por servidor;
- SHA-256, lock y resume por offset;
- `source_format=json` persistido con schema DB v3 y compatibilidad CSV;
- mismo `DryRunEngine`, identidad, `BatchRunner` y `RowExecutor` que CSV;
- UI JSON con capability/nonce;
- export JSON bounded y privados excluidos por defecto;
- round-trip export → import → dry-run;
- negativos de versión/root/límites/tampering/capability/nonce;
- 3 findings de review corregidos y threads resueltos;
- 13/13 workflows del head funcional: SUCCESS;
- benchmark 100/1k/5k documentado; 5k <1s en ambas matrices y peak delta observado 8 MiB;
- artifacts con SHA-256 registrados en evidencia.

Evidencia: `docs/evidence/phase-3/PR-3.7-JSON-WLA.md`.

### PR 3.8 — XLSX streaming + ADR/benchmark

Estado: `QA_PENDING`. PR #62 / Issue #61.

- ADR-014 `ACCEPTED / IMPLEMENTS D31`;
- PhpSpreadsheet 3.10.7 exacta y `composer.lock` versionado;
- benchmark reproducible OpenSpout 4.24.5 vs PhpSpreadsheet 3.10.7/5.8.1;
- preflight ZIP/OOXML bounded;
- protección Zip Slip/path traversal, archive expansion, macros/binarios y relationships externos;
- límites de archivo, entries, sheets, rows, columns y cell bytes;
- selección explícita de hoja antes de normalizar;
- XLSX → NDJSON privado con permisos fail-closed;
- janitor de uploads XLSX abandonados;
- `source_format=xlsx` en batch e historial;
- pipeline compartido con CSV/JSON para mapping, dry-run, identidad, runner y executor;
- UI XLSX integrada y historial filtrado por formato;
- PHPUnit XLSX, PHPStan, build/smoke y CI PHP 8.1/8.3;
- integración WordPress para handler de hoja, cleanup e historial.

Evidencia: `docs/evidence/phase-3/PR-3.8-XLSX.md`.

Al obtener CI final verde, este hito pasa a `READY_TO_MERGE`, se registra artifact/checksum final y PR #62 se mergea por squash.

## Findings / deuda no bloqueante conocida

No existen findings críticos o altos abiertos conocidos dentro de Fase 1, Fase 2 y PR 3.1–3.7 cerrados.

Para 3.8 no hay review threads abiertos conocidos; el estado final depende del último head de QA.

Deuda de prioridad baja heredada:

- PHPStan debe expandir cobertura progresivamente fuera de los dominios actualmente gated;
- PHP 8.1 deberá reevaluarse antes de Beta/1.0 por la ventana de soporte de dependencias;
- performance sintético de CI no constituye SLA productivo.

## Regla de producción

`propiedadesmartinez.cl` permanece sin cambios hasta la Fase 9 o una solicitud explícita posterior.
