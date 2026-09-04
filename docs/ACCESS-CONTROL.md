# Control de acceso — WLA Inmo

## Objetivo

WLA Inmo utiliza capabilities propias y principio de mínimo privilegio. Las pantallas futuras del administrador no deben autorizarse con `manage_options` de forma genérica.

## Roles

| Rol | Slug | Objetivo |
|---|---|---|
| Administrador WordPress | `administrator` | control total del sitio + todas las capabilities WLA |
| Administrador inmobiliario | `wla_inmo_manager` | operación inmobiliaria completa sin herramientas técnicas reservadas |
| Editor de propiedades | `wla_property_editor` | crear, editar, publicar y mantener sus propiedades y multimedia |
| Gestor de leads | `wla_lead_manager` | gestionar consultas/leads sin editar propiedades |

Desactivar WLA Inmo no elimina roles ni capabilities asignadas; un uninstall destructivo futuro requerirá una política explícita separada.

## Capabilities de propiedades

El CPT utiliza capabilities propias `wla_property`. Las capabilities singulares:

- `edit_wla_property`;
- `read_wla_property`;
- `delete_wla_property`;

son **meta capabilities** que WordPress mapea según objeto/contexto. No se asignan directamente a roles.

Las capabilities primitivas asignables incluyen:

- `edit_wla_properties`;
- `edit_others_wla_properties`;
- `publish_wla_properties`;
- `read_private_wla_properties`;
- `delete_wla_properties`;
- `delete_private_wla_properties`;
- `delete_published_wla_properties`;
- `delete_others_wla_properties`;
- `edit_private_wla_properties`;
- `edit_published_wla_properties`.

## Capabilities de taxonomías

- `manage_wla_property_terms`;
- `edit_wla_property_terms`;
- `delete_wla_property_terms`;
- `assign_wla_property_terms`.

Un Editor de propiedades puede asignar términos existentes, pero no administrar la estructura taxonómica.

## Capabilities de módulos

| Capability | Uso futuro principal |
|---|---|
| `view_wla_inmo_dashboard` | Resumen / calidad básica |
| `manage_wla_inmo_home` | Inicio y destacados |
| `import_wla_properties` | Importar propiedades |
| `export_wla_properties` | Exportar propiedades |
| `view_wla_inmo_leads` | Ver consultas/leads |
| `edit_wla_inmo_leads` | Actualizar leads |
| `manage_wla_inmo_leads` | Gestión avanzada de leads |
| `manage_wla_inmo_seo` | SEO y visibilidad |
| `view_wla_inmo_activity` | Actividad / auditoría |
| `manage_wla_inmo_settings` | Ajustes inmobiliarios |
| `manage_wla_inmo_tools` | Herramientas técnicas/rebuild/migraciones |

## Matriz resumida

| Área | Administrator | Inmo Manager | Property Editor | Lead Manager |
|---|:---:|:---:|:---:|:---:|
| Dashboard | ✅ | ✅ | ✅ | ✅ |
| Crear/editar propiedades | ✅ | ✅ | ✅ propias | ❌ |
| Editar propiedades de otros | ✅ | ✅ | ❌ | ❌ |
| Publicar propiedades | ✅ | ✅ | ✅ | ❌ |
| Eliminar propiedades de otros | ✅ | ✅ | ❌ | ❌ |
| Subir multimedia | ✅ | ✅ | ✅ | ❌ |
| Asignar clasificaciones | ✅ | ✅ | ✅ | ❌ |
| Crear/editar clasificaciones | ✅ | ✅ | ❌ | ❌ |
| Inicio/destacados | ✅ | ✅ | ❌ | ❌ |
| Importar | ✅ | ✅ | ❌ | ❌ |
| Exportar | ✅ | ✅ | ❌ | ❌ |
| Ver/gestionar leads | ✅ | ✅ | ❌ | ✅ |
| SEO global | ✅ | ✅ | ❌ | ❌ |
| Ver actividad | ✅ | ✅ | ❌ | ❌ |
| Ajustes inmobiliarios | ✅ | ✅ | ❌ | ❌ |
| Herramientas técnicas | ✅ | ❌ por defecto | ❌ | ❌ |
| `manage_options` WordPress | conserva ✅ | ❌ | ❌ | ❌ |

## Mapeo de secciones del administrador

Las pantallas se autorizarán por intención, no por pertenecer simplemente al menú WLA Inmo:

- **Resumen** → `view_wla_inmo_dashboard`.
- **Propiedades / Nueva propiedad** → capabilities del CPT.
- **Inicio y destacados** → `manage_wla_inmo_home`.
- **Importar** → `import_wla_properties`.
- **Exportar** → `export_wla_properties`.
- **Consultas / Leads** → view/edit/manage según acción.
- **Ubicaciones / Clasificaciones** → capabilities de taxonomías.
- **Multimedia inmobiliaria** → capability de propiedad + `upload_files` según acción.
- **SEO y visibilidad** → `manage_wla_inmo_seo`.
- **Calidad del catálogo** → lectura de dashboard; correcciones exigen capability del recurso afectado.
- **Actividad** → `view_wla_inmo_activity`.
- **Ayuda** → disponible para usuarios WLA autenticados según contexto.
- **Herramientas** → `manage_wla_inmo_tools`.
- **Ajustes** → `manage_wla_inmo_settings`.

## Instalación y upgrades

`Access\\RoleManager` usa un schema de roles versionado.

- En activación crea/reconcilia los roles.
- En requests administrativos solo comprueba una pequeña versión y actúa únicamente si cambia.
- Los roles propios se reconcilian contra las capabilities administradas por WLA, eliminando permisos WLA obsoletos.
- El rol nativo Administrator recibe capabilities WLA de forma aditiva; no se modifican sus permisos nativos.
- Los roles WLA nunca reciben `manage_options`.

## Seguridad

La existencia de una capability en esta matriz no sustituye los demás controles. Cada acción mutante deberá además usar nonce/CSRF, validación de entrada, autorización sobre objeto cuando corresponda y escaping en salida.

Los endpoints futuros REST/API deben aplicar esta misma matriz y no crear un segundo sistema de permisos paralelo.
