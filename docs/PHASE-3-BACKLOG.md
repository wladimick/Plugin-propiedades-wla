# Fase 3 — Import/Export

Estado: `IN_PROGRESS`  
Dependencias: Fase 1 `DONE`, Fase 2 `DONE`  
Issue de entrada: #43  
Versión de entrada: `0.1.0-alpha`

## Objetivo

Construir un sistema de importación y exportación seguro, repetible y usable para cientos o miles de propiedades, sin convertir un archivo externo en una vía paralela que evite las reglas canónicas de WLA Inmo.

La Fase 3 reutiliza `MetaSchema`, `Sanitizer`, `Validator`, taxonomías, capabilities, Search, Quality y Activity. Importar no significa escribir postmeta directamente sin pasar por contratos de dominio.

Fuente funcional: `docs/IMPORT-EXPORT.md`.

## Estado actual

La planificación original fue refinada durante implementación. Persistencia, executor y runner se separaron antes de exponer UI. Issue #56 fijó la numeración canónica vigente.

| PR | Alcance | GitHub | Estado |
|---|---|---|---|
| 3.1 | Dominio de importación + CSV foundation | #46 | DONE |
| 3.2 | Mapping + validación + dry-run | #49 | DONE |
| 3.3 | Persistencia de identidad y batches reanudables | #51 | DONE |
| 3.4 | Executor idempotente de filas | #53 | DONE |
| 3.5 | Runner reanudable de batches | #55 | DONE |
| 3.6 | UI Importar + historial de batches | #57 | DONE |
| 3.7 | JSON WLA versionado | #60 / #58 | DONE |
| 3.8 | XLSX streaming + ADR/benchmark | pendiente | NEXT |
| 3.9 | Media remota segura | pendiente | PLANNED |
| 3.10 | Exportación CSV/XLSX | pendiente | PLANNED |
| 3.11 | Rollback seguro de importación | pendiente | PLANNED |
| 3.12 | Quality Gate Fase 3 | pendiente | PLANNED |

## Principios no negociables

1. **Dry-run antes de confirmar.** Una simulación no crea posts, attachments, términos ni descarga archivos remotos.
2. **Identidad explícita.** `(source_key, external_id)` tiene prioridad; luego `property_code`; nunca título o dirección por sí solos.
3. **Origen aislado.** Un `external_id` siempre se interpreta dentro de un `source_key`.
4. **Vacíos seguros.** Por defecto, vacío conserva valor existente. Borrar exige política explícita.
5. **Batches.** Nunca procesar un archivo grande en un único request.
6. **Resume seguro.** Solo desde un checkpoint consistente.
7. **Idempotencia.** Reintentos no deben crear duplicados silenciosos.
8. **Sin side effects ocultos.** Search, Quality y Activity se sincronizan de forma observable.
9. **Media remota separada.** No forma parte del dry-run.
10. **Errores por fila.** Un dato inválido no corrompe el batch completo.
11. **Sin fórmulas ejecutables.** Exportaciones neutralizan spreadsheet/formula injection.
12. **Permisos reales.** Capability + nonce + autorización por objeto/operación.
13. **Sin dependencia de producción.** Fixtures sintéticos y WordPress limpio.
14. **No adelantar Fase 9.** El migrador Woo/ACF/Propiedades Martínez permanece separado.
15. **Un pipeline.** CSV, JSON, XLSX y futuros formatos solo transforman a filas normalizadas; no duplican mapping/upsert.

## Estados de batch

```text
uploaded
  ↓
mapped
  ↓
validated
  ↓
dry_run_ready
  ↓
confirmed
  ↓
processing
  ├──> paused
  ├──> failed
  └──> completed
```

Estados terminales adicionales solo cuando tengan semántica demostrable: `cancelled`, `rolled_back`, `rollback_blocked`.

## Identidad y conflictos

Identidad primaria:

```text
(source_key, external_id)
```

Fallback permitido:

```text
property_code
```

Nunca se deduce identidad desde título, dirección, comuna + precio, slug ni posición de fila. El dry-run debe expresar `new`, `update`, `duplicate_in_file`, `identity_conflict`, `invalid` y `warning` sin elegir silenciosamente el primer resultado.

## Semántica de vacíos

Default:

```text
vacío → conservar valor actual
```

Borrar requiere política explícita y debe quedar registrada en batch/dry-run.

## Taxonomías desconocidas

Default seguro:

- no crear términos automáticamente durante importación;
- reportar error/advertencia según campo;
- permitir mapping explícito a términos existentes;
- cualquier creación automática futura requiere capability y decisión documentada.

## PR 3.1 — Dominio de importación + CSV foundation

Estado: `DONE`. PR #46.

