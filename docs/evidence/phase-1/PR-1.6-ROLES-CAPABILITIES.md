# Evidencia — Fase 1 / PR 1.6 Roles y capabilities

Estado documental: `DONE`.

Issue: #15  
PR: #16  
Rama: `feat/phase1-roles-capabilities`  
Squash merge: `b09292c3d30972e2a1c097306312c989b84e3f11`

## Objetivo

Aplicar mínimo privilegio a WLA Inmo con roles y capabilities específicas, evitando convertir cada pantalla en una comprobación genérica de `manage_options`.

## Componentes

- `Access\\Capabilities` — permisos de módulos futuros.
- `Access\\RoleMatrix` — matriz declarativa de roles/capabilities.
- `Access\\RoleManager` — instalación/reconciliación versionada.
- `Properties\\Capabilities::meta()` / `primitive()` — diferencia explícita entre meta caps y caps asignables.

## Roles y seguridad

- WordPress `administrator`: conserva sus permisos nativos y recibe todas las capabilities WLA.
- `wla_inmo_manager`: operación inmobiliaria completa; herramientas técnicas reservadas por defecto.
- `wla_property_editor`: sus propiedades, publicación, media y asignación de términos; no módulos sensibles.
- `wla_lead_manager`: leads sin capabilities de propiedades/taxonomías.
- ningún rol propio recibe `manage_options`.

## QA automático

Workflow run: `33824793619`  
Resultado: `SUCCESS`

## Artefacto

- Artifact ID: `9919538308`
- Nombre: `wla-inmo-0.1.0-alpha.1`
- Tamaño: `32723` bytes
- Digest: `sha256:0c8f93479af7a5108ad089af51f921298aa0b4bc0f7ecde1ea410ebddb19c572`

## Documentación

`docs/ACCESS-CONTROL.md` contiene la matriz auditable.

## Producción

No afectada.

## Cierre

PR #16 integrada mediante squash merge con CI verde. El siguiente alcance es PR 1.7 — settings y contratos públicos mínimos.
