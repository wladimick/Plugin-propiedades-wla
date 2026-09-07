# ADR-014 — Dependencia XLSX para WLA Inmo

Estado: `ACCEPTED / IMPLEMENTS D31`  
Fecha: 2026-09-07  
Issue: #61  
PR: #62  
Fase: 3.8

## Contexto

WLA Inmo debe importar XLSX sin crear un segundo pipeline de escritura. El lector XLSX solo puede transformar el archivo externo a filas normalizadas consumibles por `MappingProfile`, `DryRunEngine`, identidad, `BatchRunner` y `RowExecutor`.

El plugin declara `php >=8.1`. Cambiar ese mínimo es una decisión de producto/plataforma y no puede ocurrir de forma implícita para incorporar XLSX.

La Fase 0 ya aprobó explícitamente **D31: `PhpSpreadsheet` encapsulada en Import/Export**. Por tanto, adoptar OpenSpout habría requerido reemplazar una decisión estructural aceptada. Este ADR no reemplaza D31: selecciona una versión/estrategia concreta dentro de ella después de medir alternativas.

El ZIP instalable medido antes de 3.8 pesa **214.103 bytes**.

## Restricciones

- PHP 8.1 debe seguir funcionando;
- WordPress mínimo debe seguir pasando en CI;
- lectura bounded por chunks;
- dry-run obligatorio;
- sin requests HTTP ni media remota;
- inspección ZIP/OOXML previa al reader;
- Zip Slip/path traversal bloqueado;
- límites de bytes comprimidos/descomprimidos, entries, sheets, rows, columns y cell bytes;
- fórmulas tratadas como datos, nunca ejecutadas;
- temporales server-generated y privados;
- dependencia con licencia compatible con GPL-2.0-or-later;
- artifact/checksum y benchmark reproducible.

## Candidatos medidos

Todos fueron instalados en un proyecto Composer aislado bajo PHP **8.1.34**, sin modificar inicialmente el `composer.json` productivo. Los tres resolvieron con `composer audit` sin advisories ni paquetes abandoned en las corridas registradas.

| Candidato | Runtime packages | Vendor bytes | Vendor ZIP bytes | Estado PHP 8.1 |
|---|---:|---:|---:|---|
| OpenSpout 4.24.5 | 1 | 517.303 | 199.057 | compatible, release 26-07-2024 |
| PhpSpreadsheet 3.10.7 | 6 | 5.326.110 | 1.506.360 | compatible, security release 12-07-2026 |
| PhpSpreadsheet 5.8.1 | 6 | 5.713.713 | 1.552.135 | última general con PHP 8.1, 12-07-2026 |

## Metodología reproducible

El laboratorio usa un generador OOXML neutral que no depende de ninguna candidata. Los timestamps internos del ZIP se fijan para producir fixtures byte-deterministas.

Fixtures finales compartidos por las tres candidatas:

- 1.000 filas / 6 columnas: `32.281 B`, SHA-256 `71a895edfec8d4748d80321c3ecc190db0045f263b7fe3557fcbd1826f668855`;
- 5.000 filas / 6 columnas: `153.182 B`, SHA-256 `83e53f3533350e2d1978d31895a048c0128fe72af57142158fa8ee343650cdc9`.

Los checksums de valores leídos coinciden entre candidatos y modos:

- 1k: `e78cb661eac298c4fbaa74493e4c62e4b18590631c56f7469ea2c36b3375b2f2`;
- 5k: `a261c6c9d2af976c9b110540d8be4bbbb24c25e21f2d43aefad67272727f1e6a`.

## Benchmark final — PHP 8.1.34

Los tiempos son evidencia sintética de CI, no SLA.

### OpenSpout 4.24.5 — streaming nativo

| Dataset | Tiempo | Delta memoria observado |
|---|---:|---:|
| 1k | 88,21 ms en corrida determinista inicial | 0 B sobre bloque baseline |
| 5k | 431,92 ms en corrida determinista inicial | 0 B sobre bloque baseline |

OpenSpout es claramente el candidato de menor footprint y mejor rendimiento bruto.

### PhpSpreadsheet 3.10.7

| Dataset | Modo | Tiempo | Delta memoria observado |
|---|---|---:|---:|
| 1k | native | 187,96 ms | 8 MiB |
| 5k | native | 841,35 ms | 20 MiB |
| 1k | chunked 500 | 258,55 ms | 6 MiB |
| 5k | chunked 500 | 2.610,68 ms | 8 MiB |

### PhpSpreadsheet 5.8.1

| Dataset | Modo | Tiempo | Delta memoria observado |
|---|---|---:|---:|
| 1k | native | 219,71 ms | 8 MiB |
| 5k | native | 960,75 ms | 22 MiB |
| 1k | chunked 500 | 297,88 ms | 6 MiB |
| 5k | chunked 500 | 2.853,63 ms | 10 MiB |

