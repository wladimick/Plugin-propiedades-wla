# Evidencia — PR 3.8 XLSX streaming + ADR/benchmark

Estado: `IN_PROGRESS / DECISION_PENDING`.

Issue: #61  
Rama: `feat/phase3-xlsx-foundation`  
Base: squash PR #60 `1e30be32582df04c6d30538bc40dbd3d4e685fc4`

## Objetivo

Añadir soporte XLSX sin duplicar mapping, validación, dry-run, identidad, executor ni batches, y seleccionar una dependencia solo después de comparar mantenimiento, compatibilidad, memoria, tamaño y seguridad.

## Estado de entrada

Fase 3.7 JSON WLA está cerrada y mergeada. Producción no se modifica.

El plugin declara:

```json
"require": {
  "php": ">=8.1"
}
```

No existen dependencias Composer runtime en el plugin antes de 3.8.

### Baseline release

Artifact de Phase 1 CI sobre el código previo a XLSX:

- workflow run: `34143906048`;
- artifact: `wla-inmo-0.1.0-alpha-quality`;
- artifact id: `10026953931`;
- digest: `sha256:4f6f355e7255fab7770cc00c438627f61d888ae187d8ceceedae0edaae613956`;
- ZIP instalable interno `dist/wla-inmo-0.1.0-alpha.zip`: **214.103 bytes**.

Este valor es el baseline para medir el costo real de la dependencia seleccionada.

## ADR

`docs/decisions/ADR-014-xlsx-dependency.md` está en estado `PROPOSED / DECISION_PENDING`.

No se agregará una librería XLSX al `composer.json` productivo hasta que exista:

- benchmark comparativo;
- audit de dependencias;
- medición de footprint;
- recomendación final;
- aprobación explícita.

## Investigación inicial — 2026-09-07

### OpenSpout

- release actual observada: 5.11.3;
- línea actual requiere PHP 8.4/8.5;
- v4.28.5 requiere PHP 8.2–8.4;
- **v4.24.5** requiere PHP 8.1–8.3;
- v4.24.5 fue publicada el 26-07-2024;
- MIT;
- runtime centrado en extensiones PHP, sin árbol adicional de paquetes Composer;
- proyecto orientado a streaming/scalable spreadsheet processing.

### PhpSpreadsheet

- release actual observada: 5.9.0;
- 5.9.0 requiere PHP 8.2+;
- **5.8.1** se declara como la última release general compatible con PHP 8.1 y fue publicada el 12-07-2026;
- **3.10.7** también exige PHP 8.1 y fue publicada con security patches el 12-07-2026;
- MIT;
- exige más extensiones y paquetes Composer runtime;
- documentación oficial lo describe como un modelo in-memory susceptible a límites de memoria.

## Laboratorio neutral

Se agregan:

- `tests/benchmarks/generate-xlsx-fixture.php`;
- `tests/benchmarks/read-xlsx-candidate.php`;
- `.github/workflows/xlsx-dependency-lab.yml`.

Características:

- genera XLSX OOXML mínimo sin usar ninguna candidata;
- mismo archivo para todos los readers;
- PHP 8.1;
- candidatos exactos:
  - `openspout/openspout:4.24.5`;
  - `phpoffice/phpspreadsheet:3.10.7`;
  - `phpoffice/phpspreadsheet:5.8.1`;
- instalación aislada fuera del `composer.json` productivo;
- `composer audit` registrado como evidencia;
- package count;
- vendor bytes;
- vendor ZIP bytes;
- lectura de 1.000 y 5.000 filas;
- elapsed time;
- memory baseline / peak / delta;
- checksum de valores leídos;
- artifact independiente por candidato.

## Reglas de seguridad del laboratorio

El laboratorio no implica aceptación de ninguna dependencia y no ejecuta código del XLSX. Los fixtures son sintéticos y generados en CI.

Antes de integrar XLSX al plugin todavía deberán implementarse y probarse:

- inspección ZIP previa;
- límite de compressed/uncompressed bytes;
- límite de entries y expansion ratio;
- Zip Slip/path traversal;
- límite de sheets/rows/columns/cell bytes;
- política de fórmulas;
- relaciones externas sin requests HTTP;
- temporales privados;
- integración con workspace/dry-run/batch canónico.

## Resultado pendiente

`PENDING CI`.

Después de la primera corrida del laboratorio se registrará aquí una tabla comparativa con:

- compatibilidad real PHP 8.1;
- audit/advisories del dependency tree resuelto;
- paquetes runtime;
- vendor bytes / ZIP bytes;
- tiempo y memoria para 1k/5k;
- recomendación técnica.

## Decisión crítica

La dependencia XLSX es una **decisión crítica** y requiere aprobación de Wladimick. El laboratorio y el ADR pueden avanzar antes de esa aprobación; la integración productiva no.

## Producción

`propiedadesmartinez.cl` permanece sin cambios.
