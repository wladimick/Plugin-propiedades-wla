# Fase 3 — Import/Export

Estado: `PLANNING / ENTRY APPROVED`  
Dependencias: Fase 1 `DONE`, Fase 2 `DONE`  
Issue de entrada: #43  
Versión de entrada: `0.1.0-alpha`

## Objetivo

Construir un sistema de importación y exportación seguro, repetible y usable para cientos o miles de propiedades, sin convertir un archivo externo en una vía paralela que evite las reglas canónicas de WLA Inmo.

La Fase 3 debe reutilizar `MetaSchema`, `Sanitizer`, `Validator`, taxonomías, capacidades, Search, Quality y Activity. Importar no significa escribir postmeta directamente sin pasar por contratos de dominio.

Fuente funcional: `docs/IMPORT-EXPORT.md`.

## Principios no negociables

1. **Dry-run antes de confirmar.** Una simulación no crea posts, attachments, términos ni descarga archivos remotos.
2. **Identidad explícita.** `external_id` definido por una fuente/perfil tiene prioridad; luego `property_code`; nunca título o dirección por sí solos.
3. **Origen aislado.** Un `external_id` debe interpretarse en el contexto de un `source_key` para evitar colisiones entre proveedores distintos.
4. **Vacíos seguros.** Por defecto, una celda/campo vacío conserva el dato existente. Borrar exige una política explícita.
5. **Batches.** Nunca procesar un archivo grande en un request único.
6. **Resume seguro.** Un batch interrumpido solo puede reanudarse desde un checkpoint consistente.
7. **Idempotencia donde corresponda.** Repetir una operación confirmada no debe crear duplicados silenciosos.
8. **Sin side effects ocultos.** Search, Quality y Activity se sincronizan de forma incremental y observable.
9. **Media remota separada.** La descarga de imágenes no forma parte del dry-run y debe tener controles SSRF/MIME/tamaño/timeout/redirect.
10. **Errores por fila.** Un dato recuperable inválido no debe corromper todo el batch.
11. **Sin fórmulas ejecutables.** CSV/XLSX exportado debe neutralizar spreadsheet/formula injection.
12. **Permisos reales.** Capability + nonce + autorización de operación; ocultar botones no basta.
13. **Sin dependencia de producción.** Toda la fase se valida con fixtures sintéticos y WordPress limpio.
14. **No adelantar Fase 9.** El migrador específico de Woo/ACF/Propiedades Martínez sigue siendo una fase separada.

## Estados de un batch

Contrato inicial propuesto; el esquema físico se congela en PR 3.1:

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

Estados terminales adicionales solo si tienen semántica clara: `cancelled`, `rolled_back`, `rollback_blocked`.

No usar un estado genérico como `done_with_errors` si los contadores y errores por fila permiten expresar el resultado con precisión.

## Identidad y upsert

### Fuente externa

Un perfil de origen debe tener un `source_key` estable, por ejemplo:

```text
portal_proveedor_a
crm_inmobiliaria
carga_manual_2026
```

Cuando exista `external_id`, la identidad externa recomendada es:

```text
(source_key, external_id)
```

Si no existe `external_id`, se usa `property_code` cuando esté informado.

No se deduce identidad desde:

- título;
- dirección;
- comuna + precio;
- slug;
- posición de la fila.

### Conflictos

Un dry-run debe marcar explícitamente, como mínimo:

- `new`;
- `update`;
- `duplicate_in_file`;
- `identity_conflict`;
- `invalid`;
- `warning`.

Un conflicto de identidad no se resuelve silenciosamente eligiendo el primer resultado.

## Semántica de vacíos

Default:

```text
vacío → conservar valor actual
```

La UI podrá ofrecer más adelante una política explícita:

```text
vacío → borrar valor actual
```

La política debe quedar registrada en el batch y ser visible en el dry-run.

## Taxonomías desconocidas

Default seguro de Fase 3:

- no crear términos desconocidos automáticamente durante un import confirmado;
- mostrar error/advertencia de mapping según el campo;
- permitir mapping explícito hacia un término existente;
- una opción futura de creación automática requerirá capability y decisión documentada adicional.

Esto evita contaminar Región/Comuna/Tipo/Operación por errores ortográficos del archivo.

## Orden de PR

### PR 3.1 — Dominio de importación, batch model y CSV foundation

Objetivo: crear contratos puros y una base CSV segura, todavía sin wizard completo ni escritura masiva.

Incluye:

- namespace `WLA\Inmo\Import`;
- entidades/value objects para batch/source/mapping/row result cuando aporte claridad;
- estados y transiciones válidas;
- `source_key`;
- parser CSV UTF-8 incremental;
- normalización de BOM/headers;
- límite de columnas y filas configurable/interno;
- detección de separador acotada o configuración explícita;
- rechazo controlado de archivos ilegibles;
- resolver de identidad **read-only**;
- ningún upsert todavía;
- tests unit/smoke;
- ADR/esquema físico para batches e historial si se necesita persistencia desde este PR.

