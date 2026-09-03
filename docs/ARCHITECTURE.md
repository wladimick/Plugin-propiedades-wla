# Arquitectura de WLA Inmo

## Objetivo

Construir un plugin modular, mantenible y reutilizable que concentre la lógica inmobiliaria y sea independiente del tema activo.

## Regla principal

**Plugin = datos + lógica + API de presentación.**

**Tema = diseño + composición visual.**

WLA Inmo Light será solo una implementación visual de referencia.

## Capas

```text
WordPress
│
├── WLA Inmo Core
│   ├── Domain
│   ├── Admin
│   ├── Search
│   ├── Import
│   ├── SEO
│   ├── Leads
│   ├── Security
│   ├── Help
│   └── Frontend API/Templates
│
└── Theme activo
    ├── WLA Inmo Light
    └── o cualquier otro tema
```

## Estructura propuesta

```text
plugin/wla-inmo/
├── wla-inmo.php
├── uninstall.php
├── src/
│   ├── Core/
│   │   ├── Plugin.php
│   │   ├── Activator.php
│   │   └── Installer.php
│   ├── Properties/
│   │   ├── PostType.php
│   │   ├── Meta.php
│   │   ├── Repository.php
│   │   ├── Statuses.php
│   │   └── Media.php
│   ├── Taxonomies/
│   ├── Admin/
│   ├── Search/
│   ├── Import/
│   ├── Leads/
│   ├── Indicators/
│   ├── SEO/
│   ├── Security/
│   ├── Migration/
│   ├── Help/
│   └── Frontend/
├── templates/
│   ├── archive-property.php
│   ├── single-property.php
│   └── parts/
├── assets/
│   ├── css/
│   └── js/
└── languages/
```

## Modelo de dominio

La entidad principal es `Property`.

WordPress almacenará el contenido editorial y los adjuntos. Los datos usados intensivamente para búsqueda pueden sincronizarse a una tabla índice propia.

```text
wp_posts
└── post_type = wla_property

wp_postmeta
└── metadatos editoriales/no críticos

wp_wla_property_index
└── campos filtrables/indexados
```

La tabla índice no reemplaza al registro canónico; es una proyección optimizada para búsqueda.

## Independencia del tema

El plugin debe ofrecer tres niveles de integración:

1. **Plantillas fallback del plugin.** Permiten funcionar inmediatamente con cualquier tema.
2. **Overrides del tema.** Un tema puede sobrescribir plantillas creando archivos bajo `wla-inmo/`.
3. **API/Blocks/Shortcodes.** Para integraciones personalizadas.

Ejemplo de override:

```text
wp-content/themes/mi-tema/wla-inmo/single-property.php
```

Si existe, se usa. Si no existe, WLA Inmo usa su plantilla interna.

## Hooks públicos

La arquitectura debe exponer acciones y filtros estables, por ejemplo:

```text
wla_inmo_property_saved
wla_inmo_property_imported
wla_inmo_before_property_card
wla_inmo_after_property_card
wla_inmo_property_schema
wla_inmo_property_query_args
```

Esto permite extender el plugin sin modificar el core.

## Frontend

- Renderizado server-side por PHP.
- JavaScript progresivo, no obligatorio para leer contenido.
- Sin jQuery como dependencia.
- CSS namespaced con prefijo `wla-inmo-`.
- Assets condicionales por pantalla.
- HTML semántico y accesible.

## Compatibilidad

El plugin debe evitar asumir:

- ancho del contenido del tema;
- tipografía específica;
- framework CSS;
- Elementor;
- WooCommerce;
- ACF;
- jQuery.

## Versionado

Se usará SemVer:

```text
MAJOR.MINOR.PATCH
```

Ejemplo:

```text
0.1.0 documentación + base
0.2.0 modelo de propiedades
0.3.0 importador
1.0.0 primera versión estable
```

## Migraciones internas

Cada versión que modifique esquema o datos debe ejecutar migraciones controladas mediante una versión almacenada en opciones:

```text
wla_inmo_db_version
```

No se deben ejecutar operaciones pesadas en cada request.