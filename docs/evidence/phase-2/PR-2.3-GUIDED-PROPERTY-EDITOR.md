# Evidencia — Fase 2 / PR 2.3 Editor guiado de Propiedad

Estado documental: `QA_PASSED / MERGE_PENDING`.

Issue: #27  
PR: #28  
Rama: `feat/phase2-guided-property-editor`

## Objetivo

Reemplazar la experiencia de edición técnica y dispersa por una ficha inmobiliaria guiada, reutilizando el modelo canónico de Fase 1 y manteniendo WordPress como base editorial.

## Experiencia de edición

`Admin\\PropertyEditor` incorpora una ficha con 12 secciones: Estado de publicación, Identificación, Precio, Superficies, Características, Ubicación, Descripción, Multimedia, Contacto y privacidad, SEO/GEO/AEO, Calidad e Historial.

El editor de bloques se desactiva únicamente para `wla_property` para ofrecer un formulario administrativo determinista y ligero usando el editor clásico nativo de WordPress. No instala Classic Editor ni modifica otros post types. Título, descripción, estado de publicación, imagen destacada y revisiones siguen siendo features nativas.

## Fuente de verdad

La ficha no crea un segundo schema de dominio:

- `MetaSchema` define los campos y callbacks canónicos;
- `Sanitizer` normaliza;
- `Validator` define validez de almacenamiento;
- `TaxonomyRegistry` define operación, tipo, región, comuna y sector;
- `PropertyEditor::controls()` solo describe presentación administrativa.

Los tests comprueban que cada campo editable existe en `MetaSchema` y aparece exactamente en una sección.

## Privacidad y taxonomías

`external_id`, `private_address` e `internal_notes` están identificados visualmente como privados. La dirección pública y la dirección exacta privada son conceptos separados y la ficha no cambia el contrato del Core: los campos privados no se exponen por REST público ni deben salir automáticamente al frontend.

Los metaboxes dispersos de taxonomías se retiran del editor y operación/tipo/región/comuna/sector se integran en la ficha. Un usuario sin `assign_terms` puede ver el valor actual, pero no recibe control editable. El handler reautoriza y valida el término antes de persistir.

## Seguridad de escritura

El handler propio ignora autosaves/revisiones y exige:

1. `wla_property`;
2. nonce WLA válido;
3. `current_user_can('edit_post', $post_id)`;
4. campos allowlisted;
5. validación de dominio previa;
6. capability de taxonomía y término válido.

No se confía en hidden inputs como autorización.

## Guardado lógico consistente

Los campos WLA se validan como conjunto antes de mutar datos. Si hay error, no se actualizan meta ni taxonomías WLA del envío; los valores seguros se conservan temporalmente para volver a mostrarlos y se presenta un resumen accesible con errores por campo.

Si una escritura de taxonomía falla después de validar, se restaura el snapshot previo de meta y términos. La UI aclara que título/descripción y otros campos nativos son gestionados por WordPress y pueden haberse guardado independientemente.

## Código único

Antes de persistir `property_code`, el editor busca otra `wla_property` con el mismo código incluyendo borradores. Un conflicto impide escrituras parciales del conjunto WLA sin incorporar borradores al índice público.

## Performance y alcance

- cero framework JS nuevo;
- PHP/HTML nativo;
- CSS administrativo condicional existente;
- no requests externos críticos durante render;
- no galería completa en esta PR;
- galería/videos llegan en PR 2.4;
- calidad calculada llega en PR 2.5;
- SEO/GEO/AEO completo llega en Fase 6;
- bitácora inmobiliaria llega en PR 2.8.

## Tests

`tests/smoke/property-editor.php` valida las 12 secciones, contrato con `MetaSchema`, privacidad, aislamiento del modo editor, sanitización, rechazo de valores inválidos, prevención de código duplicado y descarte de campos desconocidos.

La integración WordPress real valida guardado válido de código/precio/dirección privada, asignación de operación, rechazo de código duplicado sin escritura parcial, nonce inválido y usuario sin `edit_post`.

El release smoke exige `src/Admin/PropertyEditor.php` en el ZIP.

## QA automático final

Head de código validado: `48d6833932be5274c964f6696d27d2029aa1a937`.

### Phase 1 CI heredado

Run final: `33830157300`  
Resultado: `SUCCESS`.

- Quality Gate / PHP 8.1: SUCCESS;
- PHP syntax: SUCCESS;
- WPCS security profile: SUCCESS;
- PHPStan 2.2: SUCCESS;
- PHPUnit: `3 tests / 40 assertions`;
- guided editor smoke: SUCCESS;
- property list y todos los smoke heredados: SUCCESS;
- build ZIP: SUCCESS;
- release ZIP smoke: SUCCESS;
- WordPress `6.6.2` + PHP `8.1`: SUCCESS;
- WordPress `latest` + PHP `8.3`: SUCCESS;
- desactivación/uninstall conservan datos: SUCCESS.

### Bootstrap Smoke

Run final: `33830157352`  
Resultado: `SUCCESS`.

## Artifact QA

- Artifact ID: `9921377798`;
- nombre: `wla-inmo-0.1.0-alpha-quality`;
- tamaño del contenedor: `73328` bytes;
- digest: `sha256:fbfff4511dca2eed1aa3230369d33cd8f34f59d835dcd21787bc89973a97410a`;
- ZIP instalable SHA-256: `3d3f6e68e27768cf10904fe468f90c4106382824ec0fd61d5c7915e5caf7660a`;
- expiración: `2026-12-03`.

## Findings corregidos

### ADMIN-EDITOR-QA-1 — escaping del HTML dinámico

El primer CI de PR #28 (`33829929983`) encontró tres errores WPCS porque atributos generados por helpers se concatenaban directamente durante el render de checkbox/select/textarea/input.

Corrección: el HTML se emite por partes, todos los valores dinámicos pasan por funciones de escaping y los atributos booleanos/ARIA se agregan únicamente mediante ramas internas controladas. `checked()` se usa en modo de salida seguro.

Resultado: WPCS verde en `33830157300`.

### ADMIN-EDITOR-QA-2 — análisis estático del nonce

El mismo run marcó la lectura intermedia de `$_POST` aunque el nonce se sanitizaba y verificaba inmediatamente después.

Corrección: se mantuvo sanitización real + `wp_verify_nonce()` y se documentó una excepción PHPCS exclusivamente en esa lectura concreta; no se deshabilitó ningún sniff global.

### ADMIN-EDITOR-UX-1 — coordenadas negativas

Durante la revisión del fix se detectó que aplicar `min="0"` genéricamente a todos los inputs numéricos habría impedido latitudes/longitudes negativas válidas.

Corrección: la restricción HTML de mínimo cero solo se aplica a campos numéricos no geográficos; latitud/longitud quedan gobernadas por los límites canónicos de `Validator`.

## Producción

`propiedadesmartinez.cl` no ha sido modificado.

## Cierre

Todos los quality gates aplicables están verdes. PR #28 queda `QA_PASSED / MERGE_PENDING` y solo pasará a `DONE` después del squash merge.
