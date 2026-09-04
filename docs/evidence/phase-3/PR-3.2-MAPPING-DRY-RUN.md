# Evidencia — PR 3.2 Mapping + Validation + Dry-run

Estado: `QA_PASSED / MERGE_PENDING`.

Issue: #48  
PR: #49  
Rama: `feat/phase3-mapping-dry-run`  
Head funcional validado: `05ff73164fa3c0a95bc9fa89e7d36204e894fb01`

## Objetivo

Transformar filas externas en una intención canónica WLA Inmo, validar cada fila, resolver identidad y taxonomías en modo read-only y producir un dry-run determinista sin escribir posts, meta, términos, opciones, attachments ni recursos remotos.

## Implementación

### Canonical target registry

`WLA\Inmo\Import\TargetRegistry` permite únicamente:

- `post.title`, `post.content`, `post.excerpt`;
- campos compatibles existentes de `Properties\MetaSchema`;
- taxonomías WLA soportadas.

`meta.gallery_ids` está excluido del registry porque los attachment IDs son referencias internas de WordPress y no constituyen datos portables entre instalaciones. No se construyen meta keys desde headers arbitrarios.

### Taxonomía de características

Se incorpora `wla_feature` al registry de taxonomías WLA porque el contrato de producto contempla características múltiples y el core previo registraba solo Operación, Tipo, Región, Comuna y Sector.

La nueva taxonomía:

- se asocia solo a `wla_property`;
- reutiliza capabilities WLA;
- es plana;
- usa rewrite `caracteristica`;
- usa REST base `wla-features`;
- no crea términos ni datos por sí sola.

### Header normalization compartida

`HeaderNormalizer` es ahora el contrato único empleado por `CsvReader` y `MappingProfile`.

Cubre explícitamente vocales acentuadas, Ü y Ñ en mayúscula/minúscula y reduce los headers a snake_case ASCII. Así, por ejemplo:

```text
CÓDIGO Propiedad -> codigo_propiedad
Ñandú Área Útil -> nandu_area_util
```

Esto evita que un perfil válido deje de mapear columnas españolas por diferencias de normalización entre parser y mapping.

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

Para targets multi-source, `preserve` solo se conserva cuando todas las columnas que alimentan ese target están vacías. Si una de ellas entrega un valor o intención de clear, el target se procesa.

### Normalización y validación

`ValueNormalizer` + `RowMapper` validan sin escritura:

- strings;
- textarea conservando saltos de línea para `location_text` e `internal_notes`;
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

1. primera pasada: fingerprints canónicos de identidad + números de fila para detectar todos los duplicados intra-file;
2. segunda pasada: mapping/validación, taxonomías read-only, identidad existente y clasificación final.

La primera pasada usa la misma normalización canónica que `RowMapper`; códigos o external IDs equivalentes después de sanitización no pueden escapar de la detección de duplicados.

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

Reglas:

- término desconocido en Operación/Tipo/Región/Comuna/Sector => error;
- característica desconocida => warning y preservación completa de `taxonomy.feature`, evitando una actualización parcial destructiva futura;
- lookup ambiguo => error;
- resultado válido conserva únicamente `id` + `slug` derivados;
- `EMPTY_CLEAR` en una taxonomía se conserva como intención explícita `null`/`[]` y no ejecuta lookup.

### Privacidad

`DryRunResult::toArray(false)` excluye targets definidos como privados en MetaSchema. Solo `toArray(true)` permite incluirlos explícitamente para una capa futura que ya haya comprobado capability.

## Tests incorporados

`tests/unit/ImportDryRunTest.php` y `tests/unit/ImportDryRunRegressionTest.php` cubren:

- target registry;
- profile válido/inválido;
- unknown target;
- duplicate target;
- gallery IDs bloqueados;
- preserve/clear de vacíos;
- precio/status/boolean/date/lat;
- valores inválidos;
- fila nueva;
- update;
- changed targets;
- taxonomías read-only;
- múltiples features;
- duplicado external identity intra-file;
- duplicado property code intra-file;
- normalización canónica de identidad antes de detectar duplicados;
- title obligatorio para alta;
- privacidad del resultado;
- headers españoles compartidos por CSV/profile;
- unknown feature preservando el target completo;
- clear de taxonomía sin lookup;
- multi-source feature con una columna vacía y otra con valor;
- textarea multilinea;
- resumen streaming de 5.000 filas sin retener resultados en el caller.

`tests/smoke/import-dry-run.php` valida el pipeline source-checkout con una fila nueva y una actualización.

`tests/smoke/import-csv.php` incluye explícitamente el normalizador compartido al ejecutarse de forma aislada.

`tests/smoke/taxonomies.php` valida las seis taxonomías y cubre `wla_feature`.

## Findings detectados y corregidos

### F3.2-001 — identidad cruda en primera pasada

Review detectó que la primera pasada podía considerar distintos dos códigos que después se volvían idénticos por sanitización.

Clasificación: P1 / integridad de identidad.  
Corrección: la primera pasada usa `TargetRegistry + ValueNormalizer`, igual que la segunda. Regresión con espacios/markup añadida.

