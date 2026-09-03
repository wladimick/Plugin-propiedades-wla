# Modelo de datos

## Principio de fuente única

WLA Inmo separa deliberadamente cada concepto según el mecanismo de WordPress que mejor lo representa. Un mismo dato no debe almacenarse simultáneamente como postmeta, taxonomía y texto libre.

```text
Contenido editorial nativo  → wp_posts / media WordPress
Clasificaciones navegables  → taxonomías WLA Inmo
Datos canónicos de ficha    → postmeta _wla_inmo_*
Búsqueda de alto rendimiento→ wp_wla_property_index (proyección futura)
```

La tabla índice nunca será la fuente de verdad; se reconstruye desde el registro canónico.

## Entidad principal

Post type interno:

```text
wla_property
```

Etiqueta en administración:

```text
Propiedades
```

Contrato inicial:

- archivo `/propiedades/`;
- REST base `wla-properties`;
- imagen principal mediante featured image nativa;
- título, descripción, extracto y revisiones nativas;
- `delete_with_user = false`.

## Datos que NO son postmeta

### Contenido WordPress nativo

Se mantienen en WordPress:

- título;
- descripción principal/editor;
- extracto/resumen;
- imagen destacada;
- autor;
- estado de publicación;
- revisiones;
- fechas editoriales.

### Taxonomías

| Concepto | Taxonomía |
|---|---|
| Operación | `wla_operation` |
| Tipo de propiedad | `wla_property_type` |
| Región | `wla_region` |
| Comuna | `wla_commune` |
| Sector/barrio | `wla_sector` |

Por diseño, los campos de dominio `operation`, `property_type`, `region`, `commune` y `sector` **no se duplican en postmeta**.

## Meta schema canónico

Los meta keys físicos se almacenan con prefijo protegido:

```text
_wla_inmo_<campo>
```

Ejemplo:

```text
property_code  → _wla_inmo_property_code
price_clp      → _wla_inmo_price_clp
private_address→ _wla_inmo_private_address
```

El nombre de dominio es estable para UI, importadores y APIs futuras; el meta key físico queda encapsulado por `Properties\MetaSchema`.

### Identificación

| Campo | Tipo | Público por defecto | Uso |
|---|---|---:|---|
| `property_code` | string | Sí | código visible y estable; futuro candidato a upsert/unicidad |
| `external_id` | string | No | identificador de sistema/proveedor externo |
| `status` | string/key | Sí | estado comercial normalizado |

`status` es distinto de `post_status`: uno representa disponibilidad comercial y el otro el estado editorial WordPress.

### Comercial

| Campo | Tipo | Público | Regla |
|---|---|---:|---|
| `price_clp` | integer >= 0 | Sí | precio real ingresado en CLP |
| `price_uf` | number >= 0 | Sí | precio real ingresado en UF |
| `price_usd` | number >= 0 | Sí | precio real ingresado en USD |
| `price_on_request` | boolean | Sí | expresa “precio a consultar” sin usar 0/texto libre |
| `currency_primary` | CLP/UF/USD | Sí | moneda principal explícita |
| `common_expenses_clp` | integer >= 0 | Sí | gastos comunes CLP cuando apliquen |

Regla crítica: el frontend nunca debe inferir un precio principal desde la descripción. Conversión de monedas futura será una representación derivada, no otra fuente de verdad.

### Ubicación

Las jerarquías/regiones/comunas/sectores son taxonomías. El meta schema contiene únicamente datos complementarios:

| Campo | Tipo | Público | Nota |
|---|---|---:|---|
| `locality` | string | Sí | ciudad/localidad complementaria |
| `public_address` | string | Sí | dirección segura para publicar |
| `private_address` | string | **No** | dirección exacta interna |
| `latitude` | number -90..90 | Sí* | salida pública condicionada por política de ubicación |
| `longitude` | number -180..180 | Sí* | salida pública condicionada por política de ubicación |
| `show_map` | boolean | Sí | controla presentación de mapa |
| `location_text` | text | Sí | texto editorial breve de ubicación |

`private_address` nunca debe salir automáticamente en HTML, JSON-LD o API pública. La elegibilidad de latitud/longitud para presentación no obliga a mostrarlas si la configuración de privacidad lo impide.

### Superficies

- `land_area_m2`
- `built_area_m2`
- `usable_area_m2`
- `terrace_area_m2`

Todos son números no negativos expresados canónicamente en metros cuadrados.

### Características base

