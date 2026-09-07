# Evidencia — PR 3.8 XLSX streaming + ADR/benchmark

Estado: `QA_PASSED / READY_TO_MERGE`.

Issue: #61  
PR: #62  
Rama: `feat/phase3-xlsx-foundation`  
Base: squash PR #60 `1e30be32582df04c6d30538bc40dbd3d4e685fc4`

## Objetivo

Añadir soporte XLSX sin duplicar mapping, validación, dry-run, identidad, executor ni batches, y seleccionar una dependencia solo después de comparar mantenimiento, compatibilidad, memoria, tamaño y seguridad.

## Estado de entrada

Fase 3.7 JSON WLA está cerrada y mergeada. Producción no se modifica.

El plugin declara `php >=8.1` y antes de 3.8 no tenía dependencias Composer runtime.

### Baseline release

Artifact de Phase 1 CI previo a XLSX:

- workflow run: `34143906048`;
- artifact id: `10026953931`;
- digest: `sha256:4f6f355e7255fab7770cc00c438627f61d888ae187d8ceceedae0edaae613956`;
- ZIP instalable interno: **214.103 bytes**.

## Gobierno

D31, aprobada en Fase 0, establece **PhpSpreadsheet encapsulada en Import/Export**. El laboratorio evaluó OpenSpout como alternativa técnica, pero adoptarlo habría reemplazado una decisión aceptada.

ADR final: `docs/decisions/ADR-014-xlsx-dependency.md` — `ACCEPTED / IMPLEMENTS D31`.

Selección: **`phpoffice/phpspreadsheet` 3.10.7 exacta**, con lectura bounded mediante chunks de 500 filas como estrategia inicial.

## Laboratorio neutral

Archivos:

- `tests/benchmarks/generate-xlsx-fixture.php`;
- `tests/benchmarks/read-xlsx-candidate.php`;
- `.github/workflows/xlsx-dependency-lab.yml`.

El generador crea OOXML mínimo sin usar ninguna librería candidata. Los timestamps internos del ZIP se fijan para que todas las matrices consuman archivos byte-idénticos.

### Fixtures deterministas

| Dataset | Bytes | SHA-256 |
|---|---:|---|
| 1.000 filas / 6 columnas | 32.281 | `71a895edfec8d4748d80321c3ecc190db0045f263b7fe3557fcbd1826f668855` |
| 5.000 filas / 6 columnas | 153.182 | `83e53f3533350e2d1978d31895a048c0128fe72af57142158fa8ee343650cdc9` |

Checksums de valores leídos:

- 1k: `e78cb661eac298c4fbaa74493e4c62e4b18590631c56f7469ea2c36b3375b2f2`;
- 5k: `a261c6c9d2af976c9b110540d8be4bbbb24c25e21f2d43aefad67272727f1e6a`.

Todos los candidatos/modos produjeron esos mismos checksums.

## Resultado comparativo

### Footprint

| Candidato | Packages runtime | Vendor bytes | Vendor ZIP bytes | Composer audit |
|---|---:|---:|---:|---|
| OpenSpout 4.24.5 | 1 | 517.303 | 199.057 | limpio |
| PhpSpreadsheet 3.10.7 | 6 | 5.326.110 | 1.506.360 | limpio |
| PhpSpreadsheet 5.8.1 | 6 | 5.713.713 | 1.552.135 | limpio |

### Reader — evidencia final PHP 8.1.34

| Candidato | Dataset | Modo | Tiempo | Delta memoria |
|---|---:|---|---:|---:|
| OpenSpout 4.24.5 | 1k | streaming native | 88,21 ms* | 0 B sobre bloque baseline* |
| OpenSpout 4.24.5 | 5k | streaming native | 431,92 ms* | 0 B sobre bloque baseline* |
| PhpSpreadsheet 3.10.7 | 1k | native | 187,96 ms | 8 MiB |
| PhpSpreadsheet 3.10.7 | 5k | native | 841,35 ms | 20 MiB |
| PhpSpreadsheet 3.10.7 | 1k | chunked 500 | 258,55 ms | 6 MiB |
| PhpSpreadsheet 3.10.7 | 5k | chunked 500 | 2.610,68 ms | 8 MiB |
| PhpSpreadsheet 5.8.1 | 1k | native | 219,71 ms | 8 MiB |
| PhpSpreadsheet 5.8.1 | 5k | native | 960,75 ms | 22 MiB |
| PhpSpreadsheet 5.8.1 | 1k | chunked 500 | 297,88 ms | 6 MiB |
| PhpSpreadsheet 5.8.1 | 5k | chunked 500 | 2.853,63 ms | 10 MiB |

