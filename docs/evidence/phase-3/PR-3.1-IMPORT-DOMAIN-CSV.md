# Evidencia — PR 3.1 Import domain / CSV foundation

Estado: `IN_PROGRESS / QA_PENDING`.

Issue: #45  
Rama: `feat/phase3-import-domain-csv`

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
- detección acotada desde el header cuando no se configura delimitador;
- normalización de headers a snake_case ASCII básico;
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

Cobertura prevista antes del merge:

- transiciones válidas/invalidas;
- normalización de source key;
- match externo;
- fallback por código;
- conflictos de identidad;
- BOM;
- `;`, `,` y tab;
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
- no se registran filas completas como error.

## QA pendiente

Antes de `DONE` se debe ejecutar CI real del PR y corregir cualquier finding WPCS/PHPStan/PHPUnit/smoke. Los resultados concretos se registrarán aquí después de la ejecución.

## Producción

`propiedadesmartinez.cl` no se modifica ni recibe este código durante PR 3.1.
