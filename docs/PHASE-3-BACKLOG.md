# Fase 3 — Import/Export

Estado: `IN_PROGRESS`  
Dependencias: Fase 1 `DONE`, Fase 2 `DONE`  
Issue de entrada: #43  
Versión de entrada: `0.1.0-alpha`

## Objetivo

Construir un sistema de importación seguro, repetible y usable para cientos o miles de propiedades, manteniendo JSON WLA como formato portable de intercambio, sin convertir un archivo externo en una vía paralela que evite las reglas canónicas de WLA Inmo.

La Fase 3 reutiliza `MetaSchema`, `Sanitizer`, `Validator`, taxonomías, capabilities, Search, Quality y Activity. Importar no significa escribir postmeta directamente sin pasar por contratos de dominio.

Fuente funcional: `docs/IMPORT-EXPORT.md`.

## Estado actual

La planificación original fue refinada durante implementación. Persistencia, executor y runner se separaron antes de exponer UI. Issue #56 fijó la numeración canónica vigente. El 2026-09-07 el propietario del proyecto retiró la exportación CSV/XLSX del alcance de Fase 3; el número 3.10 se conserva explícitamente para auditoría.

| PR | Alcance | GitHub | Estado |
|---|---|---|---|
| 3.1 | Dominio de importación + CSV foundation | #46 | DONE |
| 3.2 | Mapping + validación + dry-run | #49 | DONE |
| 3.3 | Persistencia de identidad y batches reanudables | #51 | DONE |
| 3.4 | Executor idempotente de filas | #53 | DONE |
| 3.5 | Runner reanudable de batches | #55 | DONE |
| 3.6 | UI Importar + historial de batches | #57 | DONE |
| 3.7 | JSON WLA versionado | #60 / #58 | DONE |
| 3.8 | XLSX streaming + ADR/benchmark | #62 / #61 | DONE |
| 3.9 | Media remota segura | #64 / #63 | DONE |
| 3.10 | Exportación CSV/XLSX | — | OMITTED / OUT_OF_SCOPE |
| 3.11 | Rollback seguro de importación | pendiente | NEXT |
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
11. **Sin fórmulas ejecutables.** Fórmulas en archivos CSV/XLSX de entrada son datos inertes; exportación CSV/XLSX está fuera del alcance de Fase 3.
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

Estado: `DONE`. PR #62 / Issue #61. Squash `a51cb361f4534935f13c94c72b5d961a88f7a743`.

Decisión: **PhpSpreadsheet 3.10.7 exacta en lock**, implementando D31 mediante ADR-014 y lectura bounded por chunks de 500 filas.

Incluye:

- comparación reproducible OpenSpout 4.24.5 / PhpSpreadsheet 3.10.7 / 5.8.1;
- `composer.lock` runtime/tooling reproducible;
- inspección ZIP/OOXML antes del reader;
- límites de bytes comprimidos/descomprimidos, entries, ratio, sheets, rows, columns y cell bytes;
- bloqueo de path traversal/Zip Slip, macros/binarios y relationships externos;
- selección explícita de hoja;
- normalización XLSX → NDJSON privado;
- permisos temporales `0600` fail-closed;
- janitor de uploads XLSX abandonados;
- `source_format=xlsx` persistido en batch/historial;
- mismo mapping, `DryRunEngine`, identidad, `BatchRunner` y `RowExecutor` de CSV/JSON;
- UI XLSX integrada al importador existente;
- historial XLSX filtrado en su pestaña;
- PHPUnit XLSX, PHPStan, build/smoke y matriz PHP 8.1/8.3;
- integración WordPress para handler, cleanup e historial;
- benchmark 1k/5k y artifacts de decisión documentados;
- 15/15 workflows finales verdes antes del merge.

Evidencia: `docs/evidence/phase-3/PR-3.8-XLSX.md`.

### Criterios de aceptación 3.8

- ADR con comparación objetiva de alternativas — DONE;
- dependencia con licencia compatible y mantenimiento razonable — DONE;
- parser bounded — DONE;
- límites antes/durante descompresión — DONE;
- XLSX válido → filas canónicas — DONE;
- malformado/archive bomb → rechazo controlado — DONE;
- datasets 1k/5k medidos — DONE;
- ZIP release comparado antes/después — DONE;
- regresión CSV + JSON completa — DONE;
- WordPress 6.6.2/PHP 8.1 y latest/PHP 8.3 — DONE;
- evidencia QA previa a merge — DONE.

## PR 3.9 — Media remota segura

Estado: `DONE`. PR #64 / Issue #63. Squash `1067d227ac0dea5b1a8a248b57cd217490e4031e`.

Objetivo cumplido: importar imágenes después de resolver/persistir la propiedad sin convertir el plugin en SSRF proxy.

