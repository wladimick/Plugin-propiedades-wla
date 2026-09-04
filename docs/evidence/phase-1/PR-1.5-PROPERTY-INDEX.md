# Evidencia — Fase 1 / PR 1.5 Índice de búsqueda

Estado documental: `IN_PROGRESS`.

Issue: #13  
Rama: `feat/phase1-property-index`

## Objetivo

Crear `wp_wla_property_index` como proyección reconstruible y optimizada de propiedades publicadas, sin convertir la tabla en fuente de verdad.

## Arquitectura

```text
WordPress post + taxonomías + meta canónico
                 │
                 ▼
        Search\\Projection
                 │
                 ▼
       Search\\IndexRepository
                 │
                 ▼
      wp_wla_property_index
```

La tabla derivada puede eliminarse y reconstruirse sin pérdida de información de negocio.

## Componentes

- `Core\\Installer`: crea/migra el schema mediante `dbDelta` solo cuando corresponde.
- `Search\\IndexSchema`: SQL y versión de esquema.
- `Search\\Projection`: transforma únicamente fuentes canónicas.
- `Search\\IndexRepository`: persistencia de la proyección mediante `$wpdb`.
- `Search\\Indexer`: sincronización incremental y deduplicada por request.
- `Search\\Rebuilder`: reconstrucción paginada/reanudable.

## Integridad

- `property_id` es PRIMARY KEY.
- `property_code` es UNIQUE cuando no es NULL.
- códigos vacíos se proyectan como NULL.
- un conflicto de código es rechazado antes de escribir para evitar semántica destructiva de SQL `REPLACE`.
- la tabla no recibe edición directa desde UI o API.
- propiedades no publicadas se eliminan de la proyección pública.

## Performance

Índices iniciales deliberadamente conservadores:

- `(operation_slug, status)`;
- `type_slug`;
- `commune_slug`;
- `price_clp`;
- `(featured, updated_at)`.

Los índices se revisarán con benchmarks reales antes de 1.0.

Los cambios de post, meta y taxonomías se consolidan en memoria y sincronizan una vez por propiedad al final del request, evitando reindexar repetidamente durante una misma edición.

## Rebuild

`Search\\Rebuilder::batch()` procesa lotes acotados y devuelve `next_page`. La futura herramienta administrativa podrá usar el mismo servicio sin crear un request monolítico.

## Tests definidos

`tests/smoke/search-index.php` valida:

- SQL y tabla namespaced por prefijo WP;
- PRIMARY/UNIQUE/índices iniciales;
- proyección de post/meta/taxonomías;
- exclusión de borradores;
- normalización de precios/coordenadas/destacada;
- upsert seguro;
- rechazo no destructivo de códigos duplicados;
- delete/reset idempotentes;
- hooks de sincronización;
- firma segura de `deleted_post_meta`;
- despublicación elimina la fila;
- rebuild por lotes reanudable.

El smoke del ZIP exige/autoloadea Installer y todas las clases Search incorporadas en esta PR.

## Producción

No afectada. No se ejecuta instalación, migración ni reconstrucción sobre Propiedades Martínez.

## Cierre

Completar después de abrir la PR con CI final, artifact, digest y squash merge.