Criterios:

- archivo CSV no se carga entero en memoria por diseño;
- parser no evalúa fórmulas ni contenido;
- filas y columnas tienen límites;
- errores incluyen número de fila sin registrar datos privados innecesarios;
- ninguna prueba crea propiedades como side effect del parser.

### PR 3.2 — Mapping, validación y dry-run

Objetivo: convertir filas externas a una intención de cambio WLA sin escribir.

Incluye:

- detección/preview de columnas;
- mapping a título/contenido, MetaSchema y taxonomías soportadas;
- perfiles de mapping persistibles;
- normalización de valores;
- validación por fila;
- duplicados dentro del archivo;
- coincidencias con catálogo existente;
- conteos `new/update/warning/error`;
- diferencias relevantes para updates;
- dry-run firmado/identificado para la posterior confirmación;
- límite temporal/versión del dry-run para evitar confirmar una simulación obsoleta.

Prohibido:

- `wp_insert_post()`;
- `update_post_meta()`;
- `wp_set_object_terms()`;
- sideload/download remoto;
- creación automática de términos.

### PR 3.3 — Persistencia por lotes, resume e idempotencia

Objetivo: ejecutar exactamente el plan confirmado sin procesar todo en una sola solicitud.

Incluye:

- confirmación protegida;
- chunks pequeños configurables;
- checkpoint por batch;
- reanudación desde estado consistente;
- upsert por identidad resuelta;
- protección de doble ejecución;
- sincronización incremental Search/Quality;
- Activity con contexto allowlisted de importación;
- contadores y errores por fila;
- recovery de errores recuperables;
- no hacer rebuild completo por fila.

Tests mínimos:

- 100, 1.000 y 5.000 filas sintéticas;
- batch interrumpido y retomado;
- reejecución de chunk;
- dos filas que apuntan a la misma identidad;
- propiedad modificada entre dry-run y confirmación;
- rollback lógico del chunk cuando una escritura atómica local falla, cuando sea técnicamente posible.

### PR 3.4 — UI Importar e historial de batches

Objetivo: exponer el pipeline a una persona no técnica.

Wizard:

```text
1. Subir
2. Detectar
3. Mapear
4. Validar
5. Simular
6. Confirmar
7. Procesar
8. Informe
```

Incluye:

- `WLA Inmo → Importar / Exportar` deja de ser placeholder;
- capability exacta de importación;
- progress accesible;
- no depender de mantener una pestaña abierta si el mecanismo de procesamiento elegido puede continuar de forma segura;
- historial de batches;
- filtros por fecha/usuario/origen/estado;
- reporte descargable de errores sin secretos;
- cancelación solo en checkpoints seguros;
- ayuda contextual.

### PR 3.5 — JSON WLA versionado

Objetivo: formato interoperable y de respaldo lógico.

Incluye:

- `format_version`;
- metadatos mínimos del export;
- propiedades en contrato documentado;
- importación por el mismo pipeline canónico de mapping/validación;
- exportación filtrada;
- campos privados solo con capability explícita y opción consciente;
- compatibilidad entre versiones documentada;
- tests round-trip.

### PR 3.6 — XLSX streaming y ADR de dependencia

Objetivo: añadir XLSX sin degradar memoria, tamaño de ZIP o seguridad.

Antes de mergear:

- comparar al menos la opción ya propuesta en decisiones con una alternativa de lectura streaming más liviana;
- medir memoria con 1k/5k/archivo mayor razonable;
- medir peso agregado al ZIP;
- revisar mantenimiento/licencia/superficie de dependencias;
- documentar ADR final.

La librería solo transforma XLSX → filas normalizadas. La lógica de mapping/upsert no se duplica.

### PR 3.7 — Media remota segura

Objetivo: importar imágenes después de que la propiedad esté resuelta, sin convertir el plugin en un SSRF proxy.

Incluye:

- solo `https`/`http` según política final; bloquear otros esquemas;
- resolución DNS/IP y bloqueo de rangos locales/privados/reservados;
- revalidar redirects;
- timeout;
- límite de bytes;
- MIME real y extensiones permitidas;
- máximo de imágenes por propiedad/batch;
- deduplicación por fuente/hash cuando sea confiable;
- retries limitados;
- attachment IDs canónicos;
- errors de media separados de errores de datos;
- media nunca se descarga durante dry-run.

### PR 3.8 — Exportación CSV/XLSX

Objetivo: exportaciones filtradas, bounded y seguras.

Incluye:

