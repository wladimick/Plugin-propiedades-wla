# Importación, exportación y carga masiva

## Objetivo

Permitir crear o actualizar cientos o miles de propiedades de manera segura, repetible y comprensible, sin crear vías paralelas que eviten los contratos canónicos de WLA Inmo.

## Formatos y estado

- CSV UTF-8: implementado en Fase 3.1–3.6.
- JSON WLA versionado: implementado en Fase 3.7.
- XLSX: implementado en Fase 3.8, QA final pendiente en PR #62.

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

El usuario relaciona columnas externas con campos WLA Inmo.

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

El parser es incremental, UTF-8, con límites de filas/columnas/celda. El contenido se trata como datos y no se evalúan fórmulas.

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
      }
    }
  ]
}
```

Claves raíz admitidas:

- `format_version`;
- `source_key`;
- `exported_at` opcional;
- `properties`.

Secciones por propiedad admitidas:

- `post`;
- `meta`;
- `taxonomies`.

Cada campo se traduce a un target de `TargetRegistry`. Una clave desconocida nunca se convierte dinámicamente en postmeta.

### Tipos

- valores simples: string, número, boolean o null;
- arrays solo para targets canónicos múltiples;
- objetos arbitrarios/anidados dentro de campos no están permitidos;
- `gallery_ids` no es portable/importable en este contrato porque contiene IDs internos de WordPress.

### Límites iniciales

- máximo de upload: 10 MiB;
- máximo de propiedades: 10.000;
- profundidad JSON máxima: 16;
- versión futura no soportada se rechaza explícitamente;
- JSON malformado o root shape inválido se rechaza sin crear un batch.

### Normalización y resume

El JSON externo se lee bajo shared lock, se calcula SHA-256 y se valida que no cambie durante la lectura.

Una vez validado se transforma a una fuente **NDJSON privada generada por el servidor**, una propiedad normalizada por línea. El JSON original temporal se elimina después de la normalización.

La fuente NDJSON:

- conserva tipos y arrays;
- usa headers internos normalizados separados de los targets canónicos;
- tiene SHA-256 propio;
- se vuelve a verificar antes/durante ejecución;
- soporta `cursor_offset` físico por bytes;
- permite usar el mismo `BatchRunner` de CSV.

No existe un segundo runner JSON.

### Mapping JSON

El mapping se genera en servidor durante la validación del contrato. La UI lo muestra como solo lectura.

`source_key` y mapping JSON **no se aceptan desde POST**. El usuario solo puede decidir políticas explícitas permitidas, como la semántica de vacíos.

## XLSX

PR 3.8 adopta **PhpSpreadsheet 3.10.7 exacta** conforme a ADR-014, implementando D31 sin reemplazarla.

### Preflight y selección de hoja

Un XLSX no entra directamente a PhpSpreadsheet. Primero se valida mediante `XlsxArchiveInspector`:

- extensión y MIME compatibles;
- ZIP legible;
- límite de bytes comprimidos;
- límite de entries ZIP;
- límite de bytes descomprimidos por entry y total;
- ratio de expansión bounded;
- required OOXML parts;
- límite de worksheets;
- bloqueo de path traversal / Zip Slip;
- bloqueo de macros, binarios y partes ejecutables no soportadas;
- bloqueo de relationships externos.

Después del preflight, el usuario debe **elegir explícitamente la worksheet** que se importará. WLA Inmo no selecciona silenciosamente la primera hoja cuando existen varias.

### Lectura bounded y normalización

La worksheet seleccionada se procesa mediante PhpSpreadsheet con:

- `setReadDataOnly(true)`;
- `setReadEmptyCells(false)`;
- `IReadFilter` por chunks de 500 filas;
- máximo de 10.000 filas importables;
- máximo de columnas;
- máximo de bytes por celda;
- headers normalizados mediante los contratos existentes.

El XLSX se transforma a una fuente **NDJSON privada y server-generated**. Desde ese punto XLSX reutiliza el mismo pipeline de JSON/CSV.

No existe un segundo runner XLSX.

### Seguridad XLSX

- fórmulas se leen como datos y no se ejecutan;
- no se realizan requests HTTP por relationships externos;
- no se descargan imágenes/media en 3.8;
- temporales usan nombres derivados de UUIDs generados por servidor;
- el XLSX staged debe poder restringirse a permisos `0600`; si no, la carga falla y se elimina;
- la fuente NDJSON normalizada mantiene hash propio;
- el hash del XLSX original se compara antes/después de la normalización;
- uploads XLSX abandonados se limpian por `WorkspaceJanitor`;
- el janitor no borra fuentes de batch reanudables por antigüedad.

### UI XLSX

La pantalla existente `WLA Inmo → Importar / Exportar` incorpora pestaña XLSX.

Flujo:

```text
Subir
  ↓
Elegir hoja
  ↓
Mapear
  ↓
Validar / Simular
  ↓
Confirmar
  ↓
Procesar
  ↓
