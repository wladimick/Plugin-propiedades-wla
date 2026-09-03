# Evidencia — Fase 1 / PR 1.2 Entidad Property

Estado documental: `DONE`.

Issue: #7 — cerrada  
PR: #8 — squash merge  
Merge commit: `da989ef50a9d066023ae2c00d776d05af3d3499c`

## Objetivo

Registrar `wla_property` como entidad inmobiliaria nativa de WordPress y eliminar la dependencia conceptual de productos WooCommerce como modelo de propiedad.

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

Quedaron definidos permisos propios como `edit_wla_property`, `edit_wla_properties`, `edit_others_wla_properties`, `publish_wla_properties` y `read_private_wla_properties`, además de los permisos de edición/eliminación privada/publicada.

La asignación a roles se mantiene correctamente fuera de alcance hasta PR 1.6.

## QA final

Workflow: `Bootstrap Smoke`  
Run: `33818077411`  
Resultado: `SUCCESS`

Validaciones relevantes:

- PHP syntax: PASS;
- requirements smoke: PASS;
- `post-type.php`: PASS;
- build ZIP: PASS;
- release ZIP smoke: PASS;
- Composer autoload de `PostType` y `Capabilities`: PASS.

## Artefacto

- Nombre: `wla-inmo-0.1.0-alpha.1`
- Artifact ID: `9917250717`
- Tamaño: `17095` bytes
- Digest: `sha256:984963e62be5e98919f27015b98ea9ee2b0a403819901ea0c8ed550ca081b77e`

## Seguridad / producción

No se asignaron permisos a roles, no se crearon metadatos ni migraciones y eliminar un usuario no elimina sus propiedades. Producción no fue afectada.

## Cierre

PR 1.2 completada y auditada. El siguiente alcance es PR 1.3 — taxonomías base.
