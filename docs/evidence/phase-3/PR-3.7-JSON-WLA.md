# Evidencia — PR 3.7 JSON WLA versionado

Estado: `QA_PASSED / READY_TO_MERGE`.

Issue: #58  
PR: #60  
Rama: `feat/phase3-json-wla`  
Head funcional validado: `0eaeda0f02da44ae25018b6ba167b8b1dddda5a4`

## Objetivo

Añadir un formato JSON WLA interoperable y versionado para importación/exportación lógica, reutilizando identidad, mapping, dry-run, `RowExecutor` y `BatchRunner` existentes, sin crear una segunda vía de escritura.

## Decisión arquitectónica

`BatchRunner` y `RowExecutor` permanecen como única vía de ejecución. El JSON externo se valida y se normaliza a un **NDJSON privado generado por servidor**, una propiedad por línea.

Esto conserva:

- valores tipados y arrays nativos;
- SHA-256 del source confirmado;
- shared lock durante ejecución;
- `cursor_offset` físico;
- resume sin reparsear el documento JSON completo;
- checkpoints e idempotencia existentes;
- compatibilidad con el pipeline CSV ya validado;
- una base reutilizable para XLSX sin duplicar mapping/upsert.

El JSON original no se convierte en una fuente mutable durante la ejecución confirmada.

## Contrato v1

Envelope:

```json
{
  "format_version": 1,
  "source_key": "crm_inmobiliaria",
  "exported_at": "2026-09-07T13:00:00Z",
  "properties": []
}
```

Cada propiedad admite únicamente secciones allowlisted:

- `post`;
- `meta`;
- `taxonomies`.

Las claves se convierten a targets canónicos (`post.*`, `meta.*`, `taxonomy.*`) y deben existir en `TargetRegistry`. No se construyen meta keys arbitrarias desde el input.

Fixture versionado: `tests/fixtures/json/wla-v1-minimal.json`.

## Implementación cerrada

- `JsonException` con códigos estables y número de propiedad opcional;
- `JsonDocumentReader` con `format_version = 1` obligatorio;
- `source_key` reutilizando `SourceKey`;
- límites de bytes, profundidad, propiedades y tamaño de línea normalizada;
- root/secciones/targets allowlisted;
- arrays solo para targets múltiples;
- lectura del JSON original bajo `LOCK_SH`;
- detección de cambio del archivo durante validación;
- normalización a NDJSON server-generated;
- creación privada fail-closed `0600`;
- SHA-256 del source normalizado;
- `JsonLinesReader` con hash, lock y resume por byte offset;
- `source_format` persistido en batches, schema DB v3 y fallback CSV compatible;
- workspace JSON y cleanup seguro;
- dry-run obligatorio mediante `DryRunEngine`;
- ejecución mediante `BatchRunner` / `RowExecutor` canónicos;
- UI JSON dentro de `WLA Inmo → Importar / Exportar`;
- capabilities y nonces por mutación;
- export JSON bounded;
- privados excluidos por defecto;
- round-trip export → import → dry-run demostrado;
- fixture v1 versionado y regresiones del envelope;
- benchmark sintético 100 / 1.000 / 5.000.

## Seguridad

Cobertura demostrada:

- no PHP `unserialize` ni object injection;
- no requests HTTP ni descargas remotas durante parse/dry-run;
- no creación automática de términos;
- no meta keys dinámicas desde input;
- paths derivados por servidor, no aceptados desde request;
- NDJSON privado con modo `0600` desde creación;
- source incompleto eliminado ante error;
- `exported_at` inválido se rechaza antes de crear source;
- fila normalizada >2 MiB rechazada antes de entrar al runner;
- SHA-256 detecta tampering;
- capability negativa runtime → 403;
- nonce ausente/inválido bloquea mutaciones JSON;
- exportación excluye `external_id`, dirección privada y notas internas por defecto.

## Review findings

Review automatizado detectó 3 findings:

1. **P1 — permisos del NDJSON normalizado**: corregido con `umask(0077)` + `chmod(0600)` fail-closed y test de regresión.
2. **P2 — inconsistencia entre límite de fila normalizada y reader**: corregido con contrato compartido `JsonLinesReader::DEFAULT_MAX_LINE_BYTES` y rechazo temprano.
3. **P2 — `exported_at` podía dejar source huérfano**: corregido validando metadata antes de abrir output y cubierto por test.

Threads de review abiertos al cierre: **0**.  
P0/P1 abiertos conocidos: **0**.

## QA automático final

Head validado: `0eaeda0f02da44ae25018b6ba167b8b1dddda5a4`.

13/13 workflows asociados al head finalizaron `SUCCESS`:

