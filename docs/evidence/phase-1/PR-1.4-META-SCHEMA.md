# Evidencia — Fase 1 / PR 1.4 Meta schema canónico y validación

Estado documental: `IN_PROGRESS`.

Issue: #11  
Rama: `feat/phase1-meta-schema`

## Objetivo

Definir una fuente única, tipada, sanitizada y validable para los datos de propiedad que no pertenecen al contenido nativo de WordPress ni a las taxonomías WLA Inmo.

## Componentes

### `Properties\\MetaSchema`

- registra postmeta únicamente sobre `wla_property`;
- storage keys `_wla_inmo_*`;
- `single = true`;
- raw meta `show_in_rest = false` en esta fase;
- sanitizer por campo;
- auth callback basada en `edit_post`;
- clasificación de campos elegibles para presentación pública vs internos.

### `Properties\\Sanitizer`

Normaliza:

- texto/textarea/key;
- booleanos;
- enteros y números no negativos;
- moneda CLP/UF/USD;
- fechas calendario `YYYY-MM-DD`;
- latitud/longitud;
- arrays de attachment IDs;
- arrays de URLs HTTP/HTTPS.

### `Properties\\Validator`

Valida antes de persistir y devuelve códigos de error estables. Los campos vacíos opcionales son válidos para permitir borradores; la completitud se evalúa en Calidad del catálogo.

## Fuente única

No existen en meta schema:

- `operation`;
- `property_type`;
- `region`;
- `commune`;
- `sector`.

Esos conceptos permanecen en taxonomías. Título, descripción, extracto, imagen destacada y revisiones permanecen en WordPress nativo.

## Privacidad

Campos internos/no públicos iniciales:

- `external_id`;
- `private_address`;
- `home_order`;
- `indexable`;
- `internal_notes`.

`private_address` e `internal_notes` nunca deben salir automáticamente al frontend, Schema o API pública.

## Multimedia

- imagen principal: featured image de WordPress;
- `gallery_ids`: attachment IDs positivos y ordenados;
- `video_urls`: solo HTTP/HTTPS como dato canónico.

No se acepta iframe/HTML arbitrario como valor canónico de video.

## Tests

`tests/smoke/meta-schema.php` cubre:

- 37 campos canónicos;
- prefijo y unicidad de meta keys;
- ausencia de taxonomías duplicadas en meta;
- separación public/private;
- `register_post_meta()` solo para `wla_property`;
- raw REST deshabilitado;
- autorización por `edit_post`;
- sanitización de texto, keys, booleanos, números, moneda, fechas, coordenadas, IDs y URLs;
- validación positiva y escenarios inválidos.

El smoke del ZIP también exige/autoloadea `MetaSchema`, `Sanitizer` y `Validator`.

## Documentación actualizada

`docs/DATA-MODEL.md` queda alineado con el contrato implementado y documenta explícitamente qué vive en posts, taxonomías, postmeta y futura tabla índice.

## Producción

No afectada. Esta PR define contrato/registro de metadatos, pero no migra propiedades ni escribe valores de negocio existentes.

## Cierre

Completar con número de PR, CI final, artifact, digest y merge commit después del QA.
