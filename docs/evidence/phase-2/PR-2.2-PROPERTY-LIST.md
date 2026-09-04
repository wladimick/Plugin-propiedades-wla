# Evidencia — Fase 2 / PR 2.2 Listado profesional de Propiedades

Estado documental: `DONE`.

Issue: #25 — CLOSED  
PR: #26 — MERGED  
Rama: `feat/phase2-property-list`  
Squash merge: `15991b70d471fd2ba2ecbf88a762b2fdd9996b09`

## Objetivo cumplido

El listado nativo de `wla_property` quedó transformado en una herramienta administrativa inmobiliaria, manteniendo paginación y permisos de WordPress y reutilizando el índice derivado de WLA Inmo.

## Entregables

- columnas: miniatura, título, código, operación, tipo, ubicación, precio, estado, destacada y actualización;
- precio desde campos canónicos con prioridad `hide_price` → `price_on_request` → moneda principal;
- filtros por operación, tipo, región, comuna, sector, estado y destacada;
- búsqueda ampliada por `property_code` y `external_id`, sin renderizar `external_id`;
- orden seguro por código;
- `LEFT JOIN` al índice únicamente en el main query administrativo cuando se necesita;
- DB schema version `2` con índices para región, sector y estado/destacada;
- CSS responsive y miniaturas ligeras;
- smoke test específico y validación en WordPress real;
- release smoke exige `Admin\\PropertyList` dentro del ZIP.

## Seguridad y privacidad

- capability del CPT continúa controlando el acceso;
- filtros GET son de solo lectura, sanitizados o restringidos por whitelist;
- valores SQL pasan por `$wpdb->prepare()`;
- alias/columnas SQL son constantes internas;
- `external_id`, `private_address` e `internal_notes` no se imprimen en la tabla;
- no se introdujeron escrituras de negocio;
- no se modificó producción.

## Performance

- paginación server-side nativa;
- cero SPA/React/JavaScript nuevo;
- no se cargan galerías completas por fila;
- el índice se une solo cuando filtros, búsqueda o sorting lo requieren;
- filtros aislados del frontend y de otros post types.

## QA final

Head de código validado: `9baffcbcb54af6ac32a50fe037b042a73a9bab2f`.

- Phase 1 CI heredado `33829256386`: SUCCESS;
- Quality Gate PHP 8.1: SUCCESS;
- WPCS security profile: SUCCESS;
- PHPStan 2.2: SUCCESS;
- PHPUnit: `3 tests / 40 assertions`;
- smoke `property-list.php`: SUCCESS;
- WordPress `6.6.2` + PHP `8.1`: SUCCESS;
- WordPress `latest` + PHP `8.3`: SUCCESS;
- Bootstrap Smoke `33829256549`: SUCCESS;
- desactivación/uninstall conservan datos: SUCCESS.

Artifact QA:

- Artifact ID `9921060323`;
- nombre `wla-inmo-0.1.0-alpha-quality`;
- digest `sha256:37bf4b613fd59c221126307f0147cc2241f476d3dfa4fe76301abc9d2f54dcae`;
- ZIP instalable SHA-256 `660253f9ddb801ca64471066234f7db05fdbec2c6fd6674a9f34edfd4af611bb`;
- expiración `2026-12-03`.

## Findings corregidos

### ADMIN-LIST-QA-1 — filtros GET y WPCS

Un run previo detectó que WPCS no seguía la sanitización/whitelist a través de variables intermedias. Se mantuvieron los controles reales y se documentaron excepciones PHPCS únicamente en las dos lecturas de filtros de solo lectura. El sniff global no se deshabilitó.

### ADMIN-LIST-QA-2 — locale de precio en integración

La aserción de CLP se hizo independiente del separador del locale, manteniendo `number_format_i18n()` en el render productivo.

## Contrato del índice

El índice derivado continúa conteniendo solo propiedades publicadas. No se agregaron borradores ni datos privados al índice público para facilitar filtros administrativos. Una futura búsqueda indexada de borradores requerirá un mecanismo administrativo separado.

## Producción

`propiedadesmartinez.cl` no fue modificado.

## Cierre

PR #26 fue squash-mergeada como `15991b70d471fd2ba2ecbf88a762b2fdd9996b09`. PR 2.2 queda formalmente `DONE` y habilita PR 2.3 — Editor guiado de Propiedad.
