# Evidencia — PR 3.5 Runner reanudable de batches

Estado: `QA_PASSED / READY_TO_MERGE`.

Issue: #54  
PR: #55  
Rama: `feat/phase3-batch-runner`  
Head funcional validado: `e54dd110d3f65cb18383623e571e9344700607e6`

## Objetivo

Implementar el primer orquestador real de batches confirmados sobre los contratos de persistencia, identidad, dry-run y ejecución idempotente de Fase 3.2–3.4.

## Contratos implementados

### `MappingProfileCodec`

El snapshot de mapping persistido en `wla_import_batches.profile_json` queda formalizado con un contrato versionado y estricto:

- `version`;
- `source_key`;
- `name`;
- `empty_policy`;
- `mapping`;
- `separators`.

El codec usa JSON estricto con excepción ante errores y reconstruye un `MappingProfile` real antes de ejecutar. El `source_key` reconstruido debe coincidir además con el `source_key` del batch.

Un `source_key` estructuralmente inválido dentro de un snapshot persistido se convierte en `MappingException('invalid_profile_source_key')`, de modo que el runner pueda fallar el batch de forma controlada en vez de dejar una excepción sin capturar.

### `BatchRunner`

`BatchRunner::run()` recibe:

- UUID del batch;
- ruta local del origen previamente confirmado;
- máximo de filas de la iteración;
- presupuesto máximo de tiempo.

El runner:

1. carga el batch persistido;
2. acepta `confirmed`, `paused`, `failed` o un `processing` ya reclamado;
3. reclama `processing` con optimistic locking cuando corresponde;
4. valida `cursor_row`, `cursor_offset`, total y contadores;
5. abre el CSV y mantiene `LOCK_SH` durante verificación y lectura;
6. calcula SHA-256 sobre el mismo handle que será consumido;
7. exige coincidencia exacta con `source_hash`;
8. reconstruye el mapping persistido;
9. reanuda desde el byte exacto de `cursor_offset`;
10. vuelve a mapear y validar la fila actual;
11. vuelve a resolver taxonomías en modo read-only;
12. delega la escritura a `RowExecutor`;
13. confirma la fila con `BatchCheckpoint`;
14. solo entonces avanza cursor, offset, revision y contadores;
15. pausa limpiamente al alcanzar presupuesto;
16. completa únicamente cuando cursor y total coinciden.

### Revalidación por fila

La ejecución usa un `DryRunEngine` de una sola fila justo antes de escribir. Esto no reemplaza el dry-run global obligatorio: el batch solo llega a `confirmed` después de esa validación previa.

La estrategia es segura porque:

- el archivo debe mantener exactamente el mismo SHA-256 que fue confirmado;
- SHA y lectura se hacen sobre el mismo `SplFileObject` bloqueado;
- los duplicados internos del archivo ya fueron detectados en el dry-run global;
- la fila se vuelve a normalizar y validar para detectar cambios externos de taxonomías/identidad;
- `RowExecutor` vuelve a resolver identidad antes de escribir;
- las restricciones UNIQUE de identidad permanecen como barrera concurrente final.

### Presupuesto y reanudación

Por defecto una iteración procesa como máximo 25 filas o aproximadamente 5 segundos. Al alcanzar el presupuesto:

- el batch pasa `processing -> paused`;
- las filas ya checkpointed permanecen confirmadas;
- el checkpoint persiste `cursor_row` y `cursor_offset`;
- la siguiente ejecución realiza `paused -> processing`;
- el lector abre/verifica el origen y salta directamente al byte persistido;
- ninguna fila ya confirmada necesita reparsearse ni ejecutarse nuevamente.

No se introduce Action Scheduler ni WP-Cron como dependencia en este PR. Esta fase construye el runner sin imponer todavía el mecanismo que lo invoque.

### Concurrencia

Dos workers pueden alcanzar la misma fila antes del checkpoint. La seguridad se apoya en dos capas:

- escritura idempotente por identidad en `RowExecutor`/`wla_import_identity`;
- optimistic locking de `BatchCheckpoint` por `revision`.

Solo un checkpoint puede ganar. El worker con revisión obsoleta devuelve `checkpoint_conflict` y no fuerza el estado del batch.

### Fallos

Un error de validación o ejecución:

- no incrementa `processed_rows`;
- no mueve `cursor_row` ni `cursor_offset`;
- no se marca como procesado;
- transiciona el batch a `failed` cuando conserva la revisión esperada;
- puede volver luego `failed -> processing` para una reanudación controlada.

Un conflicto de revisión no se transforma artificialmente en `failed`, porque otro worker puede haber avanzado correctamente el batch.

### Privacidad de resultados y hooks

`BatchRunResult` expone solo:

- estado operacional;
- filas procesadas en la iteración;
- cursor;
- revisión;
- razón estable;
- número de fila opcional;
- códigos sanitizados de warning/error.

No expone valores de la propiedad, ruta del archivo, notas internas o direcciones privadas.

Hooks iniciales:

- `wla_inmo_import_batch_run_started`;
- `wla_inmo_import_batch_paused`;
- `wla_inmo_import_batch_failed`;
- `wla_inmo_import_batch_completed`.

## Integridad del origen

La validación SHA-256 y la lectura ocurren sobre el mismo handle bajo `LOCK_SH`. La integración reemplaza deliberadamente el pathname después de abrir/verificar el archivo y comprueba que las filas posteriores siguen viniendo del handle original validado.

