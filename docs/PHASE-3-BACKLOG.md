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

La planificación original PR 3.1–3.10 fue refinada durante implementación. El alcance de persistencia/ejecución se dividió en tres PR auditables antes de exponer la UI. Por decisión registrada en Issue #56, la UI pasó a PR 3.6 y los hitos restantes se renumeraron sin cambiar su alcance funcional.

Orden canónico desde este documento:

| PR | Alcance | GitHub | Estado |
|---|---|---|---|
| 3.1 | Dominio de importación + CSV foundation | #46 | DONE |
| 3.2 | Mapping + validación + dry-run | #49 | DONE |
| 3.3 | Persistencia de identidad y batches reanudables | #51 | DONE |
| 3.4 | Executor idempotente de filas | #53 | DONE |
| 3.5 | Runner reanudable de batches | #55 | DONE |
| 3.6 | UI Importar + historial de batches | #57 | DONE |
| 3.7 | JSON WLA versionado | Issue #58 | NEXT |
| 3.8 | XLSX streaming + ADR/benchmark | pendiente | PLANNED |
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

## Estados de un batch

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

Estados terminales adicionales solo cuando tienen semántica demostrable: `cancelled`, `rolled_back`, `rollback_blocked`.

## Identidad y upsert

### Fuente externa

Ejemplos de `source_key`:

```text
portal_proveedor_a
crm_inmobiliaria
carga_manual_2026
```

Identidad primaria:

```text
(source_key, external_id)
```

Fallback permitido:

```text
property_code
```

No se deduce identidad desde título, dirección, comuna + precio, slug ni posición de fila.

### Conflictos

El dry-run debe poder expresar:

- `new`;
- `update`;
- `duplicate_in_file`;
- `identity_conflict`;
- `invalid`;
- `warning`.

Nunca se resuelve un conflicto eligiendo silenciosamente el primer resultado.

## Semántica de vacíos

Default:

```text
vacío → conservar valor actual
```

Una futura política:

```text
vacío → borrar valor actual
```

solo se habilita de forma explícita y queda registrada en el batch/dry-run.

## Taxonomías desconocidas

Default seguro:

- no crear términos automáticamente durante importación;
- reportar error/advertencia según campo;
- permitir mapping explícito a un término existente;
- creación automática futura requiere capability y decisión documentada.

## PR 3.1 — Dominio de importación + CSV foundation

Estado: `DONE`. PR #46.

Incluye:

- namespace `WLA\Inmo\Import`;
- estados/transiciones de batch;
- `SourceKey` normalizado;
- resolución read-only de identidad;
- parser CSV UTF-8 incremental con `SplFileObject` + `Generator`;
- BOM, coma, punto y coma y tab;
- headers normalizados y duplicados rechazados;
- límites de filas/columnas/celda;
- strings tipo fórmula tratados como datos;
- unit/smoke/integration y evidencia.

## PR 3.2 — Mapping + validación + dry-run

Estado: `DONE`. PR #49.

Incluye:

- `TargetRegistry` allowlisted;
- perfiles de mapping versionados;
- normalización y validación tipada;
- duplicados intra-file;
- resolución de coincidencias existentes;
- clasificación `new/update/error/warning`;
- dry-run read-only;
- taxonomías desconocidas sin creación automática;
- serialización pública sin meta privada.

Prohibido en dry-run:

- `wp_insert_post()`;
- `update_post_meta()`;
- `wp_set_object_terms()`;
- descargas remotas;
- creación automática de términos.

## PR 3.3 — Persistencia de identidad y batches reanudables

Estado: `DONE`. PR #51.

Incluye:

- `IdentityMeta`;
- proyección `wla_import_identity` con constraints UNIQUE;
- `IdentityRepository` / `IdentityIndexer`;
- tabla `wla_import_batches`;
- UUID, hash, snapshot de mapping, estado, cursor, contadores y timestamps;
- `revision` con optimistic locking;
- progreso monotónico y transiciones seguras.

WordPress post/meta sigue siendo fuente canónica; las tablas de importación son proyecciones/estado operativo.

## PR 3.4 — Executor idempotente de filas

Estado: `DONE`. PR #53.

Incluye:

- `RowExecutor`;
- re-resolución de identidad inmediatamente antes de escribir;
- create como draft / update del objeto inequívoco;
- retry NEW → MATCH → UPDATE después de crash;
- sanitización mediante `MetaSchema`;
- términos solo previamente resueltos;
- rollback local ante fallas parciales;
- checkpoint solo después de ejecución exitosa;
- errores no avanzan cursor.

