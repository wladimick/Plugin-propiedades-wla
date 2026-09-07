# Evidencia — PR 3.9 Media remota segura

Estado: `QA_PASSED / READY_TO_MERGE`.

Issue: #63  
PR: #64  
Rama: `feat/phase3-remote-media`  
Base: squash PR #62 `a51cb361f4534935f13c94c72b5d961a88f7a743`  
Head funcional validado: `e144e2362cd0fea832c6edef317fec747facbb1f`

## Objetivo

Implementar D37 sin descargar media durante dry-run y sin convertir WLA Inmo en un proxy SSRF. Las URLs remotas son input portable/transitorio; el estado canónico termina en Media Library, `gallery_ids` y featured image de WordPress.

## Gobierno

Decisiones aplicables ya aceptadas:

- D18: galería = Media Library + attachment IDs;
- D19: videos mediante URL/política, no descarga implícita;
- D34: dry-run obligatorio;
- D35: batches reanudables;
- D37: media remota con controles SSRF, MIME, tamaño y timeout;
- D55: capabilities granulares;
- D66/D67: tests/gates y PR temática con evidencia.

No se reemplaza ninguna decisión D01–D75.

El cambio de alcance aprobado el 2026-09-07 deja PR 3.10 Exportación CSV/XLSX como `OMITTED / OUT_OF_SCOPE`; no altera 3.9. Registro: `docs/decisions/PHASE-3-SCOPE-2026-09-07.md`.

## Arquitectura implementada

```text
CSV / JSON WLA / XLSX
        ↓
media.gallery_urls / media.featured_image_url
        ↓
normalización sintáctica durante mapping/dry-run
        ↓
DryRunEngine — CERO HTTP
        ↓
RowExecutor
  ├── separa media.* del PropertyWriter
  ├── re-resuelve identidad
  ├── create/update propiedad
  └── procesa media SOLO después del upsert
        ↓
RemoteMediaRowProcessor
  ├── retry acotado de fallas transitorias
  ├── warning para fallas permanentes
  └── cache de URL dentro de la fila
        ↓
RemoteMediaUrlPolicy
  ├── scheme / host / port
  ├── DNS A + AAAA
  └── NetworkAddressPolicy
        ↓
wp_safe_remote_get()
  ├── reject_unsafe_urls
  ├── redirect validation
  ├── sslverify
  ├── timeout
  ├── stream
  └── limit_response_size
        ↓
temporal server-generated 0600
        ↓
finfo + getimagesize + dimensiones + SHA-256
        ↓
RemoteMediaLibrary
  ├── dedup WLA por SHA-256
  └── media_handle_sideload()
        ↓
gallery_ids + featured image
```

## Threat model / SSRF

La URL remota se considera totalmente no confiable.

Cubierto:

- localhost, `.local` y hostnames single-label;
- IPv4 loopback/private/link-local/reserved/multicast;
- CGNAT;
- rangos de benchmark/documentación;
- IPv6 loopback/ULA/link-local/multicast/documentación;
- IPv4-mapped IPv6;
- hostname con DNS mixto público + privado;
- IP literal no pública;
- credenciales embebidas;
- fragments;
- esquemas distintos de HTTP/HTTPS;
- puertos distintos de 80/443;
- resolución DNS fallida;
- revalidación de redirect mediante safe HTTP API de WordPress;
- lista de URLs acotada.

## Límites efectivos

- máximo URLs de galería: 20;
- URL máxima: 2048 bytes;
- máximo por imagen: 10 MiB;
- timeout: 15 s;
- redirects: 3;
- tipos admitidos: JPEG, PNG, WebP;
- SVG remoto: rechazado;
- ancho/alto máximo inicial: 12.000 px;
- máximo inicial: 40.000.000 píxeles;
- temporales: nombre server-generated + permisos `0600` fail-closed.

`Content-Length` se usa solo como rechazo temprano; el límite real también se aplica al stream/archivo descargado. No se ejecuta un benchmark contra Internet en CI: los tests son deterministas y validan los budgets de bytes/timeout/redirects sin depender de terceros.

## Persistencia / privacidad

- `gallery_ids` continúa siendo referencia interna y no puede entrar desde archivos externos;
- deduplicación global solo entre attachments identificados por WLA mediante `_wla_inmo_remote_media_sha256`;
- source URL original **no se persiste en claro**;
- se almacena únicamente SHA-256 técnico de la URL de origen;
- el nombre final del attachment se genera desde el hash validado, no desde el basename remoto;
- JSON WLA export usa las URLs públicas actuales de los attachments canónicos;
- JSON WLA no expone el hash interno de la URL de origen.

## Política de errores / checkpoint

Fallos permanentes de una imagen se convierten en warning acotado y permiten continuar la fila, por ejemplo MIME no permitido, dimensiones no permitidas o URL permanentemente inválida.

Fallos transitorios se reintentan de forma acotada. Incluyen DNS/transport y HTTP 408/425/429/5xx. Si persisten:

1. la propiedad ya puede haber sido creada/actualizada;
2. `RowExecutor` devuelve error con el `property_id`;
3. `BatchRunner` no confirma el checkpoint de esa fila;
4. al reintentar, identidad se resuelve nuevamente;
5. una fila originalmente NEW pasa a UPDATE sobre la propiedad existente en lugar de crear un duplicado.

