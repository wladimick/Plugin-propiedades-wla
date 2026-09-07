# ADR-014 — Dependencia XLSX para WLA Inmo

Estado: `PROPOSED / DECISION_PENDING`  
Fecha: 2026-09-07  
Issue: #61  
Fase: 3.8

## Contexto

WLA Inmo debe importar XLSX sin crear un segundo pipeline de escritura. El lector XLSX solo puede transformar el archivo externo a filas normalizadas consumibles por `MappingProfile`, `DryRunEngine`, identidad, `BatchRunner` y `RowExecutor`.

El plugin declara `php >=8.1`. Cambiar ese mínimo es una decisión de producto/plataforma y no puede ocurrir de forma implícita para incorporar XLSX.

El ZIP instalable medido antes de 3.8 pesa **214.103 bytes**. La dependencia elegida debe medirse también por el aumento real de tamaño del release.

## Restricciones

- PHP 8.1 debe seguir funcionando salvo decisión explícita posterior.
- WordPress mínimo soportado debe seguir pasando en CI.
- lectura bounded/streaming cuando sea viable;
- dry-run obligatorio;
- sin requests HTTP ni media remota;
- Zip Slip/path traversal bloqueado;
- límites de bytes comprimidos/descomprimidos, entries, sheets, rows, columns y cell bytes;
- fórmulas tratadas como datos, nunca ejecutadas;
- temporales server-generated y privados;
- dependencia MIT/BSD/Apache o licencia compatible con GPL-2.0-or-later del plugin;
- artifact/checksum y benchmark antes de aceptar la decisión.

## Candidatos

### A. OpenSpout

Proyecto: `openspout/openspout` — MIT.

Situación actual observada:

- versión actual consultada: 5.11.3;
- la línea actual exige PHP 8.4/8.5;
- OpenSpout v4 permite cambiar versiones PHP soportadas en releases menores;
- v4.28.5 ya exige PHP 8.2–8.4;
- **v4.24.5** exige PHP 8.1–8.3 y es la línea evaluable para nuestro mínimo actual;
- v4.24.5 fue publicada el 26-07-2024;
- sus runtime requirements son principalmente extensiones (`dom`, `fileinfo`, `filter`, `libxml`, `xmlreader`, `zip`) y no añade un árbol de paquetes Composer de runtime;
- el proyecto está orientado explícitamente a procesamiento streaming/scalable de CSV/XLSX/ODS y declara bajo uso de memoria.

Ventajas esperadas:

- arquitectura alineada con nuestro objetivo bounded/streaming;
- dependencia pequeña;
- menor presión de memoria;
- API centrada en iterar filas.

Riesgos:

- preservar PHP 8.1 obliga a fijar una versión de julio de 2024;
- la política del proyecto retira PHP EOL en versiones menores, por lo que no existe una línea moderna de OpenSpout compatible con PHP 8.1;
- pinning prolongado aumenta deuda de actualización/seguridad;
- antes de usarlo debe demostrarse que podemos envolver la lectura con nuestras propias protecciones ZIP/OOXML sin depender de supuestos internos.

Fuentes primarias:

- https://github.com/openspout/openspout
- https://github.com/openspout/openspout/blob/v4.24.5/composer.json
- https://github.com/openspout/openspout/releases/tag/v4.24.5
- https://github.com/openspout/openspout/blob/5.x/UPGRADE.md

### B. PhpSpreadsheet 5.8.1

Proyecto: `phpoffice/phpspreadsheet` — MIT.

Situación actual observada:

- versión actual consultada: 5.9.0, que exige PHP 8.2+;
- **5.8.1** fue publicada el 12-07-2026 y su release se declara explícitamente como la última con PHP 8.1;
- requiere PHP `^8.1`;
- añade varias extensiones (`gd`, `mbstring`, `simplexml`, `xml*`, `zip`, etc.) y paquetes runtime (`composer/pcre`, `zipstream`, `markbaker/complex`, `markbaker/matrix`, `psr/simple-cache`);
- PhpSpreadsheet mantiene un modelo de spreadsheet en memoria; su propia documentación advierte que puede ser exigente en memoria y ofrece caching/read filters para mitigarlo.

