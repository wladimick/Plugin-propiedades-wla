# Importación, exportación y carga masiva

## Objetivo

Permitir crear o actualizar cientos o miles de propiedades de manera segura, repetible y comprensible, sin crear vías paralelas que eviten los contratos canónicos de WLA Inmo.

## Formatos y estado

- CSV UTF-8: implementado en Fase 3.1–3.6.
- JSON WLA versionado: implementado en Fase 3.7.
- XLSX: implementado en Fase 3.8.
- Media remota segura: implementada en Fase 3.9.
- Exportación CSV/XLSX: `OMITTED / OUT_OF_SCOPE` en Fase 3 por decisión 2026-09-07.
- Rollback seguro best-effort: implementado en Fase 3.11 / PR #67; QA final aprobada antes del merge.

CSV, JSON y XLSX convergen en el mismo pipeline de mapping, validación, dry-run, identidad, `RowExecutor`, batches y checkpoints. El formato externo puede tener un lector distinto, pero nunca un segundo mecanismo de upsert.

## Flujo del importador

```text
Subir archivo
   ↓
Detectar / validar contrato
   ↓
Normalizar a fuente controlada por servidor cuando corresponda
   ↓
Mapear campos / congelar mapping canónico
   ↓
Validar
   ↓
Simular
   ↓
Confirmar hash + profile snapshot
   ↓
Procesar por lotes
   ↓
Informe final
```

## CSV

El usuario relaciona columnas externas con campos WLA Inmo. El parser es incremental, UTF-8, con límites de filas/columnas/celda. El contenido se trata como datos y no se evalúan fórmulas.

Ejemplo:

```text
Archivo               WLA Inmo
codigo              → Código
nombre              → Título
precio              → Precio CLP
uf                  → Precio UF
comuna              → Comuna
m2_terreno          → Superficie terreno
```

## JSON WLA v1

### Envelope

`format_version` es obligatorio. La versión inicial soportada es `1`.

```json
{
  "format_version": 1,
  "source_key": "crm_inmobiliaria",
  "exported_at": "2026-09-07T14:00:00+00:00",
  "properties": [
    {
      "post": {
        "title": "Casa ejemplo",
        "content": "Descripción",
        "excerpt": "Resumen"
      },
      "meta": {
        "property_code": "PROP-001",
        "price_clp": 120000000,
        "status": "available",
        "video_urls": ["https://example.com/video"]
      },
      "taxonomies": {
        "operation": "Venta",
        "property_type": "Casa",
        "commune": "Curicó",
        "feature": ["Piscina", "Terraza"]
      },
      "media": {
        "gallery_urls": ["https://cdn.example.com/casa-1.jpg"],
        "featured_image_url": "https://cdn.example.com/casa-1.jpg"
      }
    }
  ]
}
```

Claves raíz admitidas: `format_version`, `source_key`, `exported_at` opcional y `properties`.

Secciones por propiedad: `post`, `meta`, `taxonomies` y `media`. Cada campo se traduce a un target de `TargetRegistry`. Una clave desconocida nunca se convierte dinámicamente en postmeta.

### Tipos y límites

- valores simples: string, número, boolean o null;
- arrays solo para targets canónicos múltiples;
- objetos arbitrarios/anidados dentro de campos no están permitidos;
- `gallery_ids` no es portable/importable porque contiene IDs internos de WordPress;
- máximo upload: 10 MiB;
- máximo propiedades: 10.000;
- profundidad JSON máxima: 16;
- versión futura no soportada se rechaza explícitamente;
- JSON malformado/root inválido se rechaza sin crear batch.

### Normalización y resume

El JSON externo se lee bajo shared lock, se calcula SHA-256 y se valida que no cambie durante la lectura. Luego se transforma a una fuente **NDJSON privada generada por servidor**, una propiedad por línea. El JSON original temporal se elimina después de normalizar.

La fuente NDJSON conserva tipos/arrays, tiene SHA-256 propio, usa headers internos normalizados, soporta `cursor_offset` físico y reutiliza el mismo `BatchRunner`. No existe un segundo runner JSON.

### Mapping JSON

El mapping se genera en servidor durante la validación del contrato y se muestra read-only. `source_key` y mapping JSON no se aceptan desde POST.

## XLSX

Fase 3.8 adoptó **PhpSpreadsheet 3.10.7 exacta** conforme a ADR-014, implementando D31.

### Preflight

Antes del reader, `XlsxArchiveInspector` valida:

- extensión/MIME;
- ZIP legible;
- bytes comprimidos y descomprimidos;
- entries y ratio de expansión;
- required OOXML parts;
- número de worksheets;
- path traversal / Zip Slip;
- macros, binarios y partes ejecutables no soportadas;
- relationships externos.