## Targets portables

- `media.gallery_urls` — lista, máximo 20;
- `media.featured_image_url` — URL única.

Disponibles mediante `TargetRegistry` para CSV/XLSX mapping y mediante sección `media` en JSON WLA v1.

Ejemplo JSON:

```json
{
  "media": {
    "gallery_urls": ["https://cdn.example.com/a.jpg"],
    "featured_image_url": "https://cdn.example.com/a.jpg"
  }
}
```

No se permiten attachment IDs externos ni meta arbitraria.

## Tests implementados

### Unitarios

- políticas IPv4/IPv6/DNS mixto/host/port/scheme/credentials/fragments;
- lista de URLs y límite;
- transporte bounded;
- Content-Length y stream overrun;
- HTTP permanente/transitorio;
- MIME real y firma de imagen;
- SVG/no-raster rechazado;
- dimensiones/píxeles;
- temporales privados y cleanup;
- Media Library create/reuse/cleanup;
- deduplicación por SHA-256;
- warnings permanentes;
- retry transitorio y agotamiento;
- clear explícito sin descarga;
- `RowExecutor`: media se elimina del writer y corre después del upsert;
- `RowExecutor`: warning conserva éxito;
- `RowExecutor`: falla transitoria posterior a create + retry no duplica propiedad;
- JSON WLA: sección media portable y unknown media target rechazado.

### WordPress real

`tests/integration/assert-remote-media.php` usa un PNG local controlado, sin Internet, para validar:

- attachment real mediante Media Library;
- dedup de segunda ingestión con mismo SHA;
- `gallery_ids` canónicos;
- featured image;
- raw source URL ausente de metadata;
- JSON export con URLs públicas canónicas de gallery/featured.

Se ejecuta dentro de `Import Row Executor Integration` para WordPress 6.6.2/PHP 8.1 y WordPress latest/PHP 8.3.

## QA final — head `e144e2362cd0fea832c6edef317fec747facbb1f`

13/13 workflows del head funcional: `SUCCESS`.

- Remote Media Integration — `34161989830` — SUCCESS;
- Import Row Executor Integration — `34161989809` — SUCCESS;
- JSON WLA Integration — `34161989796` — SUCCESS;
- Phase 1 CI — `34161989828` — SUCCESS;
- Bootstrap Smoke — `34161989803` — SUCCESS;
- Administration Quality Gate — `34161989806` — SUCCESS;
- Import Persistence Integration — `34161989876` — SUCCESS;
- Import Batch Runner Integration — `34161989917` — SUCCESS;
- Catalogue Quality Integration — `34161989795` — SUCCESS;
- Activity Integration — `34161989844` — SUCCESS;
- Dashboard Integration — `34161989945` — SUCCESS;
- Settings UI Integration — `34161989833` — SUCCESS;
- Help Center Integration — `34161989846` — SUCCESS.

### Matrices clave

- Remote Media: PHP 8.1 — SUCCESS;
- Remote Media: PHP 8.3 — SUCCESS;
- Row Executor + Media Library: WordPress 6.6.2 / PHP 8.1 — SUCCESS;
- Row Executor + Media Library: WordPress latest / PHP 8.3 — SUCCESS;
- JSON WLA: WordPress 6.6.2 / PHP 8.1 — SUCCESS;
- JSON WLA: WordPress latest / PHP 8.3 — SUCCESS.

### Artifacts relevantes

- Administration E2E: artifact `10032928089`, digest `sha256:fc86284de64460b90f37d0905a298546da4f5592559888b61ad54a18f9282f16`;
- JSON performance 6.6.2/PHP 8.1: artifact `10032909248`, digest `sha256:f2e2b78b8575bc55eeef8d744f537b0336ecdc9efa619b2f7aa7ae88a29d01a4`;
- JSON performance latest/PHP 8.3: artifact `10032903982`, digest `sha256:0547dc5e8058f9bffa4e261dfeb6eb511cb543dbc1e2269417627ba71848ba78`.

Remote Media no genera artifact binario propio: su evidencia reproducible está en PHPUnit/PHPStan/source-smoke y la integración WordPress local controlada.

## Review findings

- Review threads abiertos: 0.
- Reviews con findings: 0.
- P0/P1 abiertos: 0.
- Finding de QA corregido: el primer script de integración llamaba helpers inexistentes `setGallery()` / `setFeaturedImage()`; se corrigió para usar el contrato público real `setGalleryIds()` / `setFeaturedImageId()` y ambas matrices quedaron verdes.

## Criterio de merge

Cumplido:

- CI final completamente verde sobre el head funcional;
- WordPress mínimo/latest validado;
- regresión JSON/CSV/XLSX pipeline cubierta por gates existentes;
- seguridad SSRF/MIME/bytes/timeout/redirects cubierta;
- dry-run sin HTTP por arquitectura y tests;
- retry/idempotencia validado;
- privacidad de URL origen validada;
- review findings bloqueantes = 0;
- producción sin cambios.

PR #64 puede salir de draft y mergearse por squash.

## Producción

`propiedadesmartinez.cl` permanece sin cambios.