### F3.2-002 — normalización distinta de headers españoles

Review detectó que `MappingProfile` y `CsvReader` no compartían exactamente la misma regla para acentos.

Clasificación: P1 / fiabilidad del mapping.  
Corrección: `HeaderNormalizer` compartido y regresiones con mayúsculas, acentos y Ñ.

### F3.2-003 — clear de taxonomía podía convertirse en término desconocido

Review detectó que `EMPTY_CLEAR` producía `null`, pero el resolver intentaba buscarlo como término.

Clasificación: P2 / semántica de actualización.  
Corrección: `null`/`[]` omite el lookup y queda como clear explícito.

### F3.2-004 — target multi-source podía quedar marcado preserve teniendo datos

Review detectó que una columna vacía podía marcar todo `taxonomy.feature` como preservado aunque otra columna sí tuviera valor.

Clasificación: P2 / exactitud del dry-run.  
Corrección: preserve se elimina si cualquier fuente del mismo target entrega valor/clear.

### F3.2-005 — textarea canónico se trataba como text

Review detectó que `location_text` e `internal_notes` perdían saltos de línea.

Clasificación: P2 / fidelidad de datos.  
Corrección: ambos targets usan validador `textarea` y tienen regresión multilinea.

### F3.2-006 — unknown feature podía producir actualización parcial futura

Autorrevisión detectó que warning + subset resuelto podía terminar representando una intención destructiva si luego se persistía.

Clasificación: seguridad de datos / fail-safe.  
Corrección: ante cualquier feature desconocida se omite el target completo y se marca `preserve`.

### F3.2-007 — smoke CSV aislado no cargaba HeaderNormalizer

Bootstrap Smoke detectó un fatal al ejecutar `tests/smoke/import-csv.php` de forma aislada después de extraer el normalizador compartido.

Clasificación: regresión de test/bootstrap.  
Corrección: require explícito en el smoke aislado. Revalidado en Bootstrap Smoke final.

### F3.2-008 — branch redundante detectado por PHPStan

PHPStan detectó una comparación con `null` imposible después del narrowing de tipos en identidad.

Clasificación: static-analysis / limpieza de tipos.  
Corrección: branch redundante eliminado. PHPStan final: 0 errores.

## QA final del head funcional

Head funcional validado: `05ff73164fa3c0a95bc9fa89e7d36204e894fb01`.

### Phase 1 CI

Workflow `33880993204`: `SUCCESS`.

- Composer validate: `SUCCESS`;
- PHP syntax: `SUCCESS`;
- WordPress Coding Standards: `SUCCESS`;
- PHPStan: `SUCCESS`, 0 errores;
- PHPUnit: **27 tests / 163 assertions**, `SUCCESS`;
- source smoke tests: `SUCCESS`;
- `WLA Inmo import CSV foundation smoke tests passed`;
- `WLA Inmo mapping and dry-run smoke tests passed`;
- `WLA Inmo taxonomy smoke tests passed`;
- build ZIP: `SUCCESS`;
- release ZIP smoke: `SUCCESS`;
- WordPress 6.6.2 / PHP 8.1: `SUCCESS`;
- WordPress latest / PHP 8.3: `SUCCESS`;
- preservación tras deactivate/uninstall: `SUCCESS`.

### Regresiones heredadas

Sobre el mismo head funcional:

- Bootstrap Smoke `33880993229`: `SUCCESS`;
- Catalogue Quality Integration `33880993245`: `SUCCESS`;
- Dashboard Integration `33880993295`: `SUCCESS`;
- Settings UI Integration `33880993258`: `SUCCESS`;
- Help Center Integration `33880993227`: `SUCCESS`;
- Activity Integration `33880993143`: `SUCCESS`;
- Administration Quality Gate `33880993251`: `SUCCESS`.

Los cinco review threads automáticos quedaron respondidos y resueltos; no quedan findings P1/P2 conocidos abiertos en el alcance revisado.

## Artifact y checksum

Workflow `33880993204`:

- artifact `9939837531`;
- artifact digest `sha256:54d63f34dc320d68e55a574aef592891e782be6cf7eac5ecaf770514f4b5ce9a`;
- ZIP `wla-inmo-0.1.0-alpha.zip` SHA-256 `7728e07e602dfb78e7520d9668a61d246b4fed3d66cb701cbd5c6bd7eef50fcb`.

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

## Criterio de salida

PR 3.2 queda `QA_PASSED / MERGE_PENDING`.

El siguiente hito es **PR 3.3 — Batch persistence + resume + idempotency**. Antes de habilitar upsert real debe resolverse de forma explícita la persistencia de `(source_key, external_id)`.

## Riesgo pendiente para PR 3.3

`source_key` todavía no es metadata canónica persistida junto a `external_id`. Antes de habilitar cualquier upsert real, PR 3.3 debe congelar una representación persistente que permita resolver `(source_key, external_id)` sin colisiones entre proveedores.

## Producción

`propiedadesmartinez.cl` no se modifica ni recibe este código durante PR 3.2.