- `bedrooms` — entero no negativo.
- `bathrooms` — entero no negativo.
- `parking` — entero no negativo.
- `storage_units` — entero no negativo.
- `pool` — boolean.
- `heating` — string normalizado.
- `construction_year` — entero validado.
- `orientation` — string normalizado.

Los atributos menos universales podrán extenderse sin convertir cada característica posible en una columna/field obligatorio del core.

### Multimedia

- imagen principal: featured image nativa de WordPress;
- `gallery_ids`: array ordenado de attachment IDs positivos;
- `video_urls`: array de URLs HTTP/HTTPS permitidas.

La validación de existencia/MIME real de attachments y políticas avanzadas de video se profundizará en las capas de media/admin/importación. El schema base ya evita guardar HTML/iframes arbitrarios como dato canónico.

### Publicación y visibilidad

| Campo | Público | Default cuando aplica |
|---|---:|---|
| `featured` | Sí | `false` |
| `home_order` | No | `0` |
| `availability_date` | Sí | sin valor |
| `hide_price` | Sí | `false` |
| `indexable` | No | `true` |
| `last_verified_date` | Sí | sin valor |
| `internal_notes` | **No** | sin valor |

`indexable` es una decisión editorial interna que luego controla robots/canonical/sitemap; no es un dato que deba exponerse como meta público crudo.

## Sanitización y validación

La capa de almacenamiento utiliza dos responsabilidades separadas:

```text
Properties\Sanitizer
    ↓ normaliza/limpia tipos
Properties\Validator
    ↓ rechaza datos fuera del dominio
Properties\MetaSchema
    ↓ registra el contrato físico en WordPress
```

Reglas iniciales:

- códigos/IDs/estados con longitudes acotadas;
- precios, superficies y cantidades no negativos;
- monedas soportadas explícitamente;
- coordenadas dentro de rangos geográficos;
- fechas `YYYY-MM-DD` y calendario válido;
- attachment IDs positivos;
- URLs de video solamente HTTP/HTTPS;
- campos vacíos opcionales permitidos para no impedir borradores.

La completitud de una ficha pertenece al módulo de calidad del catálogo, no al validador de persistencia.

## REST y privacidad

En Fase 1.4 los meta keys protegidos se registran con:

```text
show_in_rest = false
```

Esto es intencional. WLA Inmo no expondrá datos internos simplemente porque existen en postmeta. La API pública versionada definirá posteriormente qué campos públicos se entregan y aplicará reglas de privacidad/contexto.

Los campos inicialmente internos/no públicos incluyen al menos:

- `external_id`;
- `private_address`;
- `home_order`;
- `indexable`;
- `internal_notes`.

## Calidad de ficha

El plugin calculará posteriormente un score interno no público a partir de elementos como:

- título;
- código;
- precio o precio a consultar;
- operación;
- tipo;
- ubicación;
- superficie;
- descripción;
- imágenes;
- alt text;
- metadata SEO;
- fecha de verificación.

El score guía al administrador y no reemplaza decisiones editoriales ni es un factor de ranking prometido.

## Tabla de índice de búsqueda

Para escalar filtros se implementará en PR 1.5:

```text
wp_wla_property_index
```

Proyección propuesta:

```text
property_id BIGINT UNSIGNED PRIMARY KEY
property_code VARCHAR(100)
external_id VARCHAR(191)
status VARCHAR(40)
operation_slug VARCHAR(100)
type_slug VARCHAR(100)
region_slug VARCHAR(100)
commune_slug VARCHAR(100)
sector_slug VARCHAR(150)
price_clp BIGINT UNSIGNED
price_uf DECIMAL(14,2)
price_usd DECIMAL(14,2)
bedrooms SMALLINT
bathrooms SMALLINT
parking SMALLINT
land_area_m2 DECIMAL(14,2)
built_area_m2 DECIMAL(14,2)
latitude DECIMAL(10,7)
longitude DECIMAL(10,7)
featured TINYINT(1)
updated_at DATETIME
```

Los índices SQL exactos se definirán según consultas y benchmarks reales; no se sobreindexará anticipadamente.

## Integridad futura

Además de la validación del schema, las fases siguientes deben asegurar:

- `property_code` único cuando esté presente;
- `external_id` utilizable para upsert según perfil/origen;
- sincronización consistente del índice;
- existencia y tipo válido de media;
- taxonomías sanitizadas;
- importaciones usando exactamente el mismo contrato/validadores que la edición manual;
- migraciones versionadas cuando cambie el schema.
