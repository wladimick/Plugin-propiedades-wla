# Evidencia — PR 3.5 Runner reanudable de batches

Estado: `IN_PROGRESS / QA_PENDING`.

Issue: #54  
Rama: `feat/phase3-batch-runner`

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
4. valida cursor/total;
5. recalcula SHA-256 del origen y exige coincidencia exacta con `source_hash`;
6. reconstruye el mapping persistido;
7. lee CSV incrementalmente;
8. omite únicamente las posiciones lógicas ya confirmadas por `cursor_row`;
9. vuelve a mapear y validar la fila actual;
10. vuelve a resolver taxonomías en modo read-only;
11. delega la escritura a `RowExecutor`;
12. confirma la fila con `BatchCheckpoint`;
13. solo entonces avanza cursor/revision/contadores;
14. pausa limpiamente al alcanzar presupuesto;
15. completa únicamente cuando cursor y total coinciden.

### Revalidación por fila

La ejecución usa un `DryRunEngine` de una sola fila justo antes de escribir. Esto no reemplaza el dry-run global obligatorio: el batch solo llega a `confirmed` después de esa validación previa.

La estrategia es segura porque:

- el archivo debe mantener exactamente el mismo SHA-256 que fue confirmado;
- los duplicados internos del archivo ya fueron detectados en el dry-run global;
- la fila se vuelve a normalizar y validar para detectar cambios externos de taxonomías/identidad;
- `RowExecutor` vuelve a resolver identidad antes de escribir;
- las restricciones UNIQUE de identidad permanecen como barrera concurrente final.

### Presupuesto y reanudación

Por defecto una iteración procesa como máximo 25 filas o aproximadamente 5 segundos. Al alcanzar el presupuesto:

- el batch pasa `processing -> paused`;
- las filas ya checkpointed permanecen confirmadas;
- la siguiente ejecución realiza `paused -> processing`;
- el lector vuelve a abrir el origen pero descarta posiciones lógicas `<= cursor_row`;
- ninguna fila ya confirmada se ejecuta nuevamente.

No se introduce Action Scheduler ni WP-Cron como dependencia en este PR. Esta fase construye el runner sin imponer todavía el mecanismo que lo invoque.

### Concurrencia

Dos workers pueden alcanzar la misma fila antes del checkpoint. La seguridad se apoya en dos capas:

- escritura idempotente por identidad en `RowExecutor`/`wla_import_identity`;
- optimistic locking de `BatchCheckpoint` por `revision`.

Solo un checkpoint puede ganar. El worker con revisión obsoleta devuelve `checkpoint_conflict` y no fuerza el estado del batch.

### Fallos

Un error de validación o ejecución:

- no incrementa `processed_rows`;
- no mueve `cursor_row`;
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

El SHA-256 persistido es obligatorio para cada reanudación. Si el archivo cambia un solo byte después de ser confirmado:

- la ejecución se rechaza antes de leer una fila de negocio;
- el cursor no avanza;
- no se crea ni actualiza una propiedad.

## Tests agregados

### Unit

`tests/unit/ImportBatchRunnerContractsTest.php`:

- round-trip del mapping profile persistido;
- Unicode y separadores;
- JSON inválido;
- tipos inválidos del snapshot;
- contrato seguro de `BatchRunResult`.

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

- batch CSV de tres filas ejecutado en tres slices;
- `paused -> processing` en reanudaciones;
- finalización y contadores;
- tres identidades únicas;
- propiedades creadas como draft;
- drafts fuera del índice público y presentes en quality projection;
- replay de batch completed como no-op;
- simulación de crash después de persistir y antes de checkpoint: retry como UPDATE sin duplicado;
- rechazo por SHA-256 modificado sin avance de cursor;
- fila con taxonomía inválida detenida sin avance ni escritura.

Workflow dedicado:

- `Import Batch Runner Integration`;
- WordPress 6.6.2 / PHP 8.1;
- WordPress latest / PHP 8.3.

## Performance

El origen se lee con `CsvReader`/`SplFileObject`, por lo que el payload completo no se carga en memoria. La memoria de ejecución permanece acotada por fila más estructuras de control. Las pruebas de escala 100/1.000/5.000 se incorporarán al gate de esta fase antes del cierre si el CI revela que necesitan un job separado.

## Fuera de alcance

- UI final del wizard;
- XLSX y JSON como formatos de entrada reales;
- media remota/sideload;
- rollback completo de un batch;
- mecanismo definitivo WP-Cron/background;
- migración WooCommerce/ACF;
- cambios en `propiedadesmartinez.cl`.

## QA pendiente

- PHP syntax;
- WPCS;
- PHPStan;
- PHPUnit;
- source smoke;
- build/ZIP smoke;
- integración Batch Runner en ambas matrices;
- regresiones previas de importación/admin;
- revisión sin P0/P1 abiertos;
- evidencia final `QA_PASSED / READY_TO_MERGE`.

## Producción

`propiedadesmartinez.cl` permanece sin cambios durante esta fase.