\* OpenSpout corresponde a la corrida determinista inmediatamente anterior; fixture SHA/checksum son idénticos.

Los resultados son comparativos de CI, no SLA productivo.

## Decisión técnica

Se adopta PhpSpreadsheet 3.10.7 porque:

- respeta D31 ya aprobada;
- mantiene PHP 8.1;
- su release del 12-07-2026 contiene security patches;
- `composer audit` del árbol resuelto está limpio;
- es menor y más eficiente que 5.8.1 en el laboratorio;
- el modo chunked reduce el delta de memoria de 20 MiB a 8 MiB en 5k;
- ~2,61 s para 5k es aceptable como punto de partida para un pipeline de importación por lotes/reanudable.

OpenSpout queda documentado como benchmark winner en footprint/rendimiento, pero no se adopta porque implicaría reemplazar D31 y fijar una release PHP 8.1 de 2024.

## Implementación integrada

La rama 3.8 incluye:

- `phpoffice/phpspreadsheet` **3.10.7 exacta** en `composer.json` y `composer.lock`;
- `XlsxArchiveInspector` con preflight ZIP/OOXML antes del reader;
- límites de archivo, entries, bytes descomprimidos, ratio de expansión, sheets, rows, columns y cell bytes;
- rechazo de path traversal/Zip Slip, macros/binarios, contenido ejecutable y relationships externos;
- `XlsxDocumentReader` por chunks de 500 filas;
- selección explícita de worksheet antes de normalizar;
- XLSX → NDJSON privado controlado por servidor;
- `source_format=xlsx` persistido en batches e historial;
- ejecución XLSX mediante el mismo `MappingProfile`, `DryRunEngine`, identidad, `BatchRunner` y `RowExecutor` que CSV/JSON;
- dry-run obligatorio;
- workspace con permisos privados fail-closed;
- janitor para drafts y uploads XLSX abandonados, sin borrar fuentes de batch reanudables;
- pestaña XLSX en el importador e historial filtrado por formato;
- capability + nonce para upload/selección/mapping/confirmación/ejecución/cancelación;
- regresión del smoke JSON actualizada para reconocer JSON/XLSX como fuentes normalizadas compartidas;
- verificación de integridad del XLSX antes y después de normalizar.

## Seguridad negativa cubierta

Los contratos XLSX cubren, entre otros:

- ZIP inválido;
- path traversal / Zip Slip;
- macros/binarios/partes ejecutables;
- relationships externos;
- archive expansion limits;
- exceso de sheets/rows/columns;
- worksheet inexistente;
- headers inválidos/duplicados;
- límites de celda;
- fórmulas como datos (`setReadDataOnly(true)`), sin evaluación remota;
- source hash / archivo cambiado;
- temporales privados y server-generated.

## CI específico 3.8

Workflow: `.github/workflows/xlsx-integration.yml`.

Matriz:

- PHP 8.1;
- PHP 8.3.

El gate ejecuta:

1. `composer validate --strict`;
2. install desde lock;
3. `composer audit`;
4. platform requirements;
5. PHPUnit `ImportXlsx`;
6. wiring canónico del wizard/workspace/batch/history/janitor;
7. PHPStan;
8. build ZIP instalable;
9. release smoke;
10. artifact con bytes y SHA-256.

Además, `Import UI Integration` cubre el handler de selección de hoja, el janitor XLSX y el historial `source_format=xlsx` sobre WordPress real.

## Runs y artifacts previos

### Corrida determinista de dependencia

Run `34145267527`: `SUCCESS`.

Artifacts:

- OpenSpout: `sha256:483ee7a5a84f7f8cb42ee3cb7040843184c79f7b8e7671df6ee914873d1976d8`;
- PhpSpreadsheet 3.10.7: `sha256:f5f9f7199b8689ac640f351ecb59398c34ee5f2604ee870ae19dcbb7635c7ccd`;
- PhpSpreadsheet 5.8.1: `sha256:43c5154a514a02f59fc03682a43f2407c1bf398b04b5d59179c8b1e6859c2381`.

### Corrida con estrategia bounded

Run `34151473724`: `SUCCESS`.

Artifacts:

- OpenSpout: `sha256:c7aee599f336077c3588728c1f0b59539a14e9a5b33663f85482d2c803c1eb1e`;
- PhpSpreadsheet 3.10.7: `sha256:9d0d85b9f17aa44241e157a01474bde898859acd965ba45343298df1aeb2018f`;
- PhpSpreadsheet 5.8.1: `sha256:a70a89f8861dde9a17dea4c0f8cf1dc4e40218d12ae6a6d8f1cfe64c8c3305a6`.