- todas/disponibles/operación/tipo/ubicación/fecha/selección;
- CSV UTF-8 documentado;
- XLSX usando la dependencia aprobada de PR 3.6;
- streaming/chunks;
- formula injection neutralizada en celdas que comienzan con `=`, `+`, `-`, `@` u otros vectores definidos por el contrato;
- campos privados excluidos por defecto;
- pruebas con caracteres internacionales, saltos de línea y delimitadores.

### PR 3.9 — Rollback seguro de importación

Objetivo: revertir únicamente cuando WLA puede demostrar que no pisará trabajo posterior.

Incluye:

- propiedades creadas por el batch;
- snapshot mínimo de campos modificados donde el coste sea razonable;
- detectar `post_modified`/versiones o marcador equivalente posterior al batch;
- bloquear rollback destructivo si existen cambios posteriores no atribuibles al batch;
- media exclusiva identificable;
- preview de rollback;
- capability y confirmación avanzada;
- Activity del rollback.

No prometer rollback total cuando no pueda demostrarse seguridad.

### PR 3.10 — Quality Gate Fase 3

Incluye:

- regresión Fase 1/2;
- unit/integration/E2E del wizard;
- archivos CSV/JSON/XLSX malformados;
- BOM/encoding/delimitadores;
- archivo vacío;
- headers duplicados;
- columnas desconocidas;
- duplicados dentro del archivo;
- identity conflicts;
- stale dry-run;
- resume/idempotencia;
- 100/1k/5k y dataset mayor razonable;
- memoria peak cuando sea medible;
- archivos que exceden límites;
- formula injection;
- SSRF/redirections/MIME spoofing;
- capability/nonce/IDOR;
- round-trip import/export;
- rollback permitido/bloqueado;
- accesibilidad/responsive del wizard;
- artifact/checksum/evidencia.

## Persistencia de batches — requisitos antes del esquema físico

El modelo de almacenamiento de PR 3.1 debe poder representar como mínimo:

- batch UUID/ID interno;
- tipo `import`/`export` cuando corresponda;
- `source_key`;
- usuario creador;
- estado;
- formato;
- nombre seguro/referencia/hash del archivo, sin confiar en el nombre original como path;
- mapping profile/version;
- política de vacíos;
- total/processed/created/updated/skipped/warnings/errors;
- cursor/checkpoint;
- timestamps;
- versión del contrato;
- referencia a dry-run confirmado;
- mensaje resumido de error, sin payload completo.

Errores por fila pueden requerir una tabla separada para evitar options/transients gigantes. La decisión se documentará con índices y política de retención.

## Seguridad específica

### Archivos

- comprobar extensión y MIME donde corresponda;
- nombre generado por servidor;
- no usar paths entregados por el usuario;
- evitar path traversal;
- tamaño máximo antes de parsear;
- límites de filas/columnas/celda;
- no descomprimir XLSX/ZIP sin protección contra archive bombs.

### CSV / spreadsheet

- nunca ejecutar contenido;
- fórmulas de entrada se consideran strings salvo una regla futura explícita;
- export neutraliza formula injection;
- encoding invalid se reporta, no se interpreta de forma silenciosa.

### JSON

- tamaño y profundidad máximos;
- schema/shape allowlisted;
- claves desconocidas se ignoran o reportan según versión, nunca se mapean dinámicamente a meta arbitrario.

### Media remota

- SSRF protection antes y después de redirects;
- no localhost/private/link-local/cloud metadata;
- límite de redirects;
- MIME y bytes reales;
- sin SVG remoto en la primera implementación salvo decisión específica posterior.

## Performance budgets iniciales

No son SLA productivos. Son guards de CI.

- parser CSV: memoria aproximadamente bounded respecto del número total de filas;
- dry-run 5k: sin N+1 por fila para búsquedas de identidad cuando pueda resolverse por lotes;
- batch: tamaño pequeño configurable y sin timeout intencionalmente largo;
- no `get_posts(-1)` ni cargas completas de catálogo;
- Search/Quality incremental;
- historial paginado;
- UI nunca renderiza miles de errores simultáneamente: paginar/resumir.

## Observabilidad y evidencia

Cada PR funcional de Fase 3 debe registrar:

- requirement/issue/PR;
- fixtures usados;
- formatos y tamaños probados;
- mutaciones esperadas;
- conteos antes/después;
- errores/warnings esperados;
- memory/query/time cuando sea relevante;
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

1. PR 3.1–3.10 aplicables están mergeadas con evidencia;
2. importación CSV/JSON/XLSX usa un pipeline canónico común;
3. dry-run demuestra cero mutaciones;
4. resume/idempotencia están probados;
5. no hay findings críticos/altos abiertos;
6. fórmula/SSRF/archivo malicioso tienen cobertura negativa;
7. performance/memoria con datasets crecientes están documentados;
8. rollback no promete más de lo demostrable;
9. artifact/checksum final están registrados;
10. `PROJECT-STATUS.md` está actualizado;
11. producción sigue sin cambios salvo solicitud explícita posterior.
