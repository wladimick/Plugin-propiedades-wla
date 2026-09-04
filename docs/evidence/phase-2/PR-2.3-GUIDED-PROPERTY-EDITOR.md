# Evidencia — Fase 2 / PR 2.3 Editor guiado de Propiedad

Estado documental: `IN_PROGRESS / QA PENDING`.

Issue: #27  
Rama: `feat/phase2-guided-property-editor`

## Objetivo

Reemplazar la experiencia de edición técnica y dispersa por una ficha inmobiliaria guiada, reutilizando el modelo canónico de Fase 1 y manteniendo WordPress como base editorial.

## Experiencia de edición

`Admin\\PropertyEditor` incorpora una ficha con 12 secciones:

1. Estado de publicación;
2. Identificación;
3. Precio;
4. Superficies;
5. Características;
6. Ubicación;
7. Descripción;
8. Multimedia;
9. Contacto y privacidad;
10. SEO / GEO / AEO;
11. Calidad;
12. Historial.

El editor de bloques se desactiva únicamente para `wla_property` para ofrecer un formulario administrativo determinista y ligero usando el editor clásico nativo de WordPress. Esto no instala Classic Editor ni modifica otros post types.

Título, descripción, estado de publicación, imagen destacada y revisiones siguen siendo features nativas de WordPress.

## Fuente de verdad

La ficha no crea un segundo schema de dominio:

- `MetaSchema` define qué campos existen y sus callbacks canónicos;
- `Sanitizer` realiza la normalización;
- `Validator` define validez de almacenamiento;
- `TaxonomyRegistry` define operación, tipo, región, comuna y sector;
- `PropertyEditor::controls()` contiene solamente presentación administrativa: etiqueta, tipo de control, ayuda y opciones de UX.

Los tests verifican que cada campo editable pertenece a `MetaSchema` y que cada control aparece exactamente en una sección.

## Campos privados

Se identifican visualmente como privados:

- `external_id`;
- `private_address`;
- `internal_notes`.

La dirección pública y la dirección exacta privada aparecen como conceptos separados. La ficha no cambia la regla del Core: los campos privados no se exponen por REST público ni deben utilizarse automáticamente en frontend.

## Taxonomías

Los metaboxes dispersos de taxonomías se retiran del editor de propiedades y operación/tipo/región/comuna/sector se administran dentro de la ficha.

- solo se muestran controles editables cuando el usuario posee `assign_terms`;
- un usuario sin capability ve el valor actual, pero no recibe un control que simule permiso;
- el handler vuelve a verificar capability antes de persistir;
- IDs de términos se validan contra la taxonomía correspondiente.

## Seguridad de escritura

El handler propio ignora autosaves y revisiones y exige:

1. post type `wla_property`;
2. nonce WLA válido;
3. `current_user_can('edit_post', $post_id)`;
4. campos allowlisted por el editor;
5. validación de dominio antes de escribir;
6. capability de taxonomía antes de asignar términos.

No se confía en hidden inputs para autorización.

## Guardado atómico a nivel lógico

Los campos WLA se validan como conjunto antes de mutar datos.

Si hay error de validación:

- no se actualiza ningún meta WLA del envío;
- no se actualiza ninguna taxonomía WLA del envío;
- se conserva una copia segura de los valores ingresados durante unos minutos para volver a mostrarlos;
- la ficha presenta resumen accesible y error por campo.

Si una escritura de taxonomía falla después de validar, el editor restaura el snapshot previo de meta y términos.

Los campos nativos de WordPress —título/descripción, por ejemplo— son gestionados por WordPress y pueden haberse guardado independientemente; la UI lo explica para no afirmar una transacción que WordPress no ofrece sobre todo el post.

## Prevención de códigos duplicados

Antes de persistir `property_code`, el editor busca otra `wla_property` con el mismo código en cualquier estado relevante mediante WordPress.

Esto cubre también borradores, sin debilitar el contrato del índice público que continúa almacenando únicamente propiedades publicadas.

Un conflicto devuelve un error asociado al campo y evita escrituras parciales del conjunto WLA.

## Multimedia, calidad, historial y SEO

PR 2.3 prepara las secciones sin duplicar trabajo futuro:

- imagen principal continúa nativa;
- galería/videos completos llegan en PR 2.4;
- calidad calculada llega en PR 2.5;
- SEO/GEO/AEO completo llega en Fase 6, pero `indexable` ya puede administrarse;
- historial inmobiliario llega en PR 2.8.

## Performance

- cero framework JS nuevo;
- editor PHP/HTML nativo;
- editor de bloques deshabilitado solo en `wla_property`;
- CSS reutiliza el asset admin condicional existente;
- no requests externos críticos durante render;
- términos se consultan por las APIs nativas de WordPress;
- no se cargan galerías en esta PR.

## Tests incorporados

`tests/smoke/property-editor.php` valida:

- 12 secciones;
- todos los campos de UI existen en `MetaSchema`;
- cada campo aparece exactamente una vez;
- campos privados marcados;
- aislamiento del modo de editor a `wla_property`;
- sanitización canónica;
- rechazo de números/coordenadas inválidas;
- prevención de código duplicado;
- omisión de campos desconocidos.

La integración WordPress real amplía `assert-active.php` para validar:

- módulo `Admin\\PropertyEditor` disponible;
- guardado válido de código, precio y dirección privada;
- asignación de taxonomía canónica;
- rechazo de código duplicado sin escritura parcial;
- nonce inválido no puede mutar datos;
- usuario sin `edit_post` no puede mutar la propiedad mediante POST directo.

El release smoke exige `src/Admin/PropertyEditor.php` dentro del ZIP.

## Producción

`propiedadesmartinez.cl` no ha sido modificado.

## Cierre pendiente

Antes de marcar PR 2.3 como `DONE` deben registrarse PR, CI final, artifact/checksum, findings/correcciones y squash merge.
