# Modelo de datos

## Entidad principal

Post type interno:

```text
wla_property
```

Etiqueta en administración:

```text
Propiedades
```

## Identificación

Campos base:

- `property_code` — código único visible.
- `external_id` — identificador externo opcional para integraciones/importaciones.
- `status` — estado comercial.
- `operation` — venta, arriendo u otra.
- `property_type` — casa, departamento, terreno, etc.

`property_code` y `external_id` deben poder utilizarse para upsert en importaciones.

## Comercial

- `price_clp`
- `price_uf`
- `price_usd`
- `price_on_request`
- `currency_primary`

Regla: un campo es la fuente de verdad. El frontend nunca debe inferir un precio principal desde una descripción de texto.

## Ubicación

- región.
- comuna.
- ciudad/localidad.
- sector/barrio.
- dirección pública.
- dirección privada opcional.
- latitud.
- longitud.

La dirección pública puede ser distinta de la ubicación administrativa cuando por seguridad no se quiera revelar una dirección exacta.

## Superficies

- `land_area_m2`
- `built_area_m2`
- `usable_area_m2`
- `terrace_area_m2`

## Características

- dormitorios.
- baños.
- estacionamientos.
- bodegas.
- piscina.
- calefacción.
- antigüedad/año.
- orientación.
- gastos comunes.

Los atributos menos universales deben poder extenderse sin modificar el modelo base.

## Contenido editorial

- título.
- descripción principal.
- extracto/resumen.
- texto de ubicación.
- observaciones.
- contenido SEO opcional.

## Multimedia

- imagen destacada nativa de WordPress.
- galería ordenada de attachment IDs.
- videos locales o URLs permitidas.
- texto alternativo por imagen.

## Clasificación mediante taxonomías

Taxonomías sugeridas:

```text
wla_operation
wla_property_type
wla_region
wla_commune
wla_sector
wla_feature
```

No todo debe ser taxonomía. Solo lo que sea útil para navegación, agrupación, filtros o SEO.

## Publicación

- destacada.
- orden de portada.
- fecha de disponibilidad.
- ocultar precio.
- indexar/no indexar.
- fecha de última verificación comercial.

## Calidad de ficha

El plugin calculará un score interno no público a partir de:

- título.
- código.
- precio o indicador de precio a consultar.
- operación.
- tipo.
- ubicación.
- superficie.
- descripción.
- imágenes.
- alt text.
- meta description.

El score ayuda al administrador; no debe reemplazar decisiones editoriales.

## Tabla de índice de búsqueda

Para escalar filtros:

```text
wp_wla_property_index
```

Campos propuestos:

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

Índices se definirán según consultas reales; no se debe sobreindexar antes de medir.

## Integridad

- Código único cuando esté presente.
- Precios numéricos normalizados.
- Coordenadas validadas.
- IDs de imágenes existentes.
- Taxonomías sanitizadas.
- Importaciones deben usar las mismas validaciones que el formulario manual.