# Fase 4 / PR 4.2 — Archivo, tarjetas y paginación

Estado: `IN_PROGRESS / QA_PENDING`

- Issue: #76
- Pull Request: #77
- Rama: `feat/phase4-archive-property-cards`
- Base: `main` (tras merge de PR #75)
- Producción: `NO AFECTADA`

## Alcance implementado

- `Frontend/PropertyCard.php`: proyección explícita de lectura de campos públicos; permite solo publicaciones `publish` del CPT `wla_property`.
- `Frontend/ArchiveQuery.php`: limita el archivo principal a 12 registros por página, orden estable, paginación real y prefetch de meta/términos.
- `templates/parts/property-card.php`: tarjeta accesible con imagen, precio CLP/UF/USD, operación, tipo, comuna/sector y características disponibles.
- `templates/archive-property.php`: loop SSR con partial, hooks y paginación estándar.
- `TemplateResolver`: agrega solo `parts/property-card.php` a la allowlist, sin permitir rutas arbitrarias.
- `assets/css/frontend.css`: tarjetas responsive 1/2/3 columnas, CSS scoped, foco visible y variables personalizables.

## Política de privacidad

- No se entrega al partial `private_address`, `internal_notes`, `external_id`, `home_order` ni otro dato interno.
- La lectura de metadata se restringe a claves conocidas de `MetaSchema`; la plantilla no accede directamente a postmeta.
- Borradores y publicaciones privadas no entran a la proyección pública.
- `hide_price` y `price_on_request` muestran 'Precio a consultar' sin exponer el importe.
- HTML escapado por contexto; imagen pasada por `wp_kses_post`, urls por `esc_url`.

## QA implementado

- Release ZIP smoke amplía manifest y lint de archivos de frontend.
- PHPStan Frontend incluye clases nuevas y stubs de funciones de lectura WordPress.
- `tests/integration/assert-frontend-archive.php` verifica publicación versus borrador, privacidad, precio, render y límites de consulta.
- Workflow de integración reutiliza matriz WP 6.6.2/PHP 8.1 y WP latest/PHP 8.3.
- Se preservan las regresiones de Fase 1–3; ningún test se omite para mejorar el resultado.

## Gates pendientes antes de merge

- [ ] Frontend Foundation Integration verde en ambas matrices para el último head.
- [ ] PHPStan/WPCS, release smoke y Phase 1/3 Gate verdes.
- [ ] Revisar que no existan P0/P1 abiertos.
- [ ] Registrar run IDs, SHA head validado y artefacto instalable.
- [ ] Validar manualmente UI en un sitio de pruebas antes de uso comercial.

## Fuera de alcance

- Filtros GET y buscador (4.3).
- Ficha pública rica/galería (4.4), mapa (4.5), CTA/leads (4.6/Fase 7).
- Diseño de tema de referencia (Fase 5), SEO final (Fase 6) y migración productiva (Fase 9).

## Producción

`propiedadesmartinez.cl` continúa intacto. Los cambios existen solo en GitHub/CI y entornos temporales.