Ventajas esperadas:

- release PHP 8.1 muy reciente;
- ecosistema grande y soporte amplio de Excel/OOXML;
- muchas validaciones y casos de formato ya resueltos por la librería.

Riesgos:

- árbol de dependencias y requisitos de extensiones significativamente mayor;
- modelo in-memory menos alineado con imports grandes;
- puede aumentar de forma importante el ZIP instalable;
- 5.8.1 es el fin de soporte general de PHP 8.1 en la línea principal, por lo que requiere política clara de actualización.

Fuentes primarias:

- https://github.com/PHPOffice/PhpSpreadsheet/releases/tag/5.8.1
- https://github.com/PHPOffice/PhpSpreadsheet/blob/5.8.1/composer.json
- https://phpspreadsheet.readthedocs.io/

### C. PhpSpreadsheet 3.10.7 — línea de mantenimiento PHP 8.1

También se medirá `3.10.7` porque:

- exige PHP `^8.1`;
- fue publicada el 12-07-2026 con security patches;
- conserva el mismo perfil general de extensiones/dependencias que PhpSpreadsheet;
- puede resultar una opción más conservadora para PHP 8.1 si demuestra mejor encaje de mantenimiento que 5.8.1.

No se asume que esta rama recibirá soporte indefinido; el ADR solo registrará hechos verificables al momento de la decisión.

Fuente primaria:

- https://github.com/PHPOffice/PhpSpreadsheet/blob/3.10.7/composer.json

## Laboratorio obligatorio antes de decidir

El PR 3.8 ejecutará un benchmark aislado en PHP 8.1 para los tres candidatos:

1. instalar cada candidato en un proyecto Composer temporal, sin modificar el `composer.json` productivo;
2. registrar cantidad de paquetes, bytes del directorio vendor y ZIP comprimido de vendor;
3. leer el **mismo** XLSX sintético de 1.000 y 5.000 filas;
4. registrar tiempo, memoria baseline, peak y delta;
5. conservar resultados como artifact de GitHub Actions;
6. después de seleccionar candidato, repetir el build real del plugin y comparar contra baseline 214.103 B.

Los resultados de laboratorio son comparativos, no SLA.

## Opciones de decisión

### Opción 1 — OpenSpout 4.24.5

Elegir solo si el ahorro de memoria/tamaño es material y se acepta explícitamente la deuda de pinning PHP 8.1 en una release 2024.

### Opción 2 — PhpSpreadsheet compatible con PHP 8.1

Elegir la línea que presente el mejor balance de mantenimiento/seguridad y cuyo costo de memoria/tamaño sea aceptable con nuestros límites y estrategia de normalización.

### Opción 3 — No agregar librería todavía

Mantener XLSX fuera de alcance hasta elevar el mínimo PHP o implementar una capa OOXML propia bounded. Esta opción evita pinning pero retrasa 3.8; una implementación OOXML propia aumenta superficie de seguridad/mantenimiento y no es la recomendación inicial.

## Recomendación preliminar

`PENDING BENCHMARK`.

Con la información previa al benchmark:

- no se recomienda adoptar OpenSpout 4.24.5 únicamente por rendimiento, porque conservar PHP 8.1 obliga a una release de 2024;
- no se recomienda adoptar PhpSpreadsheet únicamente por mantenimiento, porque su modelo in-memory y su árbol de dependencias deben cuantificarse;
- la decisión definitiva requiere los artifacts de laboratorio y aprobación explícita.

## Criterio de cierre

Este ADR pasa a `ACCEPTED` solo cuando:

- laboratorio comparativo está verde y documentado;
- compatibilidad PHP 8.1 está demostrada en CI;
- tamaño y memoria están registrados;
- riesgos ZIP/OOXML tienen mitigación de diseño;
- existe recomendación final;
- Wladimick aprueba explícitamente la dependencia/versión o una alternativa.

Hasta entonces **no se agrega ninguna dependencia XLSX al `composer.json` productivo**.