Luego el usuario elige explícitamente la worksheet.

### Lectura bounded

La hoja seleccionada usa PhpSpreadsheet con `setReadDataOnly(true)`, `setReadEmptyCells(false)` e `IReadFilter` por chunks de 500 filas. Aplica límites de filas, columnas y bytes/celda y normaliza XLSX → NDJSON privado generado por servidor.

No existe un segundo runner XLSX.

### Seguridad XLSX

- fórmulas se leen como datos y no se ejecutan;
- no se realizan HTTP requests por relationships externos;
- temporales usan UUIDs server-side;
- staging requiere permisos `0600` o falla cerrado;
- hashes del original y normalizado se verifican;
- `WorkspaceJanitor` limpia uploads abandonados, no fuentes reanudables de batches.

## Identificación y upsert

Prioridad:

1. `(source_key, external_id)` cuando existe identidad externa;
2. `property_code`;
3. nunca título/dirección por sí solos.

La simulación clasifica nuevas, actualizaciones, advertencias, errores, duplicados y conflictos de identidad.

## Modo simulación

Antes de escribir valida identidad/duplicados, obligatorios, tipos, números, fechas, coordenadas, taxonomías, estados y contrato del formato.

El dry-run no crea posts, no modifica meta/taxonomías, no crea términos, no descarga media y no prepara rollback destructivo.

## Procesamiento por lotes

Nunca procesar un dataset grande en un único request.

Los batches avanzan en slices pequeños y guardan:

- `source_format` (`csv`, `json` o `xlsx`);
- `cursor_row`;
- `cursor_offset` cuando aplica;
- contadores;
- `revision` para optimistic locking;
- snapshot de mapping/profile;
- hash de fuente confirmada.

Un batch pausado/fallido se reanuda desde un checkpoint consistente.

## Actualizaciones parciales

Default seguro:

```text
vacío → conservar valor actual
```

Borrar requiere elección explícita:

```text
vacío → borrar valor actual
```

La política queda congelada en el profile del batch.

## Media remota segura

Fase 3.9 implementa `media.gallery_urls` y `media.featured_image_url` como targets portables.

Reglas:

- cero HTTP en dry-run;
- URL/DNS/IP/redirects validados contra SSRF;
- WordPress safe HTTP API;
- streaming bounded a temporales privados;
- máximo 10 MiB por imagen, 15 s, 3 redirects y 20 imágenes;
- JPEG/PNG/WebP; SVG remoto rechazado;
- MIME/firma/dimensiones/píxeles/SHA-256 verificados;
- deduplicación WLA por SHA-256;
- URL de origen no se conserva en claro;
- galería/featured se persisten como referencias WordPress canónicas;
- fallas permanentes generan warnings; transitorias agotan retry antes de checkpoint.

## Exportación JSON WLA

Fase 3.7 incorpora exportación lógica versionada, streaming/paginada:

- `format_version = 1`;
- `source_key` estable;
- `exported_at`;
- páginas bounded, default 100/máximo 250;
- sin `get_posts(-1)`;
- meta/taxonomías por lote;
- SHA-256 y tamaño del artifact;
- round-trip con el mismo `JsonDocumentReader`.

### Privacidad

El JSON estándar incluye targets portables no privados. Se excluyen por defecto, entre otros, `external_id`, `private_address`, `internal_notes`, `home_order`, `indexable` y cualquier target `private`.

No se exportan tokens, cookies, paths de servidor ni payloads de Activity.

## Exportación CSV/XLSX

Estado en Fase 3: `OMITTED / OUT_OF_SCOPE`.

La decisión 2026-09-07 mantiene importación CSV/XLSX y export JSON WLA, pero retira export CSV/XLSX como requisito de salida. Se conserva PR 3.10 en la numeración por auditoría. Cualquier reconsideración futura requerirá nuevo alcance y deberá contemplar streaming/chunks y Spreadsheet Formula Injection.

## Historial

Cada importación registra metadata bounded: UUID, formato, origen, usuario/fecha, estado, progreso y contadores. No expone `profile_json`, `source_hash`, filas, paths temporales ni datos privados.

## Rollback seguro best-effort — Fase 3.11

### Principio

Rollback no significa “volver atrás a cualquier costo”. WLA revierte únicamente cuando puede demostrar que no pisará trabajo posterior.

Para cada fila mutada se persiste un journal:

```text
prepare intent
  ↓ antes de RowExecutor
created | updated + targets + before mínimo
  ↓
RowExecutor
  ↓
finalize after + hash
  ↓ antes de checkpoint
BatchCheckpoint
```

### Updates

Para una propiedad actualizada:

