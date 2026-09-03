# Evidencia — Fase 1 / PR 1.3 Taxonomías base

Estado documental: `IN_PROGRESS`.

Issue: #9  
Rama: `feat/phase1-taxonomies`

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

No se crean términos predeterminados en esta PR. Chile será un preset desacoplado en PR 1.7.

## Capabilities

`Taxonomies\\Capabilities` define:

- `manage_wla_property_terms`;
- `edit_wla_property_terms`;
- `delete_wla_property_terms`;
- `assign_wla_property_terms`.

La asignación a roles queda para PR 1.6.

## Lifecycle

- CPT: `init` prioridad 5.
- Taxonomías: `init` prioridad 6.
- Activación registra CPT + taxonomías antes del único flush.
- Desactivación las unregister antes del flush.
- No hay `flush_rewrite_rules()` en requests normales.

## Tests

`tests/smoke/taxonomies.php` valida:

- cinco claves exactas;
- ausencia de `product_cat`;
- jerarquía;
- slugs;
- REST;
- capabilities;
- asociación exclusiva con `wla_property`;
- ejecución de `register_taxonomy()` mediante stub.

El smoke del ZIP valida archivos y Composer autoload de `Taxonomies\\Registry` y `Taxonomies\\Capabilities`.

## Riesgo / producción

No existen términos precargados, meta schema ni migración. Producción no se toca.

## Cierre

Completar con PR, CI, artifact y digest después de QA/merge.
