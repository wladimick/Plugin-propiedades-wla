# Fase 4 — Backlog frontend agnóstico al tema

Estado: `IN_PROGRESS / PR-4.1-FINAL-GATE`

Issue de entrada: #72  
Base de entrada: Fase 3 `DONE` en `main` (`05d92e82bcab0dbcb6b1ebe6fdfbe9635d909c00`).  
Producción: `NO AFECTADA`.

## Objetivo

WLA Inmo debe renderizar un frontend completo y utilizable con cualquier tema WordPress compatible, sin depender de WLA Inmo Light, Elementor, WooCommerce, ACF, WPCode, jQuery o un framework CSS.

El plugin entrega datos, consultas, componentes funcionales, templates fallback, CSS mínimo y APIs/hooks. El tema conserva tipografía, colores, layout global, header/footer y composición visual.

## Decisiones aceptadas que gobiernan Fase 4

No se abre una decisión estructural nueva para iniciar esta fase. Se implementan decisiones ya aprobadas:

- D15: ubicación pública y privada separadas;
- D16: mapas mediante adapter desacoplado;
- D17: OpenStreetMap + Leaflet como referencia inicial;
- D20: plugin = datos/lógica/API; tema = presentación;
- D21: templates fallback neutrales dentro del plugin;
- D22: overrides `theme-child → theme → plugin` bajo `wla-inmo/`;
- D23: Gutenberg progresivo, no dependencia del core;
- D24: shortcodes de compatibilidad, no API única;
- D25: Vanilla JS + progressive enhancement;
- D62: WCAG 2.2 AA;
- D63: performance budget obligatorio;
- D64–D66: objetivos de calidad y matriz automatizada;
- D74: WLA Inmo Light es opcional;
- D75: QA de independencia con tema WLA Inmo Light, tema core y tema de terceros.

Cualquier desviación estructural requiere ADR/decisión de reemplazo; no puede aparecer silenciosamente en un PR de implementación.

## Contrato frontend transversal

### Renderizado

- server-rendered por defecto;
- JavaScript progresivo y no requerido para leer contenido;
- HTML semántico;
- output escaping tardío según contexto;
- sin endpoints mutables nuevos durante esta fase salvo decisión explícita posterior.

### Templates

Precedencia canónica:

1. tema hijo: `wla-inmo/...`;
2. tema padre/activo: `wla-inmo/...`;
3. template interno del plugin.

El request público nunca proporciona un path de template. Los nombres/rutas resolubles son allowlisted por código y deben quedar dentro de roots esperados.

### CSS

- prefijo/namespacing `wla-inmo-`;
- sin reset global;
- no estilizar globalmente `body`, `h1`, `a`, `button`, etc.;
- custom properties para personalización;
- assets cargados solo en superficies WLA cuando sea técnicamente razonable;
- el tema puede complementar/reemplazar presentación sin romper funcionalidad.

### JavaScript

- Vanilla JS;
- sin jQuery obligatorio;
- sin SPA/bundle global;
- filtros, galería y mapa deben degradar de forma usable si JS falla.

### Privacidad

- templates públicos solo reciben datos públicos/canónicos necesarios;
- `private_address`, `internal_notes` y otros targets privados no se exponen;
- mapa usa únicamente coordenadas/ubicación pública permitida;
- ninguna API de presentación debe facilitar meta keys arbitrarias.

## Secuencia de PRs

| PR | Alcance | Estado | Issue/Evidencia |
|---|---|---|---|
| 4.1 | Frontend foundation / template resolver / assets | FINAL_GATE_PENDING | #75 / #74 / `docs/evidence/phase-4/PR-4.1-FRONTEND-FOUNDATION.md` |
| 4.2 | Archive + property card + pagination | PLANNED | pendiente |
| 4.3 | Search + filtros GET | PLANNED | pendiente |
| 4.4 | Single + detalles + galería | PLANNED | pendiente |
| 4.5 | Mapa + privacidad de ubicación | PLANNED | pendiente |
| 4.6 | CTA + API de presentación | PLANNED | pendiente |
| 4.7 | Theme overrides + compatibility matrix | PLANNED | pendiente |
| 4.8 | Quality Gate final Fase 4 | PLANNED | pendiente |

## PR 4.1 — Frontend foundation / template resolver / assets

Estado: `FINAL_GATE_PENDING` — PR #75 / Issue #74.

Documentación de integración: `docs/FRONTEND.md`.  
Evidencia: `docs/evidence/phase-4/PR-4.1-FRONTEND-FOUNDATION.md`.

### Objetivo

Crear la infraestructura frontend común sin adelantar archive/single completos.

### Alcance