Informe
```

La pestaña XLSX conserva su formato al navegar historial/paginación y filtra batches `source_format=xlsx`.

## Identificación y upsert

Prioridad:

1. `(source_key, external_id)` cuando existe identidad externa;
2. `property_code`;
3. nunca título/dirección por sí solos.

La simulación clasifica como mínimo nuevas, actualizaciones, advertencias, errores, duplicados/conflictos de identidad.

## Modo simulación

Antes de escribir se valida, entre otros:

- identidad y duplicados;
- campos obligatorios;
- tipos/números/fechas/coordenadas;
- taxonomías desconocidas;
- estados no soportados;
- shape/versión o estructura del formato.

El dry-run no crea posts, no modifica meta/taxonomías, no crea términos y no descarga media.

## Procesamiento por lotes

Nunca procesar un dataset grande en un único request.

Los batches avanzan en slices pequeños y guardan:

- `source_format` (`csv`, `json` o `xlsx`);
- `cursor_row`;
- `cursor_offset` físico cuando aplica;
- contadores;
- `revision` para optimistic locking;
- snapshot de mapping;
- hash de fuente confirmada.

Un batch pausado/fallido se reanuda desde un checkpoint consistente. Los archivos de batches no se eliminan por antigüedad; solo drafts/uploads temporales abandonados se limpian automáticamente.

## Actualizaciones parciales

Política segura por defecto:

```text
vacío → conservar valor actual
```

Borrar requiere una elección explícita:

```text
vacío → borrar valor actual
```

La política queda congelada en el profile del batch.

## Exportación JSON WLA

Fase 3.7 incorpora exportación lógica versionada y streaming/paginada.

Características:

- `format_version = 1`;
- `source_key` estable del export;
- timestamp `exported_at`;
- páginas bounded (máximo interno 250 por página; default 100);
- nunca `get_posts(-1)`;
- meta precargada por página;
- taxonomías consultadas por lote, no una consulta por propiedad/taxonomía;
- SHA-256 y tamaño del artifact generado;
- salida compatible con el mismo `JsonDocumentReader` para round-trip.

### Privacidad de exportación

El respaldo JSON estándar incluye únicamente targets portables no privados.

Quedan excluidos por defecto, entre otros:

- `external_id`;
- `private_address`;
- `internal_notes`;
- `home_order`;
- `indexable`;
- cualquier otro target marcado `private` en `TargetRegistry`.

No se exportan tokens, cookies, paths de servidor ni payloads de Activity.

Incluir campos privados en el futuro requerirá una decisión explícita, capability específica/adecuada y opción consciente; no se activa silenciosamente.

## Exportación CSV/XLSX

CSV/XLSX final corresponde a PR 3.10. Debe mantener filtros, streaming/chunks y neutralización de Spreadsheet Formula Injection. La dependencia XLSX ya quedó definida en ADR-014/PR 3.8.

## Imágenes

Media remota se implementa separadamente en PR 3.9. Nunca se descarga durante dry-run.

Reglas previstas:

- validar URL/DNS/IP/redirects contra SSRF;
- MIME y bytes reales;
- timeout y límites;
- retries acotados;
- attachment IDs canónicos;
- errores de media separados de datos.

## Historial

Cada importación registra metadata bounded:

- batch UUID;
- `source_format`;
- origen;
- usuario/fecha;
- estado;
- total/progreso;
- creadas/actualizadas/omitidas/errores/advertencias.

No se exponen en el historial:

- `profile_json`;
- `source_hash`;
- contenido de filas;
- paths temporales;
- datos privados de propiedades.

## Reversión

Rollback seguro corresponde a PR 3.11. Solo se permitirá cuando WLA pueda demostrar que no sobrescribirá cambios posteriores.

## Seguridad

- capability exacta de importación y exportación;
- nonce por mutación/descarga;
- límite de tamaño antes de parsear;
- extensión + MIME;
- paths derivados exclusivamente de UUIDs generados por servidor;
- hashes y locks de fuente;
- schema/targets allowlisted;
- sin deserialización PHP de input;
- sin HTTP durante parse/dry-run JSON/XLSX;
- preflight archive bounded para XLSX;
- logs/evidencia sin payload privado.

## Rendimiento

- parsing CSV incremental;
- JSON externo bounded por bytes/propiedades/profundidad;
- XLSX bounded por chunks de 500 filas después de preflight OOXML;
- ejecución mediante batches reanudables;
- exportación JSON streaming/paginada;
- sin cargas completas del catálogo;
- Search/Quality sincronizados incrementalmente;
- historial paginado;
- datasets 100 / 1.000 / 5.000 como regresión de fase.

## QA / evidencia

- ADR: `docs/decisions/ADR-014-xlsx-dependency.md`;
- evidencia 3.8: `docs/evidence/phase-3/PR-3.8-XLSX.md`;
- workflow: `.github/workflows/xlsx-integration.yml`;
- matrices PHP 8.1 / PHP 8.3;
- WordPress mínimo/latest mediante gates de regresión existentes.

## Producción

La Fase 3 usa fixtures sintéticos y WordPress limpio. `propiedadesmartinez.cl` permanece sin cambios.
