# Evidencia — Fase 1 / PR 1.2 Entidad Property

Estado documental: `IN_PROGRESS`.

Issue: #7  
Rama: `feat/phase1-property-entity`

## Objetivo

Registrar `wla_property` como entidad inmobiliaria nativa de WordPress y eliminar cualquier necesidad conceptual de usar productos WooCommerce como modelo de propiedad.

## Implementación incluida

- `Properties\\PostType`;
- `Properties\\Capabilities`;
- clave canónica `wla_property`;
- archive/rewrite inicial `propiedades`;
- REST base `wla-properties`;
- soporte `title`, `editor`, `excerpt`, `thumbnail`, `revisions`;
- `public`, `publicly_queryable`, `show_in_rest`;
- `delete_with_user = false`;
- capabilities inmobiliarias explícitas;
- registro en `init` prioridad 5;
- registro + flush de rewrite rules en activación;
- unregister + flush en desactivación;
- ningún flush por request normal.

## Contrato de capabilities

La entidad deja definidos nombres propios como:

- `edit_wla_property`;
- `edit_wla_properties`;
- `edit_others_wla_properties`;
- `publish_wla_properties`;
- `read_private_wla_properties`;
- capabilities de delete/edit private/published.

La asignación efectiva a Administrador inmobiliario / Editor de propiedades pertenece a PR 1.6 y no se adelanta aquí.

## Tests definidos

`tests/smoke/post-type.php` valida:

- clave del CPT;
- archive/rewrite;
- exposición REST;
- soportes editoriales;
- `delete_with_user`;
- `map_meta_cap`;
- mapa explícito de capabilities;
- ausencia de `edit_posts` genérico como capability concedida;
- llamada real a `register_post_type()` mediante stub de smoke.

El workflow se actualiza para ejecutar todos los archivos `tests/smoke/*.php` y el smoke del ZIP exige que `PostType.php` y `Capabilities.php` estén presentes y sean autoloadables.

## Seguridad / datos

- No hay metadatos aún.
- No hay endpoints de escritura propios.
- No se asignan permisos a roles todavía.
- Eliminar un usuario no borra automáticamente las propiedades.
- Desactivar/desinstalar conserva los datos.

## SEO / URLs

Se establece como contrato inicial:

- archivo: `/propiedades/`;
- singles bajo la base `/propiedades/...`;
- `with_front = false`;
- feeds del CPT deshabilitados inicialmente.

La base podrá hacerse configurable en PR 1.7 sin cambiar la identidad interna `wla_property`.

## Producción

No afectada. No existe despliegue ni migración sobre Propiedades Martínez en esta etapa.

## Cierre

Completar con PR, workflow run, artifact y digest después del QA/merge.
