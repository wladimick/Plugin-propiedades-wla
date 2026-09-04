# Contrato de configuración — WLA Inmo

## Principio

La configuración pertenece al plugin y no al tema. WLA Inmo Light puede consumirla, pero WLA Inmo debe funcionar igual con cualquier tema.

La opción física inicial es:

```text
wla_inmo_settings
```

Los consumidores deben utilizar `Settings\\Repository` en lugar de leer la opción directamente.

## Campos de Fase 1

| Campo | Default inicial | Propósito |
|---|---|---|
| `country_code` | `CL` | preset/país activo |
| `currency_primary` | `CLP` | moneda principal global |
| `area_unit` | `m2` | unidad de superficie |
| `map_provider` | `osm` | adapter de mapas preferido |
| `property_base` | `propiedades` | base de URLs del CPT |
| `business_name` | vacío | branding opcional, nunca impuesto por el core |

Los defaults Chile viven en `Localization\\ChilePreset`. Esto evita distribuir reglas chilenas por todo el core y permite incorporar otros presets sin cambiar la identidad de `wla_property`.

## Sanitización

- `country_code`: dos letras mayúsculas.
- `currency_primary`: tres letras mayúsculas.
- `area_unit`: `m2` o `ft2` en esta versión.
- `map_provider`: `osm`, `google` o `none`.
- `property_base`: slug seguro.
- `business_name`: texto plano opcional.
- claves desconocidas: descartadas.

La configuración raw no se expone en REST en Fase 1.

## Autorización

El grupo Settings API `wla_inmo` utiliza:

```text
manage_wla_inmo_settings
```

y no `manage_options` como permiso genérico.

## URLs

`Properties\\PostType` obtiene `property_base` desde `Settings\\Repository`.

Cambiar la base requerirá en la futura UI una acción controlada para regenerar rewrite rules. La lectura normal nunca ejecuta `flush_rewrite_rules()`.

## Extensibilidad mínima

Filtro público inicial:

```text
wla_inmo_settings_defaults
```

Permite adaptar defaults en código antes de que exista UI completa. No se publica todavía una gran API de settings ni filtros por cada campo.

## Compatibilidad

Los sitios no deben asumir que Chile será siempre el país. `CL` es el preset inicial del producto, no una condición del modelo de Property, del template resolver ni de las taxonomías base.
