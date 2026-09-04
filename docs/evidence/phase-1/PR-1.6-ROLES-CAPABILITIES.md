# Evidencia — Fase 1 / PR 1.6 Roles y capabilities

Estado documental: `IN_PROGRESS`.

Issue: #15  
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

## Tests definidos

`tests/smoke/access-roles.php` verifica:

- meta vs primitive capabilities;
- ausencia de `manage_options` y `edit_posts` genéricos en el contrato WLA;
- creación de tres roles;
- capabilities del Administrator;
- matriz del Administrador inmobiliario;
- límites del Editor de propiedades;
- aislamiento del Gestor de leads;
- upgrade/reconciliación de capabilities obsoletas;
- idempotencia de instalación.

El smoke de release exige/autoloadea las tres clases de `Access` y rechaza otorgamiento directo de `manage_options` desde ese módulo.

## Documentación

`docs/ACCESS-CONTROL.md` contiene la matriz auditable y el futuro mapeo de secciones administrativas a capabilities.

## Producción

No afectada. Los roles existen únicamente en el código de desarrollo hasta que exista un despliegue explícito posterior.

## Cierre

Completar con PR, workflow, artifact, digest y squash merge después del QA.
