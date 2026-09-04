# Evidencia — Fase 2 / PR 2.4 Multimedia y galería

Estado documental: `QA_PASSED / MERGE_PENDING`.

Issue: #29  
PR: #30  
Rama: `feat/phase2-property-media`

## Objetivo

Completar la gestión visual de cada propiedad usando la Biblioteca de Medios nativa de WordPress y los campos canónicos `gallery_ids` / `video_urls`, sin reintroducir WooCommerce, ACF, Elementor o WPCode.

## Implementación

`Admin\\PropertyMedia` agrega un panel Multimedia en la edición de `wla_property`.

### Galería

- selección múltiple mediante `wp.media`;
- solo imágenes;
- orden persistente en `gallery_ids`;
- reordenamiento accesible mediante botones “Mover antes” y “Mover después”;
- quitar una imagen solo modifica la asociación de la propiedad;
- no se llama a `wp_delete_attachment()`;
- contador de imágenes con `aria-live`;
- miniaturas pequeñas y lazy-loaded en el admin;
- IDs duplicados se eliminan mediante el sanitizador canónico conservando el primer orden;
- un ID debe existir, ser `attachment` y ser una imagen real para poder guardarse.

La imagen principal continúa siendo la Imagen destacada nativa de WordPress.

### Texto ALT

El ALT se muestra por cada attachment de la galería.

- editable solamente cuando el usuario tiene `edit_post` sobre ese attachment;
- el servidor vuelve a verificar capability antes de actualizar `_wp_attachment_image_alt`;
- un ID extra enviado por POST que no pertenezca a la galería guardada se ignora;
- los valores se sanitizan con `sanitize_text_field()`.

### Videos

`video_urls` se administra como una URL por línea.

- se normaliza a array antes de validar;
- reutiliza `Validator` y `Sanitizer::httpUrlArray()`;
- solo HTTP/HTTPS;
- iframe, HTML, scripts o texto que no sea URL se rechazan;
- no se guarda contenido embebido arbitrario.

### Assets

`Assets` mantiene el CSS general de WLA Inmo y agrega media assets únicamente en `post.php` / `post-new.php` cuando el screen corresponde a `wla_property`.

- `wp_enqueue_media()` solo se ejecuta en ese contexto;
- JS propio es vanilla y utiliza `window.wp.media`;
- no introduce React ni jQuery como dependencia de la funcionalidad propia;
- CSS de multimedia está aislado en `property-media.css`.

## Seguridad

- nonce específico para Multimedia;
- autorización por objeto `current_user_can('edit_post', $post_id)`;
- autosaves y revisiones ignorados;
- allowlist de campos `gallery_ids` y `video_urls`;
- validación completa antes de persistir ambos campos;
- ALT protegido por capability de attachment;
- escaping tardío en HTML;
- no existe operación de borrado físico de attachments;
- producción no se modifica.

## Tests

`tests/smoke/property-media.php` cubre:

- normalización y orden de galería;
- deduplicación canónica;
- normalización de videos por línea;
- rechazo de ID no numérico;
- rechazo de attachment que no es imagen;
- rechazo de posts normales como gallery IDs;
- rechazo de iframe/HTML como video;
- scope de assets al editor de propiedades;
- ausencia de `wp_delete_attachment`;
- capability sobre ALT;
- uso de Media Library nativa;
- ausencia de jQuery en el JS propio.

La integración WordPress real amplía `assert-active.php` para comprobar:

- clase `Admin\\PropertyMedia` disponible dentro del ZIP activo;
- creación de attachments de imagen sintéticos;
- persistencia de orden de galería;
- persistencia de URLs de video;
- persistencia autorizada de ALT;
- quitar de galería no elimina el attachment;
- un attachment `text/plain` es rechazado sin escrituras parciales;
- iframe inválido no cambia los videos canónicos;
- nonce inválido no cambia la galería;
- usuario `subscriber` no puede mutar Multimedia.

El release smoke exige `PropertyMedia.php`, `property-media.css` y `property-media.js` dentro del ZIP y comprueba el autoload de la clase.

## QA automático final de código

Head validado: `ea3678e0ba8d88a8c79517d3a8a0085f492a514c`.

### Phase 1 CI heredado

Run final de código: `33832297066`  
Resultado global: `SUCCESS`.

- Quality Gate / PHP 8.1: SUCCESS;
- PHP syntax: SUCCESS;
- WPCS security profile: SUCCESS;
- PHPStan: SUCCESS;
- PHPUnit: `3 tests / 40 assertions`;
- property media smoke: SUCCESS;
- guided editor/property list y smoke heredados: SUCCESS;
- build ZIP: SUCCESS;
- release ZIP smoke: SUCCESS;
- WordPress 6.6.2 + PHP 8.1: SUCCESS;
- WordPress latest + PHP 8.3: SUCCESS;
- desactivación/uninstall conservan datos: SUCCESS.

### Bootstrap Smoke

Run final de código: `33832297062`  
Resultado: `SUCCESS`.

## Artifact final QA de código

- Artifact ID: `9922097254`;
- Nombre: `wla-inmo-0.1.0-alpha-quality`;
- Tamaño del contenedor: `80291` bytes;
- Digest del artifact: `sha256:bdfa0492fffed8aa289e26a12a387e1b73f367333d6e4e909c9ce75e1c17033f`;
- ZIP instalable SHA-256: `20acd3332d8d34ec735d14d86c6905562c12ac5c3cd51d95ea619c8a4ce64982`;
- Expira: `2026-12-03`.

## Historial de findings

### MEDIA-QA-1 — dependencia de clase en smoke administrativo

El primer run de PR #30 (`33832232777`) pasó sintaxis, WPCS, PHPStan y PHPUnit, pero falló en `Source smoke tests` porque `tests/smoke/admin-shell.php` cargaba `Admin\\Assets` sin cargar primero `Admin\\PropertyMedia`. En WordPress/Composer real la clase es autoloaded, pero el smoke unitario usa `require_once` explícitos.

Corrección: el smoke administrativo ahora carga `PropertyMedia.php` antes de `Assets.php`, preservando el aislamiento del test y verificando el nuevo contrato de assets.

Resultado: el run final de código `33832297066` quedó completamente verde, incluido el smoke nuevo y ambas matrices WordPress.

## Consideraciones de UX

PR 2.4 implementa la gestión real en un panel `Multimedia` dedicado dentro de la misma pantalla de edición de la propiedad. La sección 8 de la ficha guiada continúa funcionando como orientación conceptual y la gestión concreta vive en este panel para mantener separado el formulario canónico de negocio de la interfaz de Media Library.

## Producción

`propiedadesmartinez.cl` no ha sido modificado.

## Cierre

Los quality gates del código están verdes. Este commit documental mueve el head de la PR, por lo que GitHub debe volver a validar el head final antes del squash merge. PR #30 permanece `QA_PASSED / MERGE_PENDING` hasta completar esa última validación y el merge.
