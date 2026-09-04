# Evidencia — Fase 2 / PR 2.2 Listado profesional de Propiedades

Estado documental: `IN_PROGRESS / QA PENDING`.

Issue: #25  
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

Todos los valores GET se normalizan con `wp_unslash()` + `sanitize_key()` y se trasladan a query vars internas. No existe mutación de estado y no se utiliza nonce para navegación/filtrado de lectura.

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
- miniaturas 72×72;
- metadatos/términos consumidos mediante APIs de WordPress y sus caches;
- join al índice solo si la query realmente lo necesita;
- filtros no afectan frontend ni otros post types.

## Seguridad

- capability del CPT continúa siendo la barrera de acceso;
- filtros GET son de solo lectura y se sanitizan;
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

## Producción

`propiedadesmartinez.cl` no ha sido modificado.

## Cierre pendiente

Antes de marcar PR 2.2 como `DONE` deben registrarse:

- número de PR;
- CI final verde;
- Bootstrap Smoke final;
- artifact/digest;
- checksum ZIP;
- findings y correcciones si aparecen;
- squash merge.