- snapshot `before` y `after` contienen **solo los targets tocados** por esa fila;
- justo antes de revertir se vuelve a capturar el mismo scope;
- si `current == after`, se restaura `before`;
- si un target tocado cambió después, la fila queda `blocked` y el dato posterior se conserva;
- un cambio en un campo fuera del scope no bloquea por sí solo y nunca se sobrescribe.

Se distingue meta inexistente de meta existente vacía. Cuando `external_id` forma parte del scope también se conserva/restaura la asociación `source_key`.

### Propiedades creadas

Para una propiedad creada por el batch, WLA guarda un fingerprint SHA-256 conservador del estado resultante, incluyendo campos editoriales nativos relevantes, toda la meta y taxonomías de la propiedad.

La eliminación permanente solo ocurre si el fingerprint actual sigue coincidiendo. Si alguien o un plugin modificó/agregó datos después, WLA bloquea la eliminación.

### Media

Rollback restaura galería y featured image anteriores mediante IDs canónicos. **No elimina attachments** descargados o existentes porque la exclusividad/ausencia de referencias no se presume.

### Crash safety

El intent se escribe antes del `RowExecutor` y el `after` se finaliza antes del checkpoint. Si el proceso cae después de crear la propiedad pero antes del checkpoint, el retry puede re-resolver como update sin perder `original_action=created`. El rollback posterior conserva la semántica original.

### Preview, confirmación y ejecución

```text
completed
  ↓
preview read-only
  ├── safe
  ├── noop
  ├── blocked
  └── error
  ↓ confirmación con revision + preview_hash fresco
rollback_processing
  ↓ slices bounded + lock TTL + revalidación por fila
rolled_back | rollback_blocked
```

- preview nunca muta;
- confirmación recalcula estado y no confía en hidden fields;
- ejecución es reanudable por slices y procesa el journal en orden inverso;
- lock atómico con TTL impide dos runners simultáneos del mismo batch;
- una reversa ya finalizada no vuelve a ejecutarse destructivamente;
- cualquier inconsistencia falla cerrado.

### Autorización

Capability dedicada: `rollback_wla_imports`.

- Administrador WordPress: sí por defecto;
- Administrador inmobiliario: no por defecto;
- editor/gestor de leads: no;
- preview, confirm y run usan nonces separados;
- el batch exige ownership o `manage_tools` según la política administrativa vigente.

### Observabilidad y privacidad

Activity registra eventos batch-level `previewed`, `started`, `completed` y `blocked` con referencia hash, conteos/estado/código estable. No registra snapshots, UUID crudo ni payload privado.

### Estados de batch vinculados a rollback

```text
completed
   ↓
rollback_processing
   ├──> rolled_back
   └──> rollback_blocked
```

`rolled_back` y `rollback_blocked` son terminales.

### Evidencia 3.11

- Issue #66 / PR #67;
- `docs/evidence/phase-3/PR-3.11-ROLLBACK.md`;
- workflow `.github/workflows/import-rollback-integration.yml`;
- matriz WP 6.6.2/PHP 8.1 + latest/PHP 8.3 sobre MySQL 8;
- head funcional `f9dab169d02ee700417dbe375d03f585d9bb1075`: 16/16 workflows SUCCESS.

## Seguridad transversal

- capabilities exactas por operación;
- nonce por mutación/descarga;
- límites antes de parsear;
- extensión + MIME;
- paths solo desde UUIDs server-generated;
- hashes y locks;
- schema/targets allowlisted;
- sin PHP unserialize de input;
- sin HTTP en dry-run;
- preflight bounded XLSX;
- SSRF hardening en media;
- optimistic locking/checkpoints;
- rollback fail-closed;
- logs/evidencia sin payload privado.

## Rendimiento

- CSV incremental;
- JSON bounded por bytes/propiedades/profundidad;
- XLSX por chunks de 500 filas tras preflight;
- ejecución y rollback por slices reanudables;
- export JSON streaming/paginada;
- sin carga completa del catálogo;
- Search/Quality incremental;
- historial paginado;
- datasets 100/1.000/5.000 como regresión de fase.

## QA / evidencia

- ADR XLSX: `docs/decisions/ADR-014-xlsx-dependency.md`;
- JSON: `docs/evidence/phase-3/PR-3.7-JSON-WLA.md`;
- XLSX: `docs/evidence/phase-3/PR-3.8-XLSX.md`;
- media: `docs/evidence/phase-3/PR-3.9-REMOTE-MEDIA.md`;
- rollback: `docs/evidence/phase-3/PR-3.11-ROLLBACK.md`.

## Producción

La Fase 3 usa fixtures sintéticos y WordPress limpio. `propiedadesmartinez.cl` permanece sin cambios.