# Evidencia — PR 3.6 UI de importación e historial

Estado: `DONE`.

Issue: #56 — CLOSED  
PR: #57 — MERGED  
Rama funcional: `feat/phase3-import-ui`  
Head funcional validado: `5d2c26d2fdd6d4c820865c2bff6f2eb9ff37c9e9`  
Head final previo al merge: `43da37612dee405d1675d7c80b2e5ed1e059423d`  
Squash en `main`: `d983034bb40a369eaf9bebaeef977548ca752e54`

## Objetivo

Exponer el pipeline canónico de Fase 3.1–3.5 mediante una UI administrativa usable por una persona no técnica, manteniendo dry-run obligatorio, identidad explícita, checkpoints, reanudación e idempotencia.

## Implementación validada

- `WLA Inmo → Importar / Exportar` deja de depender del placeholder y enruta a `ImportExportPage`;
- wizard server-rendered: Subir → Mapear → Validar → Simular → Confirmar → Procesar → Informe;
- capability exacta `import_wla_properties`;
- mutaciones por `admin-post.php` con nonce específico;
- CSV como único formato de entrada de este PR;
- `Workspace` temporal mediante `get_temp_dir()`;
- nombre de ruta generado por UUID de servidor; el nombre original nunca forma parte del path;
- límite de 10 MiB y 10.000 filas para esta etapa;
- extensión + MIME + parser CSV UTF-8;
- preview de máximo 5 filas, renderizado de forma acotada y sin persistir payload de filas en transients;
- mapping solo contra `TargetRegistry`;
- `DryRunEngine` obligatorio antes de confirmar;
- snapshot de mapping mediante `MappingProfileCodec`;
- SHA-256 revisado nuevamente antes de confirmar;
- batch confirmado a través del state machine ya persistido;
- procesamiento únicamente mediante `BatchRunner` en slices de 25 filas / ~4 segundos;
- reanudación desde `cursor_offset` implementado en PR 3.5;
- cancelación solo desde checkpoints seguros (`confirmed`, `paused` o `failed`);
- historial bounded/paginado mediante `BatchHistoryRepository`;
- usuario importador ve sus batches; usuarios con `manage_wla_inmo_tools` pueden ver todos;
- CSS propio cargado solo en la pantalla de importación;
- ayuda contextual específica;
- limpieza programada de drafts temporales vencidos mediante `WorkspaceJanitor`, sin borrar por edad archivos de batches reanudables.

## Privacidad / seguridad

La UI no recibe paths de filesystem desde request. El request solo transporta tokens/UUIDs generados por servidor y el servidor reconstruye las rutas temporales tras validarlos.

El historial no consulta ni renderiza:

- `profile_json`;
- `source_hash`;
- payload de filas;
- direcciones privadas;
- notas internas;
- tokens o credenciales.

El dry-run persistido en transient conserva únicamente conteos y una muestra acotada de códigos/targets de hallazgos; no conserva valores de las filas.

No se crean términos desconocidos automáticamente, no se descargan archivos remotos y no existe procesamiento monolítico del CSV completo en una sola solicitud.

## Findings de review corregidos

Durante review se detectaron dos findings P2 y ambos quedaron corregidos y resueltos:

1. **Colección no acotada de issues durante dry-run.** Se mantiene un contador total independiente, pero en memoria se conservan como máximo `ISSUE_LIMIT` hallazgos.
2. **Drafts temporales huérfanos.** `WorkspaceJanitor` limpia únicamente archivos draft vencidos con ejecución bounded; los archivos de batches confirmados/pausados/fallidos no se eliminan por edad para preservar resume.

Review threads abiertos al merge: **0**.  
P0/P1 abiertos conocidos: **0**.

## QA funcional

Sobre `5d2c26d2fdd6d4c820865c2bff6f2eb9ff37c9e9` quedaron verdes:

- Phase 1 CI `34046249484`: **SUCCESS**;
- Import UI Integration `34046249476`: **SUCCESS**;
- Import Batch Runner Integration `34046249469`: **SUCCESS**;
- Import Persistence Integration `34046249467`: **SUCCESS**;
- Import Row Executor Integration `34046249516`: **SUCCESS**;
- Administration Quality Gate `34046249499`: **SUCCESS**;
- Bootstrap Smoke: **SUCCESS**;
- Catalogue Quality Integration: **SUCCESS**;
- Activity Integration: **SUCCESS**;
- Dashboard Integration: **SUCCESS**;
- Settings UI Integration: **SUCCESS**;
- Help Center Integration: **SUCCESS**.

### Rerun final del head de merge

Después de los commits exclusivamente documentales, el head final `43da37612dee405d1675d7c80b2e5ed1e059423d` volvió a ejecutar toda la regresión y quedó verde:

- Phase 1 CI `34129573714`: **SUCCESS**;
- Import UI Integration `34129573608`: **SUCCESS**;
- Import Batch Runner Integration `34129573557`: **SUCCESS**;
- Import Persistence Integration `34129573803`: **SUCCESS**;
- Import Row Executor Integration `34129573761`: **SUCCESS**;
- Administration Quality Gate `34129573601`: **SUCCESS**;
- Bootstrap Smoke `34129573638`: **SUCCESS**;
- Catalogue Quality Integration `34129573619`: **SUCCESS**;
- Activity Integration `34129573762`: **SUCCESS**;
- Dashboard Integration `34129573645`: **SUCCESS**;
- Settings UI Integration `34129573674`: **SUCCESS**;
- Help Center Integration `34129573585`: **SUCCESS**.

## Matriz WordPress de Import UI

- WordPress 6.6.2 / PHP 8.1: **SUCCESS**;
- WordPress latest / PHP 8.3: **SUCCESS**;
- build e instalación del ZIP real: **SUCCESS**;
- contratos de UI e historial bounded: **SUCCESS**.

## E2E / administración

Administration Quality Gate ejecutó correctamente:

- instalación del plugin desde build instalable;
- roles, nonce y autorización por objeto;
- benchmark de catálogo 100 / 1.000 / 5.000;
- Playwright administration quality gate;
- evidencia E2E.

## Artifacts funcionales

Plugin QA:

- artifact `9993201437`;
- nombre `wla-inmo-0.1.0-alpha-quality`;
- digest `sha256:fd90493ab1905ecd31f8a1a692a1c38827f81e4c5cf0ebd361ed2f71b038f15d`.

E2E:

- artifact `9993217737`;
- nombre `wla-inmo-admin-e2e-evidence`;
- digest `sha256:3e51dffaeb116a7f9add0f9cf0294fc48271a32b73a98da0b622d01cfaf8898f`.

## Criterios de aceptación

- flujo CSV administrativo implementado sobre el pipeline canónico: **PASS**;
- dry-run obligatorio antes de confirmar: **PASS**;
- archivo/hash y mapping snapshot verificados antes de ejecutar: **PASS**;
- resume mediante batch persistido y `cursor_offset`: **PASS**;
- procesamiento por slices pequeños: **PASS**;
- capability + nonce en mutaciones: **PASS**;
- paths arbitrarios desde request: **BLOQUEADOS**;
- historial paginado/bounded y sin payload privado: **PASS**;
- findings P0/P1 abiertos: **0**;
- regresiones Fase 1/2 y pipeline Fase 3.1–3.5: **PASS**.

## Fuera de alcance

- JSON WLA — PR 3.7 / Issue #58;
- XLSX — PR 3.8;
- media remota — PR 3.9;
- exportación CSV/XLSX — PR 3.10;
- rollback completo — PR 3.11;
- cola/background definitiva;
- migración WooCommerce/ACF.

## Producción

`propiedadesmartinez.cl` permanece sin cambios.

## Cierre

**DONE**. PR #57 fue fusionado por squash en `main` como `d983034bb40a369eaf9bebaeef977548ca752e54`. El siguiente hito es **PR 3.7 — JSON WLA versionado**, Issue #58.
