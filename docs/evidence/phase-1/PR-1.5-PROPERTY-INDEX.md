# Evidencia — Fase 1 / PR 1.5 Índice de búsqueda

Estado documental: `QA_PASSED / MERGE_PENDING`.

Issue: #13  
PR: #14  
Rama: `feat/phase1-property-index`

## Objetivo

Crear `wp_wla_property_index` como proyección reconstruible para búsquedas rápidas, manteniendo WordPress como fuente de verdad.

## Componentes

- `Core\\Installer` — schema versionado con `dbDelta` solo cuando corresponde.
- `Search\\IndexSchema` — definición SQL.
- `Search\\Projection` — post + meta canónico + taxonomías → fila derivada.
- `Search\\IndexRepository` — persistencia segura con `$wpdb`.
- `Search\\Indexer` — sincronización incremental consolidada por request.
- `Search\\Rebuilder` — reconstrucción por lotes.

## Integridad

- `property_id` PRIMARY KEY.
- `property_code` UNIQUE cuando no es NULL.
- códigos vacíos → NULL.
- no se usa SQL `REPLACE` destructivo.
- un código duplicado se rechaza sin eliminar la fila original.
- propiedades no publicadas salen del índice.
- la tabla no se edita desde UI/API.

## Performance

Índices iniciales: `(operation_slug,status)`, `type_slug`, `commune_slug`, `price_clp`, `(featured,updated_at)`.

Los cambios de post, meta y taxonomías se agrupan para sincronizar una sola vez por propiedad al final del request.

## QA automático

Workflow run: `33824308053`  
Job: `PHP 8.1 / Build Smoke`  
Resultado: `SUCCESS`

Pasaron Composer validation, PHP syntax, todos los smoke tests, build ZIP, release smoke, Composer autoload y publicación del artifact.

`tests/smoke/search-index.php` cubre schema, proyección, drafts, normalización, upsert, conflictos de código, delete/reset, hooks, `deleted_post_meta`, despublicación y rebuild reanudable.

## Artefacto

- Artifact ID: `9919375168`
- Nombre: `wla-inmo-0.1.0-alpha.1`
- Tamaño: `30129` bytes
- Digest: `sha256:f97a2b70695b1abba961b3b42a4fa68ed6710e4d6a638191066751ef797e22a1`
- Expira: 2026-12-03

## Producción

No afectada. No se ejecutó instalación ni migración sobre Propiedades Martínez.

## Cierre

QA requerido para merge aprobado. Después del squash merge, PR #14 será la evidencia canónica y el siguiente alcance será PR 1.6 — roles y capabilities.