## QA final

Head funcional auditado: `12463f1fcf12f1a25c4216770ea6f8a504671591`.

**15/15 workflows asociados al head: `SUCCESS`.**

| Workflow | Run | Resultado |
|---|---:|---|
| Import Persistence Integration | `34156186941` | SUCCESS |
| Import Row Executor Integration | `34156186987` | SUCCESS |
| Bootstrap Smoke | `34156186935` | SUCCESS |
| Import UI Integration | `34156186939` | SUCCESS |
| JSON WLA Integration | `34156186952` | SUCCESS |
| Catalogue Quality Integration | `34156186978` | SUCCESS |
| XLSX Dependency Lab | `34156186947` | SUCCESS |
| Help Center Integration | `34156186931` | SUCCESS |
| Activity Integration | `34156186915` | SUCCESS |
| Import Batch Runner Integration | `34156186909` | SUCCESS |
| XLSX Integration | `34156186907` | SUCCESS |
| Settings UI Integration | `34156186904` | SUCCESS |
| Phase 1 CI | `34156186905` | SUCCESS |
| Dashboard Integration | `34156186921` | SUCCESS |
| Administration Quality Gate | `34156186966` | SUCCESS |

### XLSX Integration final

Run `34156186907`: `SUCCESS`.

PHP 8.1 y PHP 8.3 completaron:

- dependency lock/audit/platform requirements;
- **15 tests XLSX / 72 assertions**;
- wiring XLSX + janitor;
- PHPStan;
- build instalable;
- release smoke;
- artifact de evidencia.

### WordPress / compatibilidad

Phase 1 CI run `34156186905`: `SUCCESS`.

- WordPress 6.6.2 / PHP 8.1: SUCCESS;
- WordPress latest / PHP 8.3: SUCCESS;
- Quality Gate PHP 8.1: WPCS, PHPStan, PHPUnit, source smokes, build, release smoke y artifact: SUCCESS.

Import UI Integration run `34156186939`: `SUCCESS` en ambas matrices, incluyendo handler XLSX, cleanup de upload XLSX abandonado e historial bounded por formato.

JSON WLA Integration run `34156186952`: `SUCCESS` en ambas matrices, incluyendo round-trip, privacidad, capability/nonce y datasets 100/1k/5k.

Administration Quality Gate run `34156186966`: `SUCCESS`, incluyendo autorización/nonce/objeto, benchmark 100/1k/5k y Playwright.

### Artifact final / footprint

Baseline previo a XLSX: **214.103 bytes**.

ZIP final WLA Inmo 0.1.0-alpha: **1.746.465 bytes** en ambas matrices.

Delta: **+1.532.362 bytes**, equivalente a aproximadamente **+715,7%**; el ZIP final es aproximadamente **8,16×** el baseline. Este crecimiento corresponde principalmente a la dependencia XLSX y queda aceptado/documentado como trade-off de D31/ADR-014.

PHP 8.1:

- artifact id: `10031075021`;
- artifact digest: `sha256:4466785e90a1a610ca6ae32d9393f00772035c684679c3543ca5cef3d40811c2`;
- ZIP SHA-256: `35a0dc3e023ea28b7274d7bdca46fa18fa8648cd6fc4beb65cfdd11762c54820`.

PHP 8.3:

- artifact id: `10031060722`;
- artifact digest: `sha256:05d940b7df8a8daa4014b8d6275be67d1b6e7f945692824cf42468bb53f3a7ce`;
- ZIP SHA-256: `d1b366d8eafc0037aa39bed0ac3419e7b77a7619fe62a70f58026224163f9faa`.

Los ZIP tienen el mismo tamaño, pero no se declara identidad byte-a-byte entre runtimes; se registran ambos checksums de forma explícita.

### Review / findings

- review threads abiertos: **0**;
- findings P0/P1 abiertos conocidos: **0**;
- findings detectados durante QA (tipado PHPStan, smoke JSON heredado, historial `source_format`, janitor XLSX, permisos 0600 y comparación post-normalización) fueron corregidos antes de este head final.

## Resultado

PR 3.8 cumple sus criterios de aceptación y queda en estado:

`QA_PASSED / READY_TO_MERGE`.

El siguiente alcance funcional después del merge es **PR 3.9 — Media remota segura**.

## Producción

`propiedadesmartinez.cl` permanece sin cambios.
