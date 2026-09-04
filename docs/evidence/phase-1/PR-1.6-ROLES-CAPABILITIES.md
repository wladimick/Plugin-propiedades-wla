# Evidencia — Fase 1 / PR 1.6 Roles y capabilities

Estado documental: `QA_PASSED / MERGE_PENDING`.

Issue: #15  
PR: #16  
Rama: `feat/phase1-roles-capabilities`

## Objetivo

Aplicar mínimo privilegio a WLA Inmo con roles y capabilities específicas, evitando convertir cada pantalla en una comprobación genérica de `manage_options`.

## Componentes

- `Access\\Capabilities` — permisos de módulos futuros.
- `Access\\RoleMatrix` — matriz declarativa de roles/capabilities.
- `Access\\RoleManager` — instalación/reconciliación versionada.
- `Properties\\Capabilities::meta()` / `primitive()` — diferencia explícita entre meta caps y caps asignables.

## Roles

- WordPress `administrator`: conserva sus permisos nativos y recibe todas las capabilities WLA.
- `wla_inmo_manager`: operación inmobiliaria completa; `manage_wla_inmo_tools` queda reservado por defecto.
- `wla_property_editor`: administra sus propiedades, publicaciones, media y asignación de términos; no edita propiedades de otros ni módulos sensibles.
- `wla_lead_manager`: gestiona leads futuros y no recibe capabilities de propiedades/taxonomías.

## Seguridad

- ningún rol propio recibe `manage_options`;
- editor no recibe import/export/leads/SEO/settings/tools;
- gestor de leads no recibe `upload_files` ni permisos de propiedades;
- Administrator se modifica de forma aditiva, sin retirar capabilities nativas;
- roles propios se reconcilian para eliminar capabilities WLA obsoletas cuando cambie el schema;
- desactivar el plugin no elimina roles ni permisos.

## Performance

La matriz se instala en activación. En `admin_init` solo se compara `wla_inmo_roles_version`; la reconciliación completa ocurre únicamente si cambia la versión.

## QA automático

Workflow run: `33824793619`  
Job: `PHP 8.1 / Build Smoke`  
Resultado: `SUCCESS`

Pasaron Composer validation, PHP syntax, todos los source smoke tests, build del ZIP, release smoke, autoload y publicación de artifact.

`tests/smoke/access-roles.php` verifica meta vs primitive capabilities, creación de roles, Administrator, matrices positiva/negativa, ausencia de `manage_options`, reconciliación de permisos WLA obsoletos e idempotencia.

## Artefacto

- Artifact ID: `9919538308`
- Nombre: `wla-inmo-0.1.0-alpha.1`
- Tamaño: `32723` bytes
- Digest: `sha256:0c8f93479af7a5108ad089af51f921298aa0b4bc0f7ecde1ea410ebddb19c572`
- Expira: 2026-12-03

## Documentación

`docs/ACCESS-CONTROL.md` contiene la matriz auditable y el futuro mapeo de secciones administrativas a capabilities.

## Producción

No afectada. Los roles existen únicamente en el código de desarrollo hasta que exista un despliegue explícito posterior.

## Cierre

QA requerido para merge aprobado. Después del squash merge, PR #16 será la evidencia canónica y el siguiente alcance será PR 1.7 — settings y contratos públicos mínimos.