- namespace/módulo `Frontend` desacoplado de Admin/Import;
- `TemplateResolver` con precedencia child → parent → plugin;
- lista explícita de templates/partials resolubles;
- normalización y rechazo de traversal/paths no permitidos;
- integración controlada con `template_include` únicamente en consultas WLA;
- renderer de partials/templates con argumentos explícitos y scope local `$wla_args`;
- hooks públicos base `wla_inmo_before_template` / `wla_inmo_after_template`;
- filtro de argumentos `wla_inmo_template_args`;
- registro/enqueue condicional de CSS/JS;
- hoja CSS funcional mínima, namespaced y sin resets;
- JS bootstrap mínimo solo si existe una función progresiva real; no agregar JS vacío por conveniencia;
- pruebas con overrides child/parent reales y fallback plugin;
- documentación de integración inicial.

### Fuera de alcance

- diseño final de archive;
- cards completas;
- filtros de negocio;
- galería interactiva final;
- mapa Leaflet;
- leads/formularios;
- SEO final;
- WLA Inmo Light;
- migración/producción.

### Tests mínimos

- fallback interno cuando no existe override;
- override tema padre;
- override tema hijo tiene precedencia;
- path desconocido/traversal rechazado;
- path filtrado fuera de roots rechazado;
- renderer no acepta path arbitrario y no hace `extract()`;
- hooks before/after ejecutan alrededor del template;
- `template_include` no intercepta posts/páginas ajenos;
- activación sin Elementor/Woo/ACF/jQuery;
- assets no cargados en páginas ajenas;
- assets cargados en superficies WLA;
- CSS smoke: sin selectores globales prohibidos;
- PHP 8.1 / WP 6.6.2 y PHP 8.3 / WP latest;
- release ZIP contiene templates/assets/clases necesarios;
- regresiones Fases 1–3 verdes antes de merge.

## PR 4.2 — Archive + card + paginación

- `archive-property.php` fallback;
- `parts/property-card.php`;
- query pública bounded/paginada;
- preload/caches para evitar N+1;
- datos públicos únicamente;
- empty state;
- hooks before/after card;
- HTML semántico y responsive base.

## PR 4.3 — Search + filtros GET

- formulario SSR usable sin JS;
- parámetros GET allowlisted y normalizados;
- query builder separado de template;
- operación/tipo/ubicación + filtros numéricos soportados por contrato/índice;
- filtros desconocidos fail-safe;
- paginación conserva filtros;
- progressive enhancement opcional;
- SEO/noindex de combinaciones se termina en Fase 6.

## PR 4.4 — Single + detalles + galería

- `single-property.php` fallback;
- `property-details.php`;
- precio/operación/estado/taxonomías públicas;
- featured image/galería desde attachments;
- videos permitidos;
- galería Vanilla JS con fallback legible;
- cero exposición de ubicación/notas privadas.

## PR 4.5 — Mapa + privacidad

- adapter desacoplado;
- OSM + Leaflet de referencia;
- exactitud/aproximación según datos públicos;
- no renderizar datos privados en HTML/JS;
- assets de mapa solo donde exista mapa;
- degradación razonable sin JS/servicio.

## PR 4.6 — CTA + API de presentación

- `property-contact.php` como slot/superficie CTA;
- hooks/filtros públicos documentados;
- helpers PHP mínimos;
- shortcodes de compatibilidad solo si aportan integración real;
- sin almacenamiento de leads, rate limiting ni antispam: Fase 7.

## PR 4.7 — Theme overrides + compatibility matrix

- fixtures para child/parent/plugin override;
- tema core WordPress;
- tema de terceros razonable;
- WLA Inmo Light solo como referencia si ya existe una versión utilizable, sin bloquear la fase;
- sin Elementor/WooCommerce/ACF/jQuery;
- colisiones CSS/layout;
- documentación para integradores.

## PR 4.8 — Quality Gate Fase 4

Gate transversal para:

- archive/single/cards/search/filtros/paginación/galería/mapa/CTA;
- theme overrides;
- privacidad;
- teclado y WCAG 2.2 AA;
- responsive 360/390/768/1024/1440;
- axe sin serious/critical en superficies propias cubiertas;
- performance y presupuesto de assets;
- WP/PHP mínimo + latest;
- tema core + tercero;
- release ZIP/artifact/checksum;
- regresiones Fase 1–3;
- review con P0/P1 = 0.

Fase 4 cambia a `DONE` únicamente después del merge de 4.8 y cierre documental post-merge si corresponde.

## Quality gates comunes

Cada PR debe cumplir cuando aplique:

- PHP syntax;
- WPCS/security profile;
- PHPStan en dominio tocado;
- PHPUnit;
- integration WordPress/MySQL;
- smoke sobre ZIP release;
- Playwright/axe cuando haya UI observable;
- documentación/evidencia actualizada;
- review sin P0/P1 abiertos.

## Producción

`propiedadesmartinez.cl` permanece sin cambios. Fase 4 se desarrolla y valida únicamente en repo/CI/entornos de prueba. La migración productiva continúa reservada para Fase 9.