Incluye:

- `media.gallery_urls` y `media.featured_image_url` como targets portables, nunca `gallery_ids` externos;
- cero HTTP durante dry-run;
- SSRF policy para scheme/host/port/DNS A+AAAA/IP y redirects mediante WordPress safe HTTP API;
- streaming bounded a temporales `0600`;
- 10 MiB/imagen, timeout 15 s, máximo 3 redirects y 20 imágenes;
- JPEG/PNG/WebP; SVG remoto rechazado;
- MIME/firma/dimensiones/píxeles/SHA-256 verificados;
- Media Library con deduplicación WLA por SHA-256;
- source URL original no persistida, solo hash técnico;
- galería y featured image persistidos como referencias WordPress canónicas;
- fallas permanentes de media como warnings;
- fallas transitorias con retry acotado y error sin checkpoint cuando persisten;
- RowExecutor separa `media.*`, hace upsert primero y procesa media después;
- retry posterior re-resuelve identidad para no duplicar propiedades;
- sección `media` en JSON WLA y export JSON desde URLs públicas de attachments canónicos;
- tests unitarios y WordPress integration en PHP 8.1/8.3;
- 13/13 workflows verdes en head funcional y 13/13 nuevamente en head documental final;
- review threads, P0/P1 abiertos al merge: 0.

Evidencia: `docs/evidence/phase-3/PR-3.9-REMOTE-MEDIA.md`.

## PR 3.10 — Exportación CSV/XLSX

Estado: `OMITTED / OUT_OF_SCOPE`.

Decisión de alcance aprobada el 2026-09-07. **No es requisito de salida de Fase 3**. Se conserva el número para auditoría y no se renumeran 3.11/3.12.

- no se implementa export CSV en esta fase;
- no se implementa export XLSX en esta fase;
- JSON WLA export permanece porque fue implementado en 3.7;
- puede reconsiderarse en un release futuro si existe necesidad concreta.

Registro: `docs/decisions/PHASE-3-SCOPE-2026-09-07.md`.

## PR 3.11 — Rollback seguro

Estado: `NEXT`.

Revertir únicamente cuando WLA pueda demostrar que no pisa trabajo posterior. Incluye objetos creados por batch, snapshots mínimos, detección de cambios posteriores, preview, capability/confirmación avanzada y Activity.

No prometer rollback total cuando no pueda demostrarse seguridad.

## PR 3.12 — Quality Gate Fase 3

Estado: `PLANNED`.

Debe cubrir regresión Fase 1/2, formatos CSV/JSON/XLSX, archivos malformados, encoding, duplicados/conflictos, stale dry-run, resume/idempotencia, datasets 100/1k/5k, peak memory, límites, fórmulas de entrada, SSRF, capability/nonce/IDOR, JSON round-trip, rollback, accesibilidad/responsive, artifact/checksum y evidencia final.

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
- encoding inválido se reporta;
- exportación CSV/XLSX fuera del alcance actual.

### JSON

- tamaño/profundidad/count limits;
- schema/shape allowlisted;
- claves desconocidas no se convierten en meta arbitraria;
- fuente normalizada privada y hash-verificada;
- sección `media` portable solo mediante targets allowlisted.

### Media remota

- SSRF protection antes del request y nuevamente en redirects mediante safe HTTP API;
- bloqueo localhost/private/link-local/cloud metadata;
- límite de redirects;
- MIME/bytes/dimensiones reales;
- sin SVG remoto;
- upsert antes de media y checkpoint después de resultado de media.

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

Fase 3 no implementa exportación CSV/XLSX (decisión 2026-09-07), frontend público final (Fase 4), WLA Inmo Light (Fase 5), SEO completo (Fase 6), leads/indicadores (Fase 7), hardening global final (Fase 8) ni migrador específico WooCommerce/ACF/WPCode de Propiedades Martínez (Fase 9).

## Quality Gate de salida

Fase 3 pasa a `DONE` solo cuando:

1. PR 3.1–3.12 **aplicables** estén mergeadas con evidencia; PR 3.10 queda explícitamente excluida por `OMITTED / OUT_OF_SCOPE`;
2. CSV/JSON/XLSX usen pipeline canónico común;
3. dry-run demuestre cero mutaciones;
4. resume/idempotencia estén probados;
5. no existan findings críticos/altos abiertos;
6. fórmulas de entrada, SSRF y archivos maliciosos tengan cobertura negativa;
7. performance/memoria estén documentados;
8. rollback no prometa más de lo demostrable;
9. artifact/checksum final estén registrados;
10. `PROJECT-STATUS.md` esté actualizado;
11. producción siga sin cambios salvo solicitud explícita posterior.