Si el contenido del origen confirmado cambia:

- la ejecución se rechaza;
- el cursor no avanza;
- no se crea ni actualiza una propiedad con bytes no verificados.

## Findings de review corregidos

Durante review se detectaron y cerraron tres P2:

1. `source_key` inválido podía escapar como `InvalidArgumentException`; ahora se normaliza a `MappingException` controlada;
2. reanudar por fila obligaba a reparsear el prefijo ya confirmado; ahora se persiste `cursor_offset` y se reanuda por byte;
3. SHA-256 y lectura abrían el pathname por separado; ahora ambos usan el mismo handle bloqueado.

Review threads abiertos al cierre: **0**.  
P0/P1 abiertos al cierre: **0**.

## Tests

### Unit

`tests/unit/ImportBatchRunnerContractsTest.php` y regresiones relacionadas cubren:

- round-trip del mapping profile persistido;
- Unicode y separadores;
- JSON inválido;
- tipos inválidos del snapshot;
- `source_key` inválido convertido a fallo controlado;
- contrato seguro de `BatchRunResult`;
- cursor físico de reanudación.

### Smoke

`tests/smoke/import-batch-runner.php` congela contratos de:

- SHA-256;
- reconstrucción del profile;
- checkpoint;
- pause/completed;
- optimistic conflict;
- hooks seguros;
- ausencia de payload/source path en resultados.

### WordPress/MySQL

`tests/integration/assert-import-batch-runner.php` valida:

- batch CSV de tres filas ejecutado en slices;
- `paused -> processing` en reanudaciones;
- persistencia de `cursor_offset`;
- finalización y contadores;
- tres identidades únicas;
- propiedades creadas como draft;
- drafts fuera del índice público y presentes en quality projection;
- replay de batch completed como no-op;
- simulación de crash después de persistir y antes de checkpoint: retry como UPDATE sin duplicado;
- rechazo por SHA-256 modificado sin avance de cursor;
- fila con taxonomía inválida detenida sin avance ni escritura;
- lectura desde el handle verificado aunque el pathname sea reemplazado durante la iteración.

Workflow dedicado `Import Batch Runner Integration` en:

- WordPress 6.6.2 / PHP 8.1;
- WordPress latest / PHP 8.3.

## QA final

Sobre el head funcional `e54dd110d3f65cb18383623e571e9344700607e6`:

- Phase 1 CI `33914928859`: **SUCCESS**;
- Import Batch Runner Integration `33914928723`: **SUCCESS** en ambas matrices;
- Import Persistence Integration `33914928741`: **SUCCESS**;
- Import Row Executor Integration `33914928695`: **SUCCESS**;
- Administration Quality Gate `33914928897`: **SUCCESS**;
- Bootstrap Smoke `33914928694`: **SUCCESS**;
- Catalogue Quality Integration `33914928756`: **SUCCESS**;
- Dashboard Integration `33914928730`: **SUCCESS**;
- Activity Integration `33914928743`: **SUCCESS**;
- Settings UI Integration `33914928762`: **SUCCESS**;
- Help Center Integration `33914928739`: **SUCCESS**;
- PHP syntax: **SUCCESS**;
- WPCS: **SUCCESS**;
- PHPStan: **SUCCESS**;
- PHPUnit: **50 tests / 275 assertions — SUCCESS**;
- source smoke: **SUCCESS**;
- release ZIP smoke: **SUCCESS**;
- WordPress 6.6.2 / PHP 8.1: **SUCCESS**;
- WordPress latest / PHP 8.3: **SUCCESS**.

El Administration Quality Gate había revelado un flake de login de Playwright que variaba entre viewports en reintentos consecutivos. Se endureció el helper para distinguir errores reales de autenticación de un redirect transitorio y permitir un único reintento controlado. El gate final sobre el head validado quedó verde.

### Artefacto

- artifact: `9952796445`;
- digest artifact: `sha256:5e67ae4dff17370c9779d39ed0f0ecf2503713660073cdf67156b7c811058c0f`;
- ZIP instalable SHA-256: `34c9f01a6b5accf8f29eb3ac2c0d971954c5375a603be83e1d4877d35485d72c`.

Los warnings de deprecación Node provienen de GitHub Actions/acciones de terceros y no del runtime PHP del plugin.

## Performance

El origen se procesa incrementalmente con `SplFileObject`. La reanudación por byte evita costo creciente por relectura del prefijo confirmado. El runner conserva un presupuesto explícito por filas y tiempo, y no carga el CSV completo en memoria.

Los benchmarks administrativos heredados de 100/1.000/5.000 propiedades también pasaron en el gate de regresión. Son referencias sintéticas de CI, no SLA productivos.

## Fuera de alcance

- UI final del wizard;
- XLSX y JSON como formatos de entrada reales;
- media remota/sideload;
- rollback completo de un batch;
- mecanismo definitivo WP-Cron/background;
- migración WooCommerce/ACF;
- cambios en `propiedadesmartinez.cl`.

## Criterio de merge

Cumplido:

- CI sobre el head funcional exacto verde;
- workflow dedicado verde en ambas matrices;
- regressions administrativas verdes;
- review threads abiertos: 0;
- P0/P1 abiertos: 0;
- artefacto y checksum registrados.

Estado autorizado: `QA_PASSED / READY_TO_MERGE`.

## Producción

`propiedadesmartinez.cl` permanece sin cambios durante esta fase.
