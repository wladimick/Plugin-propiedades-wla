# WLA Inmo

**WLA Inmo** es un motor inmobiliario ligero para WordPress, pensado para administrar, publicar, importar y posicionar propiedades sin depender de WooCommerce, Elementor, ACF ni WPCode.

El proyecto debe poder instalarse en distintos sitios y funcionar con cualquier tema WordPress compatible. **WLA Inmo Light** será un tema de referencia ultraligero, pero nunca una dependencia obligatoria del plugin.

## Objetivos principales

- Rendimiento alto y frontend liviano.
- Administración simple para usuarios no técnicos.
- Seguridad por diseño.
- Carga masiva de propiedades mediante XLSX, CSV y JSON.
- Importaciones repetibles sin duplicar propiedades.
- SEO técnico sólido.
- Contenido preparado para GEO y AEO.
- Arquitectura reutilizable para distintas inmobiliarias.
- Compatibilidad con cualquier tema WordPress.
- Migración segura desde instalaciones existentes con WooCommerce/ACF.

## Principios del proyecto

1. **El plugin contiene los datos y la lógica.**
2. **El tema contiene la presentación.**
3. **WLA Inmo Light es opcional.**
4. **No debe existir dependencia obligatoria de un page builder.**
5. **Las propiedades no son productos.** Se modelan como entidades inmobiliarias nativas.
6. **Una sola fuente de verdad por dato.** Precio, estado, ubicación, superficies y demás atributos no deben existir duplicados en distintos campos.
7. **La administración debe ser comprensible sin conocimientos técnicos de WordPress.**
8. **La importación debe ser segura, validada, trazable y reversible cuando sea posible.**
9. **SEO/GEO/AEO se diseña desde el modelo de datos, no como un parche posterior.**

## Componentes

### WLA Inmo Core

Plugin principal.

Responsabilidades:

- Custom Post Type de propiedades.
- Taxonomías inmobiliarias.
- Metadatos y modelo de datos.
- Panel administrativo.
- Importador masivo.
- Buscador y filtros.
- Plantillas fallback.
- Galerías, videos y mapas.
- Leads y solicitudes de visita.
- Indicadores económicos.
- SEO, Schema y sitemap.
- Seguridad y permisos.
- Herramientas de migración.
- Centro de ayuda.

### WLA Inmo Light

Tema de referencia opcional.

Responsabilidades:

- Header y navegación.
- Home.
- Archivo de propiedades.
- Ficha individual.
- Páginas informativas.
- Footer.
- Estilos mínimos y accesibles.

No contiene lógica de negocio ni datos inmobiliarios.

## Dependencias que queremos eliminar

WLA Inmo no debe requerir:

- WooCommerce.
- Elementor.
- ACF.
- WPCode.
- Plugins de filtros de productos.
- jQuery en frontend.

Podrá convivir temporalmente con ellos durante una migración.

## Estructura prevista del repositorio

```text
Plugin-propiedades-wla/
├── plugin/
│   └── wla-inmo/
│       ├── wla-inmo.php
│       ├── src/
│       │   ├── Admin/
│       │   ├── Properties/
│       │   ├── Taxonomies/
│       │   ├── Search/
│       │   ├── Import/
│       │   ├── Leads/
│       │   ├── Indicators/
│       │   ├── SEO/
│       │   ├── Security/
│       │   ├── Migration/
│       │   └── Help/
│       ├── templates/
│       └── assets/
├── theme/
│   └── wla-inmo-light/
├── docs/
└── README.md
```

## Documentación

- [Visión y requisitos](docs/PRODUCT-REQUIREMENTS.md)
- [Arquitectura](docs/ARCHITECTURE.md)
- [Modelo de datos](docs/DATA-MODEL.md)
- [Administración y experiencia de usuario](docs/ADMIN-UX.md)
- [Centro de ayuda](docs/HELP-CENTER.md)
- [Importación y carga masiva](docs/IMPORT-EXPORT.md)
- [SEO, GEO y AEO](docs/SEO-GEO-AEO.md)
- [Seguridad](docs/SECURITY.md)
- [Integración con temas](docs/THEME-INTEGRATION.md)
- [Migración desde el sitio actual](docs/MIGRATION.md)
- [Roadmap](docs/ROADMAP.md)

## Estado

Proyecto en fase de definición y documentación. La primera implementación debe comenzar solamente después de validar el modelo de datos, la estrategia de migración y la experiencia administrativa.