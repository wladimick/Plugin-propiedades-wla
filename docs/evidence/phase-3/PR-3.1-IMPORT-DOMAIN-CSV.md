# Evidencia — PR 3.1 Import domain / CSV foundation

Estado: `DONE`.

Issue: #45 — `CLOSED`  
PR: #46 — `MERGED`  
Rama: `feat/phase3-import-domain-csv`  
Head funcional validado: `c134eda9c0cad3a94a02729ada5da5f2c250e778`  
Squash en `main`: `74dcc032946df8ea582a57d1f58521a45f7d99f0`

## Objetivo

Crear la primera base funcional de Fase 3 sin adelantar persistencia masiva, UI, XLSX, JSON ni media remota.

## Implementación

### Dominio de batch

`WLA\Inmo\Import\BatchStatus` define estados explícitos y transiciones permitidas:

- uploaded → mapped → validated → dry_run_ready → confirmed → processing;
- processing puede completar, fallar o pausar;
- paused/failed pueden volver a processing;
- cancelación solo desde estados previos donde todavía es coherente;
- rollback queda representado como contrato futuro después de completed.

No existe un estado ambiguo `done_with_errors`.

### Source key

`SourceKey` normaliza una clave de proveedor/origen a un identificador estable, pequeño y allowlisted.

Ejemplo:

```text
Portal Proveedor Ñ 2026 → portal_proveedor_n_2026
```

La normalización translitera vocales acentuadas, Ü y Ñ tanto en mayúscula como en minúscula antes de reducir el valor al alfabeto permitido. Esto evita que una `Ñ` mayúscula desaparezca y provoque colisiones silenciosas entre proveedores.

El parser no usa el nombre del archivo como identidad de fuente.

### Identidad read-only

`IdentityCandidate`, `IdentityResolver` e `IdentityResolution` implementan el contrato:

1. `(source_key, external_id)` cuando ambos están presentes;
2. fallback a `property_code`;
3. external ID sin source key se trata como conflicto, no se adivina;
4. external identity y property code que resuelven a propiedades distintas generan `identity_disagreement`;
5. múltiples matches generan conflicto explícito;
6. el resolver solo recibe callbacks de lectura y no contiene escrituras WordPress.

### CSV incremental

`CsvReader` usa `SplFileObject` y expone un `Generator`, evitando diseñar la lectura alrededor de `file_get_contents()` o de cargar todas las filas en un array.

Contratos incorporados:

- UTF-8;
- BOM UTF-8 soportado;
- delimitadores soportados: coma, punto y coma y tab;
- detección desde la primera fila no vacía cuando no se configura delimitador;
- normalización de headers a snake_case ASCII básico, incluyendo acentos españoles en mayúscula/minúscula;
- headers duplicados después de normalizar: error;
- límite de filas;
- límite de columnas;
- límite de bytes por celda;
- fila con columnas faltantes: se completa con vacío;
- fila con más columnas que el header: error;
- filas completamente vacías: omitidas;
- encoding inválido: error controlado;
- contenido similar a fórmulas se conserva como string y nunca se evalúa.

Los mensajes de excepción incluyen una razón técnica corta y número de fila cuando corresponde, sin copiar el payload completo.

## Tests incorporados

- `tests/unit/ImportFoundationTest.php`;
- `tests/smoke/import-csv.php`;
- namespace `plugin/wla-inmo/src/Import` añadido al gate PHPStan.

Cobertura final:

- transiciones válidas/inválidas;
- normalización de source key con `Ñ` mayúscula;
- match externo;
- fallback por código;
- conflictos de identidad;
- BOM;
- `;`, `,` y tab;
- líneas vacías antes del header;
- headers españoles en mayúscula;
- formula-like strings tratados como datos;
- headers duplicados;
- row/cell/encoding limits;
- Generator y fixture de 250 filas.

## Mutaciones deliberadamente ausentes

Este PR no contiene:

- `wp_insert_post()`;
- upsert de propiedades;
- creación automática de términos;
- descarga/sideload de media;
- persistencia de batches;
- wizard de administración.

## Seguridad

