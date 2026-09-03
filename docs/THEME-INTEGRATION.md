# Integración con temas

## Objetivo

WLA Inmo debe funcionar correctamente con un tema WordPress externo y no depender de WLA Inmo Light.

WLA Inmo Light será un tema de referencia optimizado para rendimiento, pero opcional.

## Contrato plugin/tema

El plugin entrega:

- datos;
- consultas;
- filtros;
- componentes funcionales;
- plantillas fallback;
- CSS mínimo necesario;
- hooks y API pública.

El tema controla:

- tipografía;
- colores;
- layout global;
- header/footer;
- composición de páginas;
- estilos visuales de marca.

## Plantillas fallback

El plugin debe incluir al menos:

```text
templates/archive-property.php
templates/single-property.php
templates/parts/property-card.php
templates/parts/property-gallery.php
templates/parts/property-search.php
templates/parts/property-details.php
templates/parts/property-contact.php
```

Estas plantillas deben ser suficientemente neutras para funcionar con un tema estándar.

## Overrides

Cualquier tema puede reemplazar una plantilla sin modificar el plugin.

Ejemplo:

```text
mi-tema/
└── wla-inmo/
    ├── single-property.php
    └── parts/
        └── property-card.php
```

Orden de resolución:

1. override del tema hijo;
2. override del tema activo;
3. plantilla interna del plugin.

## API de presentación

Además de plantillas, el plugin puede exponer:

- bloques Gutenberg;
- shortcodes de compatibilidad;
- funciones PHP documentadas;
- hooks.

Los shortcodes no deben ser la única manera de usar el plugin.

## CSS

El CSS funcional del plugin debe:

- usar prefijo/namespacing `wla-inmo-`;
- evitar reset global;
- no modificar `body`, `h1`, `button`, `a` globalmente;
- usar custom properties para personalización;
- ser reemplazable/desactivable por un tema avanzado.

Variables sugeridas:

```css
--wla-inmo-primary
--wla-inmo-accent
--wla-inmo-text
--wla-inmo-surface
--wla-inmo-radius
--wla-inmo-container
```

## JavaScript

- Vanilla JS.
- Progressive enhancement.
- Sin jQuery obligatorio.
- Ficha legible y navegable aunque falle JS.
- Galería y filtros deben degradar de forma razonable.

## WLA Inmo Light

Tema oficial de referencia.

Objetivos:

- muy pocos archivos y dependencias;
- `theme.json`;
- CSS pequeño;
- sin page builder;
- Core Web Vitals como prioridad;
- accesibilidad;
- integración visual completa con WLA Inmo.

Estructura propuesta:

```text
theme/wla-inmo-light/
├── style.css
├── functions.php
├── theme.json
├── index.php
├── header.php
├── footer.php
├── front-page.php
├── page.php
└── assets/
```

WLA Inmo Light no debe registrar el CPT, guardar precios, crear taxonomías ni manejar importaciones. Si el tema cambia, los datos y el negocio siguen funcionando.

## Pruebas de independencia

Antes de cada release importante se debe probar el plugin con:

- WLA Inmo Light;
- un tema estándar de WordPress;
- al menos un tema popular ajeno al proyecto.

La instalación debe seguir siendo usable aunque el tema externo no tenga overrides específicos.

## Page builders

Elementor u otros builders pueden utilizarse por elección del sitio, pero nunca son requisito.

WLA Inmo debe proporcionar componentes que un builder pueda insertar sin que el core dependa de él.