## PR 3.5 — Runner reanudable de batches

Estado: `DONE`. PR #55.

Incluye:

- `MappingProfileCodec`;
- `BatchRunner` por slices;
- hash SHA-256 y lectura sobre el mismo handle bloqueado;
- resume mediante `cursor_row`, `cursor_offset` y `revision`;
- optimistic locking por checkpoint;
- pausa limpia por presupuesto de filas/tiempo;
- reintentos idempotentes;
- WordPress/MySQL integration.

## PR 3.6 — UI Importar + historial de batches

Estado: `DONE`. PR #57 / Issue #56.

Squash en `main`: `d983034bb40a369eaf9bebaeef977548ca752e54`.

Wizard:

```text
1. Subir
2. Mapear
3. Validar
4. Simular
5. Confirmar
6. Procesar
7. Informe
```

Incluye:

- `WLA Inmo → Importar / Exportar` deja de ser placeholder;
- capability `import_wla_properties`;
- nonces por mutación;
- CSV únicamente;
- workspace temporal con rutas derivadas de UUID de servidor;
- límite 10 MiB / 10.000 filas;
- preview bounded;
- dry-run obligatorio;
- mapping snapshot + SHA-256 antes de confirmar;
- procesamiento por `BatchRunner`;
- cancelación solo en checkpoints seguros;
- historial bounded/paginado;
- ayuda contextual y CSS responsive;
- `WorkspaceJanitor` para drafts temporales vencidos;
- findings P2 de memoria y limpieza de temporales corregidos;
- review threads abiertos al merge: 0;
- CI, integración y Administration Quality Gate verdes.

Evidencia: `docs/evidence/phase-3/PR-3.6-IMPORT-UI.md`.

## PR 3.7 — JSON WLA versionado

Estado: `NEXT`. Issue #58.

Objetivo: formato interoperable y de respaldo lógico que use el mismo pipeline canónico.

Incluye:

- `format_version`;
- schema/shape allowlisted;
- límites de tamaño y profundidad;
- importación por el mismo mapping/validation/dry-run;
- exportación filtrada;
- campos privados excluidos por defecto y protegidos por capability explícita;
- compatibilidad entre versiones documentada;
- tests round-trip;
- JSON inválido o claves desconocidas se reportan de forma controlada;
- ninguna meta key se construye dinámicamente desde input.

### Decisión arquitectónica de entrada

`BatchRunner` permanece como única vía de ejecución y escritura. JSON no debe introducir un runner paralelo. La implementación de 3.7 debe normalizar el formato externo a una fuente interna reanudable o introducir una abstracción mínima de source reader sin debilitar los checkpoints/hash/idempotencia ya demostrados por CSV. La decisión final debe quedar documentada en la evidencia/ADR del PR antes del merge.

## PR 3.8 — XLSX streaming + ADR de dependencia

Objetivo: añadir XLSX sin degradar memoria, tamaño de ZIP ni seguridad.

Antes de mergear:

- comparar al menos dos alternativas razonables;
- medir memoria con 1k/5k/dataset mayor razonable;
- medir peso agregado al ZIP;
- revisar mantenimiento/licencia/superficie de dependencias;
- documentar ADR final;
- proteger contra archive bombs/descompresión no acotada.

La librería solo transforma XLSX → filas normalizadas. Mapping/upsert no se duplica.

## PR 3.9 — Media remota segura

Objetivo: importar imágenes después de resolver la propiedad sin convertir el plugin en un SSRF proxy.

Incluye:

- allowlist de esquemas;
- resolución DNS/IP y bloqueo de rangos locales/privados/reservados;
- revalidación de redirects;
- timeout y límite de bytes;
- MIME real y extensiones permitidas;
- máximo de imágenes por propiedad/batch;
- deduplicación cuando sea confiable;
- retries limitados;
- attachment IDs canónicos;
- errores de media separados de errores de datos;
- ninguna descarga durante dry-run.

## PR 3.10 — Exportación CSV/XLSX

Objetivo: exportaciones filtradas, bounded y seguras.

Incluye:

- todas/disponibles/operación/tipo/ubicación/fecha/selección;
- CSV UTF-8 documentado;
- XLSX mediante dependencia aprobada en 3.8;
- streaming/chunks;
- neutralización de formula injection para `=`, `+`, `-`, `@` y vectores definidos por contrato;
- campos privados excluidos por defecto;
- pruebas con caracteres internacionales, saltos de línea y delimitadores.

## PR 3.11 — Rollback seguro de importación

Objetivo: revertir únicamente cuando WLA puede demostrar que no pisará trabajo posterior.

Incluye:

- propiedades creadas por batch;
- snapshot mínimo de campos modificados cuando sea razonable;
- detección de cambios posteriores al batch;
- bloqueo de rollback destructivo si existe trabajo posterior no atribuible al batch;
- media exclusiva identificable;
- preview de rollback;
- capability + confirmación avanzada;
- Activity del rollback.

No prometer rollback total cuando no pueda demostrarse seguridad.

## PR 3.12 — Quality Gate Fase 3

Incluye:

- regresión Fase 1/2;
- unit/integration/E2E del wizard y formatos;
- CSV/JSON/XLSX malformados;
- BOM/encoding/delimitadores;
- archivo vacío y headers duplicados;
- columnas/claves desconocidas;
- duplicados e identity conflicts;
- stale dry-run;
- resume/idempotencia;
- datasets 100/1k/5k y mayor razonable;
- peak memory cuando sea medible;
- archivos sobre límites;
- formula injection;
- SSRF/redirections/MIME spoofing;
- capability/nonce/IDOR;
- round-trip import/export;
- rollback permitido/bloqueado;
- accesibilidad/responsive;
- artifact/checksum/evidencia.

## Persistencia de batches

El modelo debe representar como mínimo:

- UUID/ID interno;
- tipo import/export cuando corresponda;
- `source_key`;
- usuario creador;
- estado;
- formato;
- referencia/hash segura del archivo;
- mapping profile/version;
- política de vacíos;
- total/processed/created/updated/skipped/warnings/errors;
- cursor/checkpoint;
- timestamps;
- versión del contrato;
- referencia a dry-run confirmado;
- mensaje resumido de error sin payload completo.

## Seguridad específica

### Archivos

- extensión/MIME cuando corresponda;
- nombre generado por servidor;
- sin paths entregados por usuario;
- path traversal bloqueado;
- tamaño máximo antes de parsear;
- límites de filas/columnas/celda;
- protección contra archive bombs en XLSX/ZIP.

### CSV / spreadsheet

- nunca ejecutar contenido;
- fórmulas de entrada son strings;
- export neutraliza formula injection;
- encoding inválido se reporta.

### JSON

- tamaño y profundidad máximos;
- schema/shape allowlisted;
- claves desconocidas nunca se convierten dinámicamente en meta arbitrario.

### Media remota

- SSRF protection antes y después de redirects;
- bloqueo localhost/private/link-local/cloud metadata;
- límite de redirects;
- MIME y bytes reales;
- sin SVG remoto en primera implementación salvo decisión posterior.

## Performance budgets iniciales

No son SLA productivos; son guards de CI.

- parser CSV/JSON/XLSX con memoria bounded respecto del dataset cuando sea técnicamente razonable;
- dry-run 5k sin N+1 evitable;
- batches pequeños configurables;
- no `get_posts(-1)` ni cargas completas del catálogo;
- Search/Quality incremental;
- historial paginado;
- UI nunca renderiza miles de errores simultáneamente.

## Observabilidad y evidencia

Cada PR funcional registra:

- requirement/issue/PR;
- fixtures usados;
- formatos/tamaños probados;
- mutaciones esperadas;
- conteos antes/después;
- errores/warnings esperados;
- memory/query/time cuando aplique;
- security negative cases;
- artifact/checksum cuando aplique.

Evidencia bajo `docs/evidence/phase-3/`.

## Fuera de alcance

Fase 3 no implementa:

- frontend público final — Fase 4;
- WLA Inmo Light — Fase 5;
- SEO completo — Fase 6;
- leads/indicadores — Fase 7;
- hardening global final — Fase 8;
- migrador específico WooCommerce/ACF/WPCode de Propiedades Martínez — Fase 9.

## Quality Gate de salida

Fase 3 solo pasa a `DONE` cuando:

1. PR 3.1–3.12 aplicables están mergeadas con evidencia;
2. importación CSV/JSON/XLSX usa pipeline canónico común;
3. dry-run demuestra cero mutaciones;
4. resume/idempotencia están probados;
5. no hay findings críticos/altos abiertos;
6. fórmula/SSRF/archivo malicioso tienen cobertura negativa;
7. performance/memoria con datasets crecientes están documentados;
8. rollback no promete más de lo demostrable;
9. artifact/checksum final están registrados;
10. `PROJECT-STATUS.md` está actualizado;
11. producción sigue sin cambios salvo solicitud explícita posterior.