- no se ejecuta contenido CSV;
- no se construyen nombres de meta desde headers arbitrarios;
- no se descargan URLs;
- no se escriben paths entregados por el archivo;
- límites básicos existen antes de mapping/persistencia;
- no se registran filas completas como error;
- external IDs nunca se resuelven globalmente sin `source_key`.

## Findings detectados y corregidos

### F3.1-001 — WPCS sobre metadata numérica de excepción

WPCS interpretó el número interno de fila entregado al constructor de `CsvException` como output no escapado.

Clasificación: tooling/static-analysis, no vulnerabilidad de render.  
Corrección: se acotó la excepción del sniff exclusivamente a `CsvReader`, documentando que los mensajes son estáticos y el número de fila no se renderiza en esa clase. WPCS final quedó verde.

### F3.1-002 — guard redundante después de `array_combine`

PHPStan detectó que el `is_array()` posterior era imposible de fallar bajo los contratos de longitud ya demostrados.

Clasificación: limpieza de tipos.  
Corrección: se eliminó el branch muerto. PHPStan final quedó verde.

### F3.1-003 — `Ñ` mayúscula podía desaparecer del source key

Review detectó que `strtolower()` de PHP no translitera caracteres UTF-8 mayúsculos, pudiendo transformar proveedores distintos en la misma clave.

Clasificación: P1 / integridad de identidad externa.  
Corrección: transliteración explícita de variantes mayúsculas y minúsculas antes de `strtolower()`, con prueba de regresión.

### F3.1-004 — delimitador con líneas vacías iniciales

Review detectó que una primera línea vacía hacía caer la autodetección a coma aunque el archivo real fuera `;` o tab.

Clasificación: P2 / robustez de importación.  
Corrección: la detección avanza hasta la primera línea no vacía y valida UTF-8 durante el recorrido. Se agregó prueba de regresión.

### F3.1-005 — fixture de límite por celda probaba el header

La primera versión del test configuró un límite menor que el header `codigo`, por lo que correctamente fallaba en fila 1 y no en la celda de datos pretendida.

Clasificación: finding del test.  
Corrección: el fixture usa un header válido dentro del budget y una celda de datos que lo excede.

## QA final

Head funcional: `c134eda9c0cad3a94a02729ada5da5f2c250e778`.

Phase 1 CI `33877587885`: `SUCCESS`.

- PHP syntax: `SUCCESS`;
- WordPress Coding Standards: `SUCCESS`;
- PHPStan: `SUCCESS`, 0 errores;
- PHPUnit: **13 tests / 91 assertions**, `SUCCESS`;
- source smoke tests: `SUCCESS`, incluido `WLA Inmo import CSV foundation smoke tests passed`;
- build del ZIP: `SUCCESS`;
- release ZIP smoke: `SUCCESS`;
- WordPress 6.6.2 / PHP 8.1: `SUCCESS`;
- WordPress latest / PHP 8.3: `SUCCESS`;
- preservación tras deactivate/uninstall: `SUCCESS`.

Regresiones heredadas sobre el mismo head:

- Bootstrap Smoke: `SUCCESS`;
- Catalogue Quality Integration: `SUCCESS`;
- Help Center Integration: `SUCCESS`;
- Settings UI Integration: `SUCCESS`;
- Activity Integration: `SUCCESS`;
- Dashboard Integration: `SUCCESS`;
- Administration Quality Gate / Playwright: `SUCCESS`.

El commit documental final previo al merge también volvió a pasar los checks automáticos correspondientes. No quedan findings críticos/altos conocidos abiertos en PR 3.1.

## Artifact y checksum

Workflow `33877587885`:

- artifact `9938487353`;
- artifact digest `sha256:2c41ae15ff69dc8784257d16458a60d1ec921c231daf2a1fd23e3b8d138bc5a4`;
- ZIP `wla-inmo-0.1.0-alpha.zip` SHA-256 `0c78bbcee835517f56d83856b5f0c12ee95ec736db93d7c20bd00bcdb95a7b5e`.

## Criterio de salida

PR 3.1 está `DONE` y fue integrado mediante squash en `main`.

Fase 3 continúa con **PR 3.2 — Mapping + Validation + Dry-run**. La persistencia real de propiedades sigue deliberadamente diferida a PR 3.3.

## Producción

`propiedadesmartinez.cl` no fue modificado ni recibió este código durante PR 3.1.
