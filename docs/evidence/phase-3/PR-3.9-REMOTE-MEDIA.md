# Evidencia — PR 3.9 Media remota segura

Estado: `IN_PROGRESS / SSRF_FOUNDATION`.

Issue: #63  
Rama: `feat/phase3-remote-media`  
Base: squash PR #62 `a51cb361f4534935f13c94c72b5d961a88f7a743`

## Objetivo

Implementar D37 sin descargar media durante dry-run y sin convertir WLA Inmo en un proxy SSRF. La URL remota es input transitorio; el resultado canónico será un attachment de WordPress asociado mediante attachment IDs.

## Gobierno

Decisiones aplicables ya aceptadas:

- D18: galería = Media Library + attachment IDs;
- D19: videos mediante URL/política, no descarga implícita;
- D34: dry-run obligatorio;
- D35: batches reanudables;
- D37: media remota con controles SSRF, MIME, tamaño y timeout;
- D55: capabilities granulares;
- D66/D67: tests/gates y PR temática con evidencia.

No se requiere reemplazar ninguna decisión D01–D75 para esta implementación.

## Threat model inicial

La URL remota se considera totalmente no confiable.

Amenazas cubiertas desde la primera capa:

- localhost/single-label/internal hostnames;
- IPv4 loopback/private/link-local/reserved/multicast;
- CGNAT;
- rangos de benchmark/documentación;
- IPv6 loopback/ULA/link-local/multicast/documentación;
- IPv4-mapped IPv6;
- hostname con respuestas DNS mixtas públicas + privadas;
- IP literal privada;
- credenciales embebidas;
- fragments;
- esquemas distintos de HTTP/HTTPS;
- puertos distintos de 80/443;
- resolución DNS fallida;
- lista de URLs no acotada.

## Arquitectura prevista

```text
input URL(s)
  ↓
normalización sintáctica (dry-run-safe)
  ↓
RemoteMediaUrlPolicy
  ├── scheme / host / port
  ├── DNS A + AAAA
  └── NetworkAddressPolicy
  ↓
WP safe HTTP transport (solo ejecución)
  ├── redirect validation
  ├── timeout
  ├── stream a temporal server-generated
  └── limit_response_size
  ↓
MIME real + bytes
  ↓
Media Library
  ↓
attachment IDs
  ↓
gallery_ids / featured image canónicos
```

WordPress documenta que `wp_safe_remote_get()` valida la URL y cada redirect mediante `wp_http_validate_url()` y que el HTTP API soporta streaming a archivo y `limit_response_size`. WLA agrega política propia antes del transporte como defensa en profundidad.

## Implementado — foundation

- `RemoteMediaException`;
- `DnsResolverInterface`;
- `SystemDnsResolver` con A/AAAA y fallback IPv4;
- `NetworkAddressPolicy` con rangos bloqueados explícitos + flags PHP de private/reserved;
- `RemoteMediaUrlPolicy`;
- máximo inicial de 20 URLs por operación lógica;
- URL máxima 2048 bytes;
- HTTP/HTTPS solamente;
- puertos 80/443 solamente;
- tests unitarios deterministas con resolver inyectable;
- workflow `Remote Media Integration` PHP 8.1/8.3 + PHPStan.

## Límites de transporte propuestos para la siguiente etapa

Estos valores son defaults iniciales, configurables internamente y sujetos a evidencia:

- máximo por imagen: 10 MiB;
- timeout: 15 s;
- redirects: 3;
- tipos iniciales: JPEG, PNG, WebP;
- SVG remoto: rechazado;
- máximo imágenes por propiedad: 20;
- Content-Length: hint temprano, nunca defensa única;
- límite real aplicado también durante stream.

## Pendientes

- transporte WordPress bounded;
- MIME real desde archivo;
- cleanup de temporales;
- integración con Media Library;
- deduplicación/idempotencia;
- target import portable para URLs de imágenes sin exponer `gallery_ids` externos;
- procesamiento después del upsert y cero HTTP en dry-run;
- warnings de media sin romper checkpoint de datos ya persistidos;
- tests de redirects/timeout/stream overrun/MIME;
- integración WordPress;
- performance/evidencia final;
- review + QA final.

## Producción

`propiedadesmartinez.cl` permanece sin cambios.
