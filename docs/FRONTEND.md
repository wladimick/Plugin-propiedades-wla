# Frontend WLA Inmo — contrato de integración

Estado: `PHASE-4 / PR-4.1 FOUNDATION`

Este documento define el contrato público inicial del frontend de WLA Inmo. El objetivo es que el plugin funcione con cualquier tema WordPress compatible sin depender de WLA Inmo Light, Elementor, WooCommerce, ACF, WPCode, jQuery ni un framework CSS.

## Responsabilidades

### Plugin

WLA Inmo es responsable de:

- detectar consultas públicas del CPT Property;
- resolver templates WLA de forma segura;
- entregar fallbacks mínimos instalables;
- exponer un renderer con argumentos explícitos;
- registrar hooks públicos base;
- cargar assets propios solo en superficies WLA;
- mantener datos privados fuera del frontend público.

### Tema

El tema mantiene la responsabilidad de:

- header/footer;
- tipografía;
- colores globales;
- layout general;
- composición visual del sitio;
- overrides opcionales de templates WLA.

WLA Inmo Light es un tema de referencia opcional, no una dependencia del plugin.

## Resolución de templates

Clase: `WLA\Inmo\Frontend\TemplateResolver`.

Precedencia canónica:

1. tema hijo: `wla-inmo/<template>`;
2. tema padre/activo: `wla-inmo/<template>`;
3. plugin: `templates/<template>`.

PR 4.1 permite únicamente:

- `archive-property.php`;
- `single-property.php`.

La allowlist crecerá en los PR posteriores cuando se introduzcan partials reales. Un request HTTP nunca entrega un path de filesystem.

### Seguridad del resolver

- nombres no allowlisted se rechazan;
- traversal, bytes NUL y segmentos inválidos se rechazan;
- el resultado debe existir físicamente;
- `realpath()` debe permanecer dentro de uno de los roots aprobados;
- un filtro no puede escapar de child theme, parent theme o `plugin/templates`;
- el resolver falla cerrado ante un path inválido.

### Filtros

`wla_inmo_template_candidates`

Permite intervenir la lista de candidatos, pero PR 4.1 conserva únicamente el candidato canónico `wla-inmo/<template>`.

`wla_inmo_template_path`

Permite intervenir el path resuelto. El resultado vuelve a pasar por confinamiento y validación de archivo real; no es un bypass de seguridad.

## Routing público

Clase: `WLA\Inmo\Frontend\Bootstrap`.

Hooks:

- `template_include`, prioridad 99;
- `wp_enqueue_scripts` para assets frontend.

Solo se interceptan:

- archive del CPT WLA Property;
- single del CPT WLA Property.

No se interceptan posts/páginas ajenos, administración, AJAX, REST ni feeds.

## Renderer con scope explícito

Clase: `WLA\Inmo\Frontend\Renderer`.

Uso:

```php
$html = WLA\Inmo\Frontend\Renderer::render(
    'single-property.php',
    array(
        'property_id' => 123,
    )
);
```

Reglas:

- el caller entrega un nombre allowlisted, nunca un path;
- `TemplateResolver` sigue siendo la única autoridad de filesystem;
- los argumentos se exponen al template únicamente como `$wla_args`;
- no se usa `extract()`;
- si el template no puede resolverse, devuelve `null`;
- la salida se captura y retorna como string.

### Filtro de argumentos

`wla_inmo_template_args`

Firma conceptual:

```php
$args = apply_filters('wla_inmo_template_args', $args, $template);
```

Solo un array filtrado reemplaza los argumentos originales.

### Hooks de componente

Antes de incluir el template:

```php
do_action('wla_inmo_before_template', $template, $args);
```

Después de incluirlo:

```php
do_action('wla_inmo_after_template', $template, $args);
```

Estos hooks forman la base de extensibilidad de presentación. PR 4.2+ podrá agregar hooks más específicos para cards, archive, single, galería, mapa y CTA.

## Assets

Clase: `WLA\Inmo\Frontend\Assets`.

CSS:

- handle `wla-inmo-frontend`;
- archivo `assets/css/frontend.css`;
- sin dependencias;
- se carga únicamente en archive/single WLA;
- selectores namespaced bajo clases `wla-inmo*`;
- sin reset global.

JavaScript:

PR 4.1 no distribuye `assets/js/frontend.js` porque todavía no existe una interacción progresiva que lo justifique. No se agrega un bundle vacío.

## Privacidad

Los templates fallback y el módulo Frontend no deben acceder ni exponer directamente:

- `private_address`;
- `internal_notes`;
- identificadores internos no públicos;
- meta keys arbitrarias mediante `get_post_meta()`.

Los PR posteriores deben construir contratos de datos públicos explícitos antes de ampliar el renderizado.

## Compatibilidad

Matriz mínima de PR 4.1:

- WordPress 6.6.2 + PHP 8.1 + MySQL 8;
- WordPress latest + PHP 8.3 + MySQL 8;
- sin dependencia runtime de Elementor/WooCommerce/ACF/jQuery;
- override child → parent → plugin validado mediante fixtures reales de tema.

La matriz ampliada con tema core, tema tercero y WLA Inmo Light se completa progresivamente y se cierra formalmente en PR 4.7/4.8.

## Fuera de alcance de PR 4.1

- cards finales;
- filtros de búsqueda;
- query builder público;
- galería interactiva;
- mapa/Leaflet;
- CTA/leads;
- SEO final;
- theme WLA Inmo Light;
- migración a producción.

## Producción

`propiedadesmartinez.cl` no se modifica durante Fase 4. La migración productiva permanece reservada para Fase 9 o una instrucción explícita posterior.