Incluye dominio `WLA\Inmo\Import`, estados/transiciones, `SourceKey`, identidad read-only, parser CSV incremental UTF-8, delimitadores soportados, headers normalizados, límites de filas/columnas/celda y tratamiento inerte de fórmulas de entrada.

## PR 3.2 — Mapping + validación + dry-run

Estado: `DONE`. PR #49.

Incluye `TargetRegistry`, perfiles versionados, normalización tipada, duplicados intra-file, resolución de identidad, clasificación de resultados, dry-run read-only, taxonomías desconocidas sin creación automática y serialización pública sin meta privada.

Prohibido en dry-run: `wp_insert_post()`, `update_post_meta()`, `wp_set_object_terms()`, descargas remotas y creación automática de términos.

## PR 3.3 — Persistencia de identidad y batches

Estado: `DONE`. PR #51.

Incluye `wla_import_identity`, constraints UNIQUE, `IdentityRepository`, `IdentityIndexer`, tabla `wla_import_batches`, UUID, hash, mapping snapshot, estado, cursor, contadores, timestamps y revision con optimistic locking.

WordPress post/meta sigue siendo fuente canónica.

## PR 3.4 — Executor idempotente de filas

Estado: `DONE`. PR #53.

Incluye re-resolución de identidad antes de escribir, create/update inequívoco, retry idempotente, sanitización canónica, términos previamente resueltos, rollback local y checkpoint solo después de ejecución exitosa.

## PR 3.5 — Runner reanudable de batches

Estado: `DONE`. PR #55.

Incluye `BatchRunner` por slices, SHA-256 + lock, resume por fila/offset/revision, optimistic locking, pausa limpia por presupuesto y WordPress/MySQL integration.

## PR 3.6 — UI Importar + historial

Estado: `DONE`. PR #57 / Issue #56.

Incluye wizard CSV completo, capability + nonces, workspace temporal server-controlled, 10 MiB / 10.000 filas, preview bounded, dry-run obligatorio, confirmación hash/snapshot, processing por `BatchRunner`, cancelación en checkpoints seguros, historial paginado y janitor de drafts.

Evidencia: `docs/evidence/phase-3/PR-3.6-IMPORT-UI.md`.

## PR 3.7 — JSON WLA versionado

Estado: `DONE`. PR #60 / Issue #58.

Decisión final: JSON se valida y normaliza a NDJSON privado; `BatchRunner` y `RowExecutor` permanecen como única vía de ejecución.

Incluye:

- `format_version = 1`;
- fixture v1 versionado UTF-8;
- schema/shape/targets allowlisted;
- límites de bytes, profundidad, propiedades y tamaño de línea;
- fuente NDJSON privada `0600`;
- SHA-256, lock y resume por offset;
- `source_format=json` persistido con schema DB v3 y compatibilidad CSV;
- mismo mapping/validation/dry-run/identidad/runner/executor que CSV;
- UI JSON con capability + nonces;
- export JSON bounded;
- privados excluidos por defecto;
- round-trip;
- negativos de envelope, seguridad, tampering y permisos;
- benchmark 100/1k/5k;
- 3 findings de review corregidos;
- 13/13 workflows verdes en head funcional `0eaeda0f02da44ae25018b6ba167b8b1dddda5a4`.

Evidencia: `docs/evidence/phase-3/PR-3.7-JSON-WLA.md`.

## PR 3.8 — XLSX streaming + ADR de dependencia

Estado: `NEXT`.

Objetivo: añadir XLSX sin degradar memoria, tamaño del ZIP ni seguridad y sin duplicar pipeline.

Antes de implementar la dependencia definitiva:

- comparar al menos dos alternativas razonables;
- medir memoria con 1k/5k y dataset mayor razonable;
- medir peso agregado al ZIP release;
- revisar mantenimiento, licencia y superficie de dependencias;
- evaluar compatibilidad PHP 8.1 y WordPress mínimo;
- documentar ADR final;
- proteger contra ZIP/archive bombs y descompresión no acotada;
- definir límites de sheets/rows/columns/cell bytes;
- rechazar fórmulas/macros/contenido no soportado de forma controlada.

Regla arquitectónica: la librería seleccionada solo transforma XLSX → filas normalizadas. Mapping, validación, dry-run, identidad, executor y runner no se duplican.

### Criterios de aceptación 3.8

- ADR con comparación objetiva de alternativas;
- dependencia con licencia compatible y mantenimiento razonable;
- parser bounded;
- límites antes/durante descompresión;
- XLSX válido → filas canónicas;
- malformado/archive bomb → rechazo controlado;
- datasets 1k/5k medidos;
- ZIP release comparado antes/después;
- regresión CSV + JSON completa;
- WordPress 6.6.2/PHP 8.1 y latest/PHP 8.3 verdes;
- evidencia `QA_PASSED / READY_TO_MERGE` antes de merge.