- Bootstrap Smoke — run `34142720918`;
- Import Row Executor Integration — `34142720947`;
- Activity Integration — `34142720882`;
- JSON WLA Integration — `34142720944`;
- Help Center Integration — `34142720923`;
- Import UI Integration — `34142720909`;
- Dashboard Integration — `34142720926`;
- Phase 1 CI — `34142720900`;
- Import Persistence Integration — `34142720841`;
- Settings UI Integration — `34142720899`;
- Catalogue Quality Integration — `34142720830`;
- Import Batch Runner Integration — `34142720972`;
- Administration Quality Gate — `34142720887`.

### Matriz JSON WLA

Run: `34142720944`.

Ambas matrices finalizaron `SUCCESS`:

- WordPress 6.6.2 / PHP 8.1.34;
- WordPress 7.1 / PHP 8.3.33.

Cada matriz validó:

- build/install del ZIP real;
- fixture JSON WLA v1 versionado;
- importación JSON y pipeline canónico;
- privacidad y round-trip;
- capability y nonce negativos;
- datasets 100 / 1.000 / 5.000;
- subida de artifact de performance.

## Performance sintético

Estas mediciones son evidencia de CI, **no SLA productivo**.

| Entorno | Filas | Input | NDJSON | Tiempo | Peak delta |
|---|---:|---:|---:|---:|---:|
| WP 6.6.2 / PHP 8.1.34 | 100 | 18.748 B | 19.384 B | 21,10 ms | 0 MiB observado |
| WP 6.6.2 / PHP 8.1.34 | 1.000 | 188.851 B | 195.786 B | 190,07 ms | 2 MiB |
| WP 6.6.2 / PHP 8.1.34 | 5.000 | 952.851 B | 987.786 B | 984,13 ms | 8 MiB |
| WP 7.1 / PHP 8.3.33 | 100 | 18.748 B | 19.384 B | 20,77 ms | 2 MiB |
| WP 7.1 / PHP 8.3.33 | 1.000 | 188.851 B | 195.786 B | 197,50 ms | 2 MiB |
| WP 7.1 / PHP 8.3.33 | 5.000 | 952.851 B | 987.786 B | 947,09 ms | 8 MiB |

Resultado: el dataset de 5.000 propiedades se normalizó en menos de 1 segundo en ambas matrices, con incremento peak observado de 8 MiB.

## Artifacts / checksums

Run `34142720944`:

- `json-wla-performance-6.6.2-php-8.1`
  - artifact id: `10026516420`
  - digest: `sha256:532b416b671b3912814593a70b5497345883ea50a28bff8a43f092f51e8b3d34`
- `json-wla-performance-latest-php-8.3`
  - artifact id: `10026517102`
  - digest: `sha256:82cb46ad0cab89e32f27a793d2af707820e0de2ea24eb3fab881642de385e9aa`

Artifacts configurados con retención de GitHub Actions; los digests permanecen en esta evidencia para auditoría.

## Tests relevantes

- `tests/unit/ImportJsonFoundationTest.php`;
- `tests/unit/ImportJsonExportTest.php`;
- `tests/integration/assert-json-fixture.php`;
- `tests/integration/assert-json-wla.php`;
- `tests/integration/assert-json-security.php`;
- `tests/integration/measure-json-wla.php`;
- `tests/smoke/json-wla.php`;
- `tests/smoke/json-export.php`;
- `tests/smoke/json-admin.php`.

Regresiones compartidas de identidad, duplicados, dry-run y 5k permanecen en la suite canónica de importación y no fueron duplicadas en un engine JSON paralelo.

## Criterios de aceptación #58

- JSON usa pipeline canónico: **PASS**;
- no escrituras directas fuera de `RowExecutor`: **PASS**;
- dry-run obligatorio: **PASS**;
- `format_version` validado: **PASS**;
- versión ausente/futura/no soportada: **PASS**;
- root/shape/bytes/depth/count limits: **PASS**;
- claves desconocidas no crean meta arbitraria: **PASS**;
- campos privados excluidos en export: **PASS**;
- round-trip con fixture sintético: **PASS**;
- capability/nonce negativos runtime: **PASS**;
- benchmark 100/1k/5k documentado: **PASS**;
- CI final: **PASS, 13/13**;
- review sin P0/P1 abiertos: **PASS**;
- evidencia `QA_PASSED / READY_TO_MERGE`: **PASS**.

## Producción

`propiedadesmartinez.cl` permanece sin cambios. No hubo despliegue ni migración productiva en PR 3.7.

## Siguiente hito

Tras mergear PR #60, el siguiente hito canónico es **PR 3.8 — XLSX streaming + ADR/benchmark**, manteniendo la regla de transformar XLSX a filas normalizadas y reutilizar el pipeline existente.