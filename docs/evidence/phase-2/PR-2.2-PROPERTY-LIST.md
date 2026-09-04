# Evidencia — Fase 2 / PR 2.2 Listado profesional de Propiedades

Estado documental: `QA_PASSED / MERGE_PENDING`.

Issue: #25  
PR: #26  
Rama: `feat/phase2-property-list`

## Objetivo

Convertir el listado nativo de `wla_property` en una herramienta inmobiliaria rápida, legible y segura sin reemplazar la paginación ni el editor nativo de WordPress.

## Implementación

### Columnas administrativas

`Admin\\PropertyList` reemplaza las columnas genéricas por una vista orientada a operación inmobiliaria:

- miniatura;
- título;
- código;
- operación;
- tipo de propiedad;
- ubicación;
- precio principal;
- estado comercial;
- destacada;
- última actualización.

No se imprime `external_id`, `private_address`, `internal_notes` ni otro campo marcado como privado.

### Precio canónico

El listado reutiliza los campos de dominio de Fase 1:

1. `hide_price` tiene prioridad y muestra `Precio oculto`;
2. `price_on_request` muestra `A consultar`;
3. se usa `currency_primary` de la propiedad y, si falta, el ajuste global;
4. CLP usa `price_clp`;
5. UF usa `price_uf`;
6. USD usa `price_usd`.

Esto evita repetir el problema histórico de Propiedades Martínez donde un campo legacy podía tener prioridad sobre el precio canónico.

### Filtros

Filtros de solo lectura:

- operación;
- tipo;
- región;
- comuna;
- sector;
- estado;
- destacada/no destacada.

Todos los valores GET se normalizan con `wp_unslash()` + `sanitize_key()` o, para destacada, mediante whitelist estricta `0/1`, y se trasladan a query vars internas. No existe mutación de estado y no se utiliza nonce para navegación/filtrado de lectura.

### Búsqueda

La búsqueda nativa por título/contenido se conserva y se amplía para encontrar:

- `property_code`;
- `external_id`.

`external_id` se utiliza únicamente como dato de búsqueda administrativa y no se renderiza en la tabla.

### Índice derivado

Cuando no hay filtros, búsqueda ni orden por código, no se incorpora el índice al query.

Cuando es necesario, el main query administrativo de `wla_property` incorpora un `LEFT JOIN` a `wp_wla_property_index` por `property_id`.

La tabla derivada se eleva a DB schema version `2` con índices adicionales:

- `region_slug`;
- `sector_slug`;
- `status_featured (status, featured)`.

Se mantienen los índices previos de operación/estado, tipo, comuna, precio y destacados/actualización.

`dbDelta()` realiza el upgrade mediante el versionado ya existente en `Core\\Installer`; no se recrea ni borra la tabla.

### Ordenamiento

Se habilita ordenamiento seguro por código de propiedad. La columna SQL está fijada por código y la dirección se restringe a `ASC`/`DESC`; no se acepta un identificador de columna arbitrario desde GET.

La columna de actualización reutiliza el ordenamiento nativo `modified`.

### Performance

- paginación server-side nativa;
- no SPA;
- no React;
- no JavaScript nuevo;
- no consulta de galería completa en filas;
- miniaturas pequeñas y lazy-loaded;
- metadatos/términos consumidos mediante APIs de WordPress y sus caches;
- join al índice solo si la query realmente lo necesita;
- filtros no afectan frontend ni otros post types.

## Seguridad

- capability del CPT continúa siendo la barrera de acceso;
- filtros GET son de solo lectura y se sanitizan o validan por whitelist;
- `external_id` no se imprime;
- SQL de valores utiliza `$wpdb->prepare()`;
- columnas SQL/alias son constantes internas;
- no hay nuevas escrituras de negocio;
- no se modifica producción.

## Tests incorporados

`tests/smoke/property-list.php` cubre:

- contrato de columnas;
- formato CLP canónico;
- precedencia `hide_price` / `price_on_request`;
- humanización de estado;
- sanitización de filtros;
- filtro destacada estricto;
- join al índice;
- WHERE preparados;
- búsqueda por código/external_id;
- orden por código whitelisted;
- aislamiento de otros post types.

La integración WordPress real valida:

- clase `Admin\\PropertyList` disponible dentro del plugin activo;
- DB schema upgrade aplicado;
- índices `region_slug`, `sector_slug` y `status_featured` presentes;
- precio CLP y estado se presentan desde datos canónicos;
- indexación de propiedad sintética continúa funcionando;
- desactivación/uninstall siguen preservando datos.

El release smoke exige `src/Admin/PropertyList.php` dentro del ZIP instalable.

## QA automático final

Head validado: `9baffcbcb54af6ac32a50fe037b042a73a9bab2f`.

### Phase 1 CI heredado

Run final: `33829256386`  
Resultado global: `SUCCESS`.

- Quality Gate / PHP 8.1: SUCCESS;
- WPCS security profile: SUCCESS;
- PHPStan 2.2: SUCCESS;
- PHPUnit: `3 tests / 40 assertions`;
- smoke `property-list.php`: SUCCESS;
- todos los smoke tests heredados: SUCCESS;
- build ZIP: SUCCESS;
- release ZIP smoke: SUCCESS;
- WordPress `6.6.2` + PHP `8.1`: SUCCESS;
- WordPress `latest` + PHP `8.3`: SUCCESS;
- desactivación/uninstall conservan datos: SUCCESS.

### Bootstrap Smoke

Run final: `33829256549`  
Resultado: `SUCCESS`.

## Artifact final QA

- Artifact ID: `9921060323`;
- Nombre: `wla-inmo-0.1.0-alpha-quality`;
- Tamaño del contenedor: `64436` bytes;
- Digest del artifact: `sha256:37bf4b613fd59c221126307f0147cc2241f476d3dfa4fe76301abc9d2f54dcae`;
- ZIP instalable SHA-256: `660253f9ddb801ca64471066234f7db05fdbec2c6fd6674a9f34edfd4af611bb`;
- Expira: `2026-12-03`.

## Historial de findings

### ADMIN-LIST-QA-1 — WPCS y filtros GET

El run previo `33829158975` detectó dos errores del sniff `ValidatedSanitizedInput` sobre lecturas de `$_GET` utilizadas exclusivamente para filtros del listado.

Análisis: los valores ya se normalizaban inmediatamente con `sanitize_key()` o se restringían explícitamente a `0/1`, y no disparaban mutaciones. El problema era la incapacidad del sniff para seguir esa sanitización a través de la variable intermedia.

Corrección: se conservaron la sanitización/whitelist reales y se añadieron excepciones PHPCS exclusivamente en las dos lecturas concretas, documentando tanto `NonceVerification.Recommended` como `ValidatedSanitizedInput.InputNotSanitized`. No se deshabilitó ningún sniff global.

Resultado: WPCS pasó en el run final `33829256386`.

### ADMIN-LIST-QA-2 — formato monetario dependiente de locale en integración

Antes del run final se revisó la aserción de precio CLP y se detectó que exigir literalmente `$123.456.789` podía depender del locale del WordPress de CI.

Corrección: la integración valida el prefijo monetario y los dígitos canónicos independientemente del separador local, manteniendo el render productivo mediante `number_format_i18n()`.

Resultado: ambas matrices WordPress pasaron.

## Consideración de alcance

El índice derivado sigue conservando la regla de Fase 1: solo contiene propiedades publicadas. No se incorporan borradores ni propiedades privadas al índice público para resolver filtros administrativos. El listado sin filtros continúa mostrando los estados que WordPress permita; una futura necesidad de búsqueda indexada sobre borradores deberá resolverse mediante un mecanismo administrativo separado y no debilitando el contrato del índice público.

## Producción

`propiedadesmartinez.cl` no ha sido modificado.

## Cierre

Todos los quality gates aplicables están verdes. PR #26 queda `QA_PASSED / MERGE_PENDING` y solo pasará a `DONE` después del squash merge.