## PR 3.9 — Media remota segura

Estado: `PLANNED`.

Objetivo: importar imágenes después de resolver propiedad sin convertir el plugin en SSRF proxy.

Incluye allowlist de esquemas, resolución DNS/IP, bloqueo de rangos privados/reservados/cloud metadata, revalidación de redirects, timeout/bytes/MIME, máximo de imágenes, deduplicación, retries limitados y ninguna descarga en dry-run.

## PR 3.10 — Exportación CSV/XLSX

Estado: `PLANNED`.

Incluye filtros, CSV UTF-8, XLSX con dependencia aprobada en 3.8, streaming/chunks, neutralización de formula injection, privados excluidos por defecto y pruebas de caracteres internacionales/saltos/delimitadores.

## PR 3.11 — Rollback seguro

Estado: `PLANNED`.

Revertir únicamente cuando WLA pueda demostrar que no pisa trabajo posterior. Incluye objetos creados por batch, snapshots mínimos, detección de cambios posteriores, preview, capability/confirmación avanzada y Activity.

No prometer rollback total cuando no pueda demostrarse seguridad.

## PR 3.12 — Quality Gate Fase 3

Estado: `PLANNED`.

Debe cubrir regresión Fase 1/2, formatos CSV/JSON/XLSX, archivos malformados, encoding, duplicados/conflictos, stale dry-run, resume/idempotencia, datasets 100/1k/5k, peak memory, límites, formula injection, SSRF, capability/nonce/IDOR, round-trip, rollback, accesibilidad/responsive, artifact/checksum y evidencia final.

## Persistencia de batches

El modelo debe representar como mínimo UUID, tipo/formato, source key, usuario creador, estado, referencia/hash segura, mapping profile/version, política de vacíos, contadores, cursor/checkpoint, timestamps, versión de contrato, dry-run confirmado y error resumido sin payload privado.

## Seguridad específica

### Archivos

- extensión/MIME cuando corresponda;
- nombre generado por servidor;
- sin paths entregados por usuario;
- path traversal bloqueado;
- tamaño máximo antes de parsear;
- límites de filas/columnas/celda;
- protección archive-bomb en XLSX/ZIP.

### CSV / spreadsheet

- nunca ejecutar contenido;
- fórmulas de entrada son datos;
- export neutraliza formula injection;
- encoding inválido se reporta.

### JSON

- tamaño/profundidad/count limits;
- schema/shape allowlisted;
- claves desconocidas no se convierten en meta arbitraria;
- fuente normalizada privada y hash-verificada.

### Media remota

- SSRF protection antes/después de redirects;
- bloqueo localhost/private/link-local/cloud metadata;
- límite de redirects;
- MIME/bytes reales;
- sin SVG remoto en primera implementación salvo decisión posterior.

## Performance budgets iniciales

No son SLA productivos; son guards/evidencia de CI.

- parser de formatos con memoria bounded cuando sea técnicamente razonable;
- dry-run 5k sin N+1 evitable;
- batches pequeños configurables;
- no `get_posts(-1)` ni catálogo completo en memoria;
- Search/Quality incremental;
- historial paginado;
- UI no renderiza miles de errores simultáneamente.

## Observabilidad y evidencia

Cada PR funcional registra requirement/issue/PR, fixtures, formatos/tamaños, mutaciones esperadas, conteos, errores/warnings, memory/query/time cuando aplique, security negatives y artifact/checksum.

Evidencia bajo `docs/evidence/phase-3/`.

## Fuera de alcance

Fase 3 no implementa frontend público final (Fase 4), WLA Inmo Light (Fase 5), SEO completo (Fase 6), leads/indicadores (Fase 7), hardening global final (Fase 8) ni migrador específico WooCommerce/ACF/WPCode de Propiedades Martínez (Fase 9).

## Quality Gate de salida

Fase 3 pasa a `DONE` solo cuando:

1. PR 3.1–3.12 aplicables estén mergeadas con evidencia;
2. CSV/JSON/XLSX usen pipeline canónico común;
3. dry-run demuestre cero mutaciones;
4. resume/idempotencia estén probados;
5. no existan findings críticos/altos abiertos;
6. fórmulas/SSRF/archivos maliciosos tengan cobertura negativa;
7. performance/memoria estén documentados;
8. rollback no prometa más de lo demostrable;
9. artifact/checksum final estén registrados;
10. `PROJECT-STATUS.md` esté actualizado;
11. producción siga sin cambios salvo solicitud explícita posterior.
