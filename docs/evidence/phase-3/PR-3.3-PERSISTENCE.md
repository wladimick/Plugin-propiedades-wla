# Evidencia — PR 3.3 Persistencia de identidad y batches reanudables

Estado: `IN_PROGRESS / QA_PENDING`.

Issue: #50  
Rama: `feat/phase3-persistence`

## Objetivo

Crear una base persistente y segura para que el importador de WLA Inmo pueda pasar del dry-run read-only a ejecución real por lotes en PRs siguientes, manteniendo identidad externa inequívoca y progreso reanudable.

## Identidad canónica

La identidad externa se compone de:

- `_wla_inmo_external_source_key`: fuente normalizada y privada;
- `_wla_inmo_external_id`: identificador externo ya existente en `MetaSchema`.

`IdentityMeta` registra y sanitiza `external_source_key` sin exponerlo por REST.

La fuente de verdad continúa siendo WordPress post/meta. La tabla `wla_import_identity` es únicamente una proyección indexada, consistente con la arquitectura híbrida ya aprobada.

### Proyección de identidad

`IdentityProjection` incluye propiedades WLA publicadas y no publicadas, excepto `trash` y `auto-draft`, porque una importación no puede ignorar una propiedad existente solo por estar en borrador.

La proyección contiene:

- `property_id`;
- `source_key`;
- `external_id`;
- `property_code`;
- `updated_at`.

Reglas de integridad:

- `source_key` y `external_id` deben existir juntos o ambos estar vacíos;
- `property_code` es único en la proyección;
- `(source_key, external_id)` tiene índice UNIQUE en base de datos;
- una colisión con otra propiedad hace fallar el upsert de la proyección;
- `IdentityRepository::resolver()` alimenta el `IdentityResolver` de Phase 3.2 sin cambiar su contrato.

La restricción UNIQUE de base de datos es la última barrera ante carreras entre procesos concurrentes.

## Sincronización

`IdentityIndexer` mantiene la proyección al guardar, eliminar, enviar a papelera o restaurar una propiedad.

No se realiza un rebuild masivo automático durante un request normal. La estrategia de backfill para instalaciones que ya contengan propiedades WLA se cerrará antes de una release estable/migración productiva, para evitar introducir una operación potencialmente larga en `admin_init`.

## Batch persistente

Se incorpora `wla_import_batches` con:

- UUID estable;
- `source_key`;
- SHA-256 del origen;
- snapshot JSON del perfil;
- usuario creador;
- estado;
- total de filas;
- cursor lógico;
- filas procesadas;
- contadores creadas/actualizadas/omitidas/warnings/errores;
- timestamps de creación/inicio/fin;
- `revision` para optimistic locking.

No se persiste el payload completo de las filas dentro de la tabla de batch.

## Reanudación e idempotencia operacional

`BatchRepository` impone:

- transiciones permitidas por `BatchStatus`;
- rechazo de revisiones obsoletas;
- progreso y contadores monotónicos;
- cursor que nunca retrocede;
- imposibilidad de superar `total_rows`;
- imposibilidad de marcar `completed` antes de procesar todas las filas;
- conservación de `started_at` al pausar/reanudar;
- `completed_at` solo al finalizar.

La columna `revision` permite que dos workers no actualicen silenciosamente el mismo snapshot de estado. El ejecutor de filas de un PR posterior deberá usar este contrato para confirmar progreso solo después de una operación idempotente.

## Schema install/upgrade

`Core\Installer` instala y versiona:

- `IdentitySchema`;
- `BatchSchema`;

junto a los schemas ya existentes. `maybeUpgrade()` compara versiones pequeñas y ejecuta `dbDelta()` solo cuando existe mismatch.

## Tests iniciales

`tests/unit/ImportPersistenceTest.php` cubre:

- normalización de `source_key`;
- UNIQUE para `property_code`;
- UNIQUE para `(source_key, external_id)`;
- resolución por identidad indexada;
- rechazo de colisiones entre propiedades;
- rechazo de identidad incompleta;
- creación de batch;
- secuencia completa de estados;
- rechazo de salto inválido de estado;
- optimistic locking por `revision`;
- progreso monotónico;
- pausa/reanudación;
- rechazo de `completed` prematuro;
- finalización completa y timestamps.

`tests/smoke/import-persistence.php` verifica contratos críticos directamente desde el source checkout.

## Fuera de alcance de este PR

- escritura real de campos de propiedad desde una fila de importación;
- descarga/sideload de imágenes;
- worker WP-Cron/background definitivo;
- UI del wizard;
- XLSX/JSON;
- rollback de cambios;
- backfill masivo automático;
- migración WooCommerce/ACF;
- modificación de `propiedadesmartinez.cl`.

## QA pendiente

Antes del merge:

- PHP syntax;
- WPCS;
- PHPStan;
- PHPUnit;
- source smoke;
- ZIP smoke;
- WordPress 6.6.2 / PHP 8.1;
- WordPress latest / PHP 8.3;
- Administration Quality Gate;
- review de PR y findings resueltos.

## Producción

`propiedadesmartinez.cl` permanece sin cambios durante Phase 3.3.