## Decisión

Se selecciona **`phpoffice/phpspreadsheet` 3.10.7 exacta**, encapsulada exclusivamente dentro del subsistema Import/Export XLSX.

Estrategia obligatoria para imports:

1. inspección ZIP/OOXML propia y bounded antes de entregar el archivo a PhpSpreadsheet;
2. selección controlada de sheet;
3. `setReadDataOnly(true)` y `setReadEmptyCells(false)`;
4. `IReadFilter` por chunks de **500 filas** como punto de partida;
5. normalización a una fuente interna reanudable controlada por servidor;
6. mapping, dry-run, identidad, `BatchRunner` y `RowExecutor` permanecen compartidos con CSV/JSON;
7. ningún workbook completo queda vivo durante el procesamiento del batch confirmado.

El tamaño de chunk puede refinarse con evidencia posterior sin cambiar esta decisión.

## Por qué 3.10.7 y no 5.8.1

- ambas son compatibles con PHP 8.1;
- ambas tienen `composer audit` limpio en el laboratorio;
- 3.10.7 fue publicada el 12-07-2026 específicamente con **security patches**;
- 3.10.7 tiene menor footprint;
- en el benchmark final fue más rápida y usó menos peak memory en 5k, tanto native como chunked;
- WLA Inmo no necesita las capacidades adicionales de spreadsheet engine de 5.8.1 para este importador.

## Por qué no OpenSpout 4.24.5

OpenSpout ganó claramente en tamaño, memoria y tiempo. Sin embargo:

- usarlo reemplazaría D31 ya aprobada en Fase 0;
- conservar PHP 8.1 obliga a fijar una release del 26-07-2024;
- versiones v4 posteriores ya eliminaron PHP 8.1 de su rango soportado;
- el beneficio de memoria puede obtenerse en un nivel aceptable usando PhpSpreadsheet 3.10.7 por chunks;
- la ventaja de OpenSpout no justifica por sí sola introducir una excepción de gobierno y una dependencia antigua.

OpenSpout queda documentado como alternativa futura a reevaluar cuando cambie el mínimo PHP o si los benchmarks reales de producción invalidan el presupuesto actual.

## Implementación resultante

La decisión quedó materializada en PR #62 con:

- `composer.lock` versionado;
- reader XLSX bounded;
- preflight ZIP/OOXML antes de PhpSpreadsheet;
- selección explícita de hoja;
- normalización XLSX → NDJSON privado;
- pipeline canónico compartido con CSV/JSON;
- temporales privados fail-closed;
- cleanup de uploads XLSX abandonados;
- CI PHP 8.1/8.3, PHPUnit XLSX, PHPStan, build y smoke.

Esto confirma que ADR-014 no es solo una decisión teórica: es el contrato implementado de 3.8.

## Reproducibilidad / artifacts

XLSX Dependency Lab run `34151473724`: `SUCCESS`.

Artifacts:

- OpenSpout 4.24.5: `sha256:c7aee599f336077c3588728c1f0b59539a14e9a5b33663f85482d2c803c1eb1e`;
- PhpSpreadsheet 3.10.7: `sha256:9d0d85b9f17aa44241e157a01474bde898859acd965ba45343298df1aeb2018f`;
- PhpSpreadsheet 5.8.1: `sha256:a70a89f8861dde9a17dea4c0f8cf1dc4e40218d12ae6a6d8f1cfe64c8c3305a6`.

## Consecuencias

### Positivas

- respeta D31 y PHP 8.1;
- dependencia con security release reciente;
- memoria bounded demostrada en 5k mediante chunks;
- integración aislada y reemplazable detrás de contratos WLA;
- no se crea un pipeline paralelo.

### Trade-offs

- vendor aumenta aproximadamente 5,3 MB sin comprimir antes del build final;
- lectura chunked de 5k es aproximadamente 3 veces más lenta que native en el laboratorio;
- PhpSpreadsheet exige más extensiones/runtime packages que OpenSpout;
- PHP 8.1 está al final de la ventana de soporte de varias librerías y deberá revisarse antes de Beta/1.0.

## Revisión futura

Reevaluar esta decisión cuando ocurra cualquiera de estos eventos:

- WLA Inmo eleve PHP mínimo a 8.2+;
- PhpSpreadsheet 3.10.x deje de recibir security backports relevantes;
- un `composer audit` reporte advisory no mitigable;
- datasets reales excedan de forma material los budgets de memoria/tiempo;
- una MAJOR release permita reemplazar D31 explícitamente.

## Trazabilidad

Este ADR **implementa D31 y no la reemplaza**. Por tanto no modifica D01–D75; concreta la versión y estrategia después del benchmark obligatorio de Fase 3.8.
