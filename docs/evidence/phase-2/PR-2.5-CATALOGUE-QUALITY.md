# Evidencia — Fase 2 / PR 2.5 Calidad del catálogo

Estado documental: `IN_PROGRESS / QA PENDING`.

Issue: #31  
Rama: `feat/phase2-catalogue-quality`

## Objetivo

Convertir la completitud de cada `wla_property` en una guía administrativa accionable y explicable, manteniendo separados los datos canónicos, el índice público de búsqueda y la proyección administrativa de calidad.

## Motor de calidad

`Quality\\Evaluator` calcula un resultado determinista de 0–100 a partir de 11 checks explícitos:

1. código de propiedad;
2. operación;
3. tipo de propiedad;
4. precio válido o “precio a consultar”;
5. ubicación suficiente;
6. superficie;
7. descripción mínima útil;
8. imagen principal;
9. cantidad mínima recomendada de imágenes;
10. texto ALT en las imágenes asociadas;
11. fecha de última verificación comercial.

Cada check posee código estable, etiqueta y acción sugerida. El resultado incluye `passed_checks`, `total_checks`, `missing_codes`, `has_price`, `has_image` e `is_complete`.

El score es una guía interna de completitud. No se presenta como factor de ranking de Google y no existe un `seo_score` artificial en esta fase.

## Proyección administrativa separada

Se incorpora `wp_wla_property_quality`, independiente de `wp_wla_property_index`.

La tabla derivada contiene exclusivamente:

- property ID;
- score;
- checks aprobados/total;
- completa/incompleta;
- tiene precio;
- tiene imagen principal;
- códigos estables de checks pendientes;
- fecha de actualización.

No almacena dirección privada, notas internas, external ID ni contenido sensible.

La proyección de calidad puede incluir borradores, pendientes, privados y programados para apoyar el trabajo administrativo. El índice público mantiene su contrato previo: solo propiedades publicadas.

## Sincronización

`Quality\\Indexer` marca propiedades como dirty cuando cambian:

- post/contenido/estado;
- meta canónico WLA;
- imagen destacada;
- taxonomías inmobiliarias;
- ALT de attachments asociados.

La sincronización se consolida en `shutdown`, evitando recalcular repetidamente durante un mismo guardado. También existe `syncNow()` para operaciones controladas e integración.

Los cambios de ALT o eliminación de un attachment localizan únicamente las propiedades que lo referencian como imagen principal o dentro de la galería.

## Rebuild

`Quality\\Rebuilder` permite reconstruir la proyección derivada por lotes a partir de WordPress. No modifica datos canónicos.

La acción administrativa se protege con capability y nonce.

## Listado de propiedades

`Admin\\PropertyQualityList` agrega:

- columna `Calidad`;
- estado `Pendiente` cuando aún no existe proyección;
- porcentaje y cantidad de checks pendientes;
- filtros: Incompletas, Completas, Sin precio y Sin imagen principal.

La columna no realiza una query de calidad por fila. Los resultados de la página actual se cargan en un único `findMany()`.

Los filtros utilizan la tabla administrativa de calidad y no el índice público.

## Pantalla Calidad del catálogo

`Admin\\QualityPage` reemplaza el placeholder del shell y muestra:

- evaluadas;
- completas;
- incompletas;
- sin precio;
- sin imagen principal;
- prioridades de corrección ordenadas por menor score;
- checks faltantes traducidos a acciones;
- acceso directo a corregir cada ficha;
- reconstrucción de calidad para usuarios autorizados.

La tabla de prioridades vuelve a verificar `edit_post` antes de mostrar cada propiedad.

## Seguridad

- proyección sin campos privados;
- checks derivados, nunca fuente de verdad canónica;
- filtro GET normalizado + whitelist;
- joins/columnas SQL internas y fijas;
- rebuild protegido por `manage_wla_inmo_settings` + nonce;
- edición de una propiedad continúa protegida por su sistema previo de capabilities;
- producción no se modifica.

## Tests incorporados

`tests/smoke/catalogue-quality.php` cubre:

- resultado 100% reproducible;
- resultado 0% para snapshot vacío;
- 11 checks documentados;
- precio a consultar;
- ubicación y superficie alternativas;
- ALT incompleto;
- missing codes estables;
- ausencia de SEO score artificial;
- ausencia de campos privados en el schema de calidad.

`tests/integration/assert-quality.php` valida en WordPress real:

- tabla/schema e índices de calidad;
- propiedad draft dentro de calidad;
- la misma propiedad draft fuera del índice público;
- resultado 100% con datos reales, términos, featured image, galería y ALT;
- dirección privada ausente de la proyección;
- cambio de ALT reduce calidad y genera `image_alt`;
- corrección restaura el resultado;
- rebuild reproduce el score;
- módulos administrativos disponibles.

Se añade workflow `Catalogue Quality Integration` en WordPress 6.6.2/PHP 8.1 y WordPress latest/PHP 8.3.

El release smoke exige las clases Quality y los módulos administrativos dentro del ZIP instalable, y comprueba que el schema derivado no incorpore campos privados.

## Producción

`propiedadesmartinez.cl` no ha sido modificado.

## Cierre pendiente

Antes de marcar PR 2.5 como `DONE` deben registrarse número de PR, CI final, integración Quality final, artifact/checksum, findings/correcciones y squash merge.
