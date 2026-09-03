# ADR-002 — Property, fuente de verdad, índice, taxonomías y precios

Estado: accepted  
Fecha: 2026-09-03  
Decisiones: D05–D14  
Issue: #2

## Contexto

El sitio histórico mezcla productos WooCommerce, ACF y textos manuales. WLA Inmo necesita un modelo consistente, escalable y auditable.

## Decisión

- Entidad principal: CPT `wla_property`.
- El registro canónico vive en WordPress; una tabla `wp_wla_property_index` actúa como proyección optimizada, nunca como fuente de verdad.
- Taxonomías base: operación, tipo, región, comuna y sector. Características adicionales se modelan como campo o taxonomía según necesidad real de navegación/filtro/SEO.
- `property_code` es único cuando exista. `external_id` es independiente y puede identificar registros de sistemas externos.
- Estados comerciales: configurables, con base Disponible/Reservada/Vendida/Arrendada/No disponible.
- Operaciones: Venta y Arriendo como base extensible.
- Precios: CLP, UF y USD pueden almacenarse; existe una moneda principal.
- Conversiones monetarias son derivadas y opcionales.
- `price_on_request` representa explícitamente “precio a consultar”.

## Alternativas consideradas

- Solo `postmeta`: más simple, pero menos eficiente para filtros a escala.
- Tabla propia como registro principal: más rápida, pero pierde integración natural con WordPress y aumenta complejidad editorial.
- Reutilizar WooCommerce: mantiene dependencias y semántica incorrecta de producto.

## Consecuencias

### Positivas
- Una sola fuente de verdad por dato.
- Buen rendimiento de búsqueda sin sacrificar WordPress nativo.
- Importaciones idempotentes y auditables.

### Trade-offs
- La proyección debe sincronizarse y poder reconstruirse.
- Requiere migraciones de esquema controladas.

## Impacto

- Datos: contrato canónico claro.
- Performance: consultas filtrables indexables.
- SEO/GEO/AEO: taxonomías y campos consistentes.
- Migración: mapeo explícito desde Woo/ACF.

## Revisión futura

Revisar índices físicos después de benchmarks reales y patrones de consulta de catálogo.