# Evidencia — PR 3.7 JSON WLA versionado

Estado: `IN_PROGRESS / QA_PENDING`.

Issue: #58  
Rama: `feat/phase3-json-wla`

## Objetivo

Añadir un formato JSON WLA interoperable y versionado que reutilice identidad, mapping, dry-run, executor y batches existentes, sin crear una segunda vía de escritura.

## Decisión arquitectónica inicial

`BatchRunner` y `RowExecutor` deben permanecer como única vía de ejecución. El JSON externo se valida una sola vez y se normaliza a un **NDJSON privado generado por servidor**, una propiedad por línea.

Esto conserva:

- valores tipados y arrays nativos;
- SHA-256 del source confirmado;
- shared lock durante ejecución;
- `cursor_offset` físico;
- resume sin reparsear el documento JSON completo;
- checkpoints e idempotencia existentes;
- posibilidad de reutilizar la misma abstracción para XLSX en PR 3.8.

El JSON original nunca se usa como source mutable durante la ejecución confirmada.

## Contrato v1 inicial

Envelope:

```json
{
  "format_version": 1,
  "source_key": "crm_inmobiliaria",
  "exported_at": "2026-09-07T13:00:00Z",
  "properties": []
}
```

Cada propiedad admite únicamente:

- `post`;
- `meta`;
- `taxonomies`.

Las claves se convierten a targets canónicos (`post.*`, `meta.*`, `taxonomy.*`) y deben existir en `TargetRegistry`. No se construyen meta keys arbitrarias desde el input.

## Primera entrega implementada

- `JsonException` con códigos estables y número de propiedad opcional;
- `JsonDocumentReader`;
- `format_version = 1` obligatorio;
- `source_key` validado con `SourceKey` existente;
- límite de bytes, profundidad y cantidad de propiedades;
- root/sections allowlisted;
- targets verificados por `TargetRegistry`;
- valores estructurados permitidos solo para targets múltiples;
- lectura del JSON original bajo `LOCK_SH`;
- detección de cambio del archivo durante validación;
- hash SHA-256 del original;
- materialización NDJSON con creación exclusiva (`xb`);
- SHA-256 del source normalizado;
- `JsonLinesReader` con hash + lock + resume por byte offset;
- unit tests de contrato/resume/tampering;
- smoke guards de seguridad.

## Seguridad

No hay requests HTTP, descargas, unserialize PHP, creación de términos ni escrituras WordPress en esta entrega. La normalización solo produce un archivo temporal privado controlado por servidor.

## Próximos pasos del mismo PR

1. abstraer el source reader de `BatchRunner` sin cambiar el comportamiento CSV;
2. persistir `source_format` de forma versionada y compatible;
3. integrar workspace JSON y limpieza segura;
4. conectar JSON al dry-run y batch canónico;
5. incorporar export JSON bounded;
6. UI/importación JSON;
7. integración WordPress/E2E/round-trip;
8. QA final y artifact/checksum.

## Producción

`propiedadesmartinez.cl` permanece sin cambios.
