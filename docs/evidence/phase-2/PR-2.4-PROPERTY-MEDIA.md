# Evidencia — Fase 2 / PR 2.4 Multimedia y galería

Estado documental: `IN_PROGRESS / QA PENDING`.

Issue: #29  
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

## Producción

`propiedadesmartinez.cl` no ha sido modificado.

## Cierre pendiente

Antes de marcar PR 2.4 como `DONE` deben registrarse número de PR, CI final, Bootstrap Smoke, artifact/digest, checksum ZIP, findings/correcciones y squash merge.
