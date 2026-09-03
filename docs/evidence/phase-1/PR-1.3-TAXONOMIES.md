# Evidencia — Fase 1 / PR 1.3 Taxonomías base

Estado documental: `DONE`.

Issue: #9 — cerrada  
PR: #10 — squash merge  
Merge commit: `61954bfbab9827b6d07d6b6151b9095677951dee`

## Objetivo

Registrar clasificaciones inmobiliarias nativas y reutilizables sobre `wla_property`, sin `product_cat` ni WooCommerce.

## Taxonomías incluidas

| Taxonomía | Uso | Jerárquica | Rewrite inicial | REST base |
|---|---|---:|---|---|
| `wla_operation` | venta/arriendo/u otras operaciones futuras | No | `operacion` | `wla-operations` |
| `wla_property_type` | casa/departamento/terreno/etc. | Sí | `tipo` | `wla-property-types` |
| `wla_region` | región | No | `region` | `wla-regions` |
| `wla_commune` | comuna | No | `comuna` | `wla-communes` |
| `wla_sector` | sector/barrio | No | `sector` | `wla-sectors` |

No se crearon términos predeterminados; Chile sigue siendo un preset desacoplado reservado para PR 1.7.

## Capabilities

`Taxonomies\\Capabilities` define:

- `manage_wla_property_terms`;
- `edit_wla_property_terms`;
- `delete_wla_property_terms`;
- `assign_wla_property_terms`.

La asignación a roles continúa correctamente reservada para PR 1.6.

## Lifecycle

- CPT: `init` prioridad 5.
- Taxonomías: `init` prioridad 6.
- Activación registra CPT + taxonomías antes del único flush.
- Desactivación las unregister antes del flush.
- No hay `flush_rewrite_rules()` en requests normales.

## QA final

Workflow: `Bootstrap Smoke`  
Run: `33818338049`  
Resultado: `SUCCESS`

Pasaron:

- PHP syntax;
- requirements smoke;
- post type smoke;
- taxonomy smoke;
- build ZIP;
- release ZIP smoke;
- Composer autoload;
- artifact upload.

## Artefacto

- Nombre: `wla-inmo-0.1.0-alpha.1`
- Artifact ID: `9917328550`
- Tamaño: `18883` bytes
- Digest: `sha256:67c1e9ba7be704e3622e978ef979d3a8b9e67b9bc84478ea33f30c2b7514a191`

## Riesgo / producción

No se crearon términos, metadatos, tablas ni migraciones. Producción no fue afectada.

## Cierre

PR 1.3 completada y auditada. El siguiente alcance es PR 1.4 — meta schema canónico y validación.
