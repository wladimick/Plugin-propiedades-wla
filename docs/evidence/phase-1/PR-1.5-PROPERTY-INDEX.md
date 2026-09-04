# Evidencia — Fase 1 / PR 1.5 Índice de búsqueda

Estado documental: `DONE`.

Issue: #13  
PR: #14  
Rama: `feat/phase1-property-index`  
Squash merge: `01560207b4872aa73d58e23f5ee2a58adfa6d0be`

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

## QA automático final

Workflow run: `33824375224`  
Job: `PHP 8.1 / Build Smoke`  
Resultado: `SUCCESS`

Pasaron Composer validation, PHP syntax, todos los smoke tests, build ZIP, release smoke, Composer autoload y publicación del artifact.

## Artefacto final

- Artifact ID: `9919396460`
- Nombre: `wla-inmo-0.1.0-alpha.1`
- Tamaño: `30129` bytes
- Digest: `sha256:a6602413d78624f5fe17ac7f77d0f06a013f4f4a81c23261958e10f499dbfe6f`
- Expira: 2026-12-03

## Producción

No afectada. No se ejecutó instalación ni migración sobre Propiedades Martínez.

## Cierre

PR #14 fue integrada mediante squash merge con CI verde. La tabla continúa siendo una proyección reconstruible y no una fuente de verdad.
