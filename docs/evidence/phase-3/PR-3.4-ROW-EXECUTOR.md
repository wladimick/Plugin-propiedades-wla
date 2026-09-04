# Evidencia — PR 3.4 Executor idempotente de filas

Estado: `QA_PASSED / READY_TO_MERGE`.

Issue: #52  
Rama: `feat/phase3-row-executor`

## Objetivo

Convertir el dry-run validado de Fase 3.2 y la identidad/batch persistentes de Fase 3.3 en una primera ejecución real de filas, manteniendo WordPress post/meta como fuente canónica y evitando duplicados ante retries, carreras o crashes antes del checkpoint.

## Contratos implementados

### `RowExecutor`

El executor:

- recibe exclusivamente un `DryRunResult` ya validado;
- vuelve a resolver identidad inmediatamente antes de escribir;
- exige al menos `source_key + external_id` o `property_code`;
- rechaza conflictos antes de mutar;
- impide que un UPDATE stale termine escribiendo otra propiedad;
- permite que un dry-run NEW pase a MATCH y se ejecute como UPDATE, que es la ruta idempotente de retry/concurrencia;
- exige título para una creación real;
- devuelve `RowExecutionResult` estructurado con códigos estables;
- no entrega el payload canónico/private data en los hooks de ejecución.

Hooks públicos iniciales:

- `wla_inmo_import_before_row_execute`;
- `wla_inmo_import_after_row_execute`.

Los argumentos del hook previo son número de fila, acción y property ID opcional. El hook posterior recibe el objeto de resultado, no la fila completa.

### `WordPressPropertyWriter`

El writer real:

- crea propiedades nuevas como `draft`;
- actualiza únicamente `wla_property` existentes;
- prepara y valida todo el payload antes de mutar;
- reutiliza los sanitizadores canónicos publicados por `TargetRegistry` desde `MetaSchema`;
- no permite targets desconocidos;
- no crea taxonomías durante ejecución: exige IDs ya resueltos por dry-run y confirma que sigan existiendo;
- trata `null`/array vacío como intención explícita de clear;
- conserva valores omitidos por `EMPTY_PRESERVE` porque esos targets no llegan al payload de ejecución;
- mantiene `_wla_inmo_external_source_key` junto con `external_id`;
- valida la proyección de identidad después de escribir;
- sincroniza search index y quality projection antes de declarar éxito.

### Rollback local

Para una creación que falla después de `wp_insert_post`, el writer elimina la propiedad recién creada y exige que la limpieza de la proyección de identidad también tenga éxito. Si cualquiera de esas operaciones falla, devuelve `rollback_failed`.

Para un update, se toma snapshot de:

- título/contenido/excerpt;
- cada meta que será tocado;
- `external_source_key` cuando corresponde;
- términos de cada taxonomía que será tocada.

Ante una falla parcial se restaura ese snapshot y se vuelven a sincronizar las proyecciones. Cada restauración de metadata se lee de vuelta y se valida contra su estado anterior; una restauración incompleta se eleva como `rollback_failed` y nunca se presenta como fila procesada.

## Idempotencia

La identidad se vuelve a resolver en tiempo de ejecución. Por ello, el escenario crítico queda así:

1. dry-run clasifica fila como NEW;
2. worker crea la propiedad;
3. proceso cae antes de checkpoint;
4. el mismo batch reintenta la fila;
5. el executor vuelve a resolver identidad y encuentra la propiedad creada;
6. ejecuta UPDATE sobre el mismo property ID;
7. no crea un segundo post.

La restricción UNIQUE de `wla_import_identity` sigue siendo la última barrera concurrente.

## Batch checkpoint

`BatchCheckpoint` confirma una sola fila únicamente cuando el `RowExecutionResult` es `created`, `updated` o `skipped`.

Un resultado `error`:

- no avanza `cursor_row`;
- no aumenta `processed_rows`;
- no cambia `revision`;
- queda reintentable.

El checkpoint usa `BatchRepository::advanceProgress()`, por lo que conserva optimistic locking y contadores monotónicos de Fase 3.3.

## Dry-run alineado

Fase 3.4 endurece también `DryRunEngine`: una fila sin `external_id` ni `property_code` recibe `missing_identity`. Esto evita mostrar como NEW una fila que el executor necesariamente rechazaría por no tener identidad idempotente.

## Seguridad

- no se ejecuta HTML/iframe/código del archivo;
- post content/excerpt pasan por sanitización de texto/textarea del contrato actual;
- meta usa sanitizadores de `MetaSchema`;
- taxonomías deben haber sido resueltas read-only antes de ejecución;
- el executor no confía en el property ID del dry-run sin revalidar identidad;
- no se incluyen direcciones privadas, notas internas ni payloads completos en hooks de auditoría;
- no hay media remota en este PR;
- no hay SQL nuevo construido desde input del usuario.

## Tests agregados

### Unit

`tests/unit/ImportRowExecutorTest.php` cubre:

- create de NEW;
- update de MATCH;
- retry NEW → MATCH → UPDATE sin segundo create;
- identidad desaparecida desde dry-run;
- identidad retargeteada desde dry-run;
- desacuerdo external/code;
- missing identity;
- dry-run con errores;
- target desconocido;
- taxonomía no resuelta;
- error estable de persistencia/rollback.

### Smoke

`tests/smoke/import-row-executor.php` congela contratos críticos de resultado, stale identity, hooks seguros, rollback y checkpoint.

### WordPress/MySQL

`tests/integration/assert-import-row-executor.php` valida sobre WordPress real:

- create draft;
- meta canónica;
- source key + external ID;
- taxonomías resueltas;
- retry de la misma fila sin duplicado;
- actualización posterior;
- identity projection;
- ausencia del draft en el índice público hasta `publish`;
- quality projection del draft;
- rechazo de stale identity;
- checkpoint created/updated;
- error sin avance de checkpoint;
- finalización de batch.

Workflow dedicado:

- `Import Row Executor Integration`;
- WordPress 6.6.2 / PHP 8.1;
- WordPress latest / PHP 8.3.

## Review y correcciones

Findings atendidos durante QA:

- P1: la integración esperaba erróneamente un `draft` dentro del índice público; se corrigió para exigir ausencia mientras no esté publicado;
- P2: rollback de metadata ahora verifica lectura/existencia después de cada restauración;
- P2: rollback de creación valida también la eliminación de la proyección de identidad.

Todos los threads quedaron resueltos.

## QA final

Ejecución sobre head `7970958a6da361c8212f259aca66beb12e7af385`:

- Phase 1 CI: `success`;
- Import Row Executor Integration: `success`;
- Import Persistence Integration: `success`;
- Administration Quality Gate: `success`;
- Bootstrap Smoke: `success`;
- Catalogue Quality Integration: `success`;
- Activity Integration: `success`;
- Dashboard Integration: `success`;
- Settings UI Integration: `success`;
- Help Center Integration: `success`;
- review threads pendientes: `0`;
- P0/P1 pendientes: `0`.

## Fuera de alcance

- descarga/sideload de imágenes;
- XLSX;
- JSON como archivo de entrada;
- UI final del wizard;
- worker WP-Cron/background definitivo;
- rollback completo de un batch completo;
- migración WooCommerce/ACF;
- cambios en `propiedadesmartinez.cl`.

## Siguiente paso

Issue #54 — **Fase 3 / PR 3.5 — Runner reanudable de batches de importación**.

## Producción

`propiedadesmartinez.cl` permanece sin cambios durante Fase 3.4.
