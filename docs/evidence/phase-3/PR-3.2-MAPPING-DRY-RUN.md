# Evidencia — PR 3.2 Mapping + Validation + Dry-run

Estado: `IN_PROGRESS / QA_PENDING`.

Issue: #48  
Rama: `feat/phase3-mapping-dry-run`

## Objetivo

Transformar filas externas en una intención canónica WLA Inmo, validar cada fila, resolver identidad y taxonomías en modo read-only y producir un dry-run determinista sin escribir posts, meta, términos, opciones, attachments ni recursos remotos.

## Implementación inicial

### Canonical target registry

`WLA\Inmo\Import\TargetRegistry` permite únicamente:

- `post.title`, `post.content`, `post.excerpt`;
- campos existentes de `Properties\MetaSchema`;
- taxonomías WLA soportadas.

`meta.gallery_ids` está explícitamente bloqueado para Phase 3.2. No se construyen meta keys desde headers arbitrarios.

### Taxonomía de características

Se incorpora `wla_feature` al registry de taxonomías WLA, porque el contrato de producto ya contempla características múltiples y el core previo solo registraba Operación, Tipo, Región, Comuna y Sector.

La nueva taxonomía:

- se asocia solo a `wla_property`;
- reutiliza capabilities WLA;
- es plana;
- usa rewrite `caracteristica`;
- no introduce datos por sí sola.

### Mapping profile

`MappingProfile` registra:

- versión de contrato;
- `source_key` normalizado;
- nombre legible opcional;
- `source_header -> canonical_target`;
- política de vacíos;
- separador por columna para targets múltiples.

Default seguro: `empty -> preserve`.

Se rechazan:

- versiones desconocidas;
- targets desconocidos;
- headers inválidos;
- dos columnas hacia un target single-value;
- separadores aplicados a targets single-value;
- gallery IDs.

### Normalización y validación

`ValueNormalizer` + `RowMapper` validan sin escritura:

- strings/textarea;
- booleanos estrictos;
- integer/number;
- precios, superficies y cantidades no negativas;
- moneda CLP/UF/USD;
- status permitido;
- fechas YYYY-MM-DD válidas;
- latitud/longitud;
- listas de URL HTTP/HTTPS;
- términos simples/múltiples.

Los errores contienen código estable + target, sin copiar la fila completa.

### Dry-run

`DryRunEngine` usa dos pasadas sobre un `rowFactory` re-ejecutable:

1. primera pasada: solo fingerprints de identidad + números de fila para detectar todos los duplicados intra-file;
2. segunda pasada: mapping/validación, taxonomías read-only, identidad existente y clasificación final.

Resultados:

- `new`;
- `update`;
- `error`;
- warnings separados;
- `property_id` cuando existe;
- valores derivados;
- targets preservados;
- targets que cambiarían cuando se entrega snapshot read-only.

El diseño evita retener el payload completo de 5.000 filas solo para poder marcar duplicados anteriores.

### Taxonomías read-only

El motor recibe un callback de lookup y no llama funciones de creación/escritura.

Regla inicial:

- término desconocido en Operación/Tipo/Región/Comuna/Sector => error;
- característica desconocida => warning y no se resuelve a ID;
- lookup ambiguo => error;
- resultado válido conserva únicamente `id` + `slug` derivados.

### Privacidad

`DryRunResult::toArray(false)` excluye targets definidos como privados en MetaSchema. Solo `toArray(true)` permite incluirlos explícitamente para una capa futura que ya haya comprobado capability.

## Tests incorporados

`tests/unit/ImportDryRunTest.php` cubre inicialmente:

- target registry;
- profile inválido;
- unknown target;
- duplicate target;
- gallery IDs bloqueados;
- preserve de vacíos;
- precio/status/boolean/date/lat;
- valores inválidos;
- fila nueva;
- update;
- changed targets;
- taxonomías read-only;
- múltiples features;
- duplicado external identity intra-file;
- duplicado property code intra-file;
- title obligatorio para alta;
- privacidad del resultado;
- resumen streaming de 5.000 filas sin retener resultados en el caller.

`tests/smoke/import-dry-run.php` valida el pipeline source-checkout con una fila nueva y una actualización.

`tests/smoke/taxonomies.php` se actualiza a seis taxonomías y cubre `wla_feature`.

## Mutaciones deliberadamente ausentes

Este PR no contiene lógica de:

- `wp_insert_post()`;
- `wp_update_post()`;
- `update_post_meta()`;
- `delete_post_meta()`;
- `wp_set_object_terms()`;
- `wp_insert_term()`;
- descarga/sideload de media;
- requests HTTP;
- persistencia de batch;
- confirmación del dry-run.

La persistencia real sigue diferida a PR 3.3.

## QA pendiente

Antes del merge:

- PHP syntax;
- WPCS;
- PHPStan;
- PHPUnit;
- source smoke;
- WordPress 6.6.2 / PHP 8.1;
- WordPress latest / PHP 8.3;
- regresión Fase 1/2;
- Administration Quality Gate;
- revisión de PR y resolución de findings.

Los resultados concretos se registrarán después de CI.

## Riesgo pendiente para PR 3.3

`source_key` todavía no es metadata canónica persistida junto a `external_id`. Antes de habilitar cualquier upsert real, PR 3.3 debe congelar una representación persistente que permita resolver `(source_key, external_id)` sin colisiones entre proveedores.

## Producción

`propiedadesmartinez.cl` no se modifica ni recibe este código durante PR 3.2.
