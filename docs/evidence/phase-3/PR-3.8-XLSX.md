# Evidencia — PR 3.8 XLSX streaming + ADR/benchmark

Estado: `IN_PROGRESS / DEPENDENCY_SELECTED`.

Issue: #61  
PR: #62  
Rama: `feat/phase3-xlsx-foundation`  
Base: squash PR #60 `1e30be32582df04c6d30538bc40dbd3d4e685fc4`

## Objetivo

Añadir soporte XLSX sin duplicar mapping, validación, dry-run, identidad, executor ni batches, y seleccionar una dependencia solo después de comparar mantenimiento, compatibilidad, memoria, tamaño y seguridad.

## Estado de entrada

Fase 3.7 JSON WLA está cerrada y mergeada. Producción no se modifica.

El plugin declara `php >=8.1` y antes de 3.8 no tiene dependencias Composer runtime.

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

\* OpenSpout corresponde a la corrida determinista inmediatamente anterior; fixture SHA/checksum son idénticos. La corrida 4 revalidó OpenSpout `SUCCESS` con el mismo laboratorio.

Los resultados son comparativos de CI, no SLA productivo.

## Runs y artifacts

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

## Contrato de integración aprobado

La siguiente implementación debe:

1. inspeccionar el ZIP/OOXML antes de PhpSpreadsheet;
2. bloquear Zip Slip/path traversal y límites de archive bomb;
3. seleccionar sheet de forma controlada;
4. leer por chunks de 500 filas inicialmente;
5. normalizar a una fuente interna privada/reanudable;
6. reutilizar mapping, `DryRunEngine`, identidad, `BatchRunner` y `RowExecutor`;
7. mantener fórmulas como datos no ejecutables;
8. no disparar requests HTTP por relationships externos;
9. mantener temporales privados;
10. registrar benchmark y ZIP final después de integrar la dependencia real.

## Pendientes del PR 3.8

- incorporar PhpSpreadsheet 3.10.7 al build productivo de forma reproducible;
- resolver `composer.lock` de runtime/tooling;
- implementar `XlsxArchiveInspector` bounded;
- implementar source/normalización XLSX;
- workspace y cleanup;
- dry-run/batch/UI;
- negativos de ZIP/OOXML/fórmulas/limits;
- benchmark 1k/5k con implementación WLA real;
- medir ZIP final vs baseline 214.103 B;
- CI/review final y artifact/checksum.

## Producción

`propiedadesmartinez.cl` permanece sin cambios.
