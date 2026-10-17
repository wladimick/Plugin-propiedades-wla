# Phase 4.3 — Búsqueda y filtros públicos GET

Estado: IN_PROGRESS / QA_PENDING
Issue: #78
Rama: feat/phase4-search-filters
Base: main tras 4.2 (#77, squash 96950721)
Producción: NO AFECTADA.

## Diseño de seguridad y alcance

- Contrato explícito de once parámetros: q, operacion, tipo, region, comuna, sector, moneda, precio_min, precio_max, dormitorios, banos y superficie_min (doce parámetros).
- GET funciona sin JavaScript; no endpoints ni persistencia adicionales.
- Entradas no escalares, valores desconocidos y slugs no normalizados quedan descartados; búsqueda textual acotada a 100 caracteres.
- Límites numéricos bounded con regex; se normalizan rangos invertidos.
- Condiciones creadas mediante tax_query y meta_query estándar de WordPress. No SQL concatenado con datos de request.
- Exclusión de precios privados (hide_price / price_on_request) en filtros numéricos para evitar inferencia.
- Sin modificar consultas de otros CPT, REST, administración ni secundarios. Paginación y orden preservan la política de ArchiveQuery.
- Los filtros y la paginación conservan solo valores ya normalizados (add_args). Campos opcionales con labels y foco visible.
- No depende de Elementor, jQuery, WooCommerce ni ACF.

## QA a completar

- [ ] PHPStan, WPCS, release smoke y workflow Frontend WordPress 6.6.2/PHP 8.1 y latest/PHP 8.3.
- [ ] Unitarias: filtros válidos, desconocidos, maliciosos, límites y rangos invertidos.
- [ ] Integración: consulta no filtrada, combinación tax + precio, campos numéricos, hidden price, paginación GET.
- [ ] Calidad de consultas/performance: medir impacto de tax_query/meta_query vs índice canónico wla_property_index; estudiar mejora indexada antes de 1.0 sin omitir publicaciones no indexadas.
- [ ] Validación responsive, teclado y axe en staging antes de uso comercial; no declarar esas pruebas superadas por el solo CI.
- [ ] Regresiones fase 1-4.2 y 0 P0/P1.

## Fuera de alcance

SEO/noindex canónicos finales: Fase 6. Galería 4.4, mapas 4.5, leads 4.6/Fase 7, producción y migración Fase 9.
