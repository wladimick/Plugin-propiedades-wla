# Importación, exportación y carga masiva

## Objetivo

Permitir crear o actualizar cientos o miles de propiedades de manera segura, repetible y comprensible, sin crear vías paralelas que eviten los contratos canónicos de WLA Inmo.

## Formatos y estado

- CSV UTF-8: implementado en Fase 3.1–3.6.
- JSON WLA versionado: Fase 3.7.
- XLSX: Fase 3.8.

CSV y JSON convergen en el mismo pipeline de mapping, validación, dry-run, identidad, `RowExecutor`, batches y checkpoints.

## Flujo del importador

```text
Subir archivo
   ↓
Detectar / validar contrato
   ↓
Mapear campos (CSV) o congelar mapping canónico (JSON WLA)
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
- shape y versión del formato.

El dry-run no crea posts, no modifica meta/taxonomías y no descarga media.

## Procesamiento por lotes

Nunca procesar un dataset grande en un único request.

Los batches avanzan en slices pequeños y guardan:

- `source_format` (`csv` o `json`);
- `cursor_row`;
- `cursor_offset` físico;
- contadores;
- `revision` para optimistic locking;
- snapshot de mapping;
- hash de fuente confirmada.

Un batch pausado/fallido se reanuda desde un checkpoint consistente. Los archivos de batches no se eliminan por antigüedad; solo drafts temporales abandonados se limpian automáticamente.

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

CSV/XLSX final corresponde a PR 3.10. Debe mantener filtros, streaming/chunks y neutralización de Spreadsheet Formula Injection.

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
- formato/origen;
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
- sin HTTP durante parse/dry-run JSON;
- logs/evidencia sin payload privado.

## Rendimiento

- parsing CSV incremental;
- JSON externo bounded por bytes/propiedades/profundidad;
- ejecución mediante batches reanudables;
- exportación JSON streaming/paginada;
- sin cargas completas del catálogo;
- Search/Quality sincronizados incrementalmente;
- historial paginado;
- datasets 100 / 1.000 / 5.000 como regresión de fase.
