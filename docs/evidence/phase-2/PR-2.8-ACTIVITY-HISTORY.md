# Evidencia — Fase 2 / PR 2.8 Actividad e historial

Estado documental: `IN_PROGRESS / QA PENDING`.

Issue: #37  
Rama: `feat/phase2-activity-history`

## Objetivo

Incorporar una bitácora operativa propia para responder qué cambió, cuándo, sobre qué objeto y por quién, sin convertir WLA Inmo en una herramienta de telemetría ni almacenar datos privados innecesarios.

## Persistencia

Se agrega la tabla versionada `{$wpdb->prefix}wla_inmo_activity` mediante `Activity\Schema` y `Core\Installer`.

Campos:

- ID;
- tipo de evento;
- tipo e ID de objeto;
- ID del actor cuando existe;
- resumen técnico estable;
- contexto JSON estrictamente allowlisted;
- fecha UTC.

Índices iniciales:

- fecha;
- timeline por objeto;
- timeline por tipo de evento.

No se añadió un índice por actor hasta demostrar una necesidad real de consulta.

## Contrato de eventos

`Activity\EventTypes` define un catálogo cerrado:

- `property.created`;
- `property.wp_status_changed`;
- `property.price_changed`;
- `property.commercial_status_changed`;
- `property.featured_changed`;
- `settings.changed`;
- `settings.property_base_changed`;
- `settings.rewrite_rules_applied`.

Cada tipo posee una allowlist propia de contexto. `Activity\Recorder` rechaza tipos desconocidos y descarta keys no aprobadas antes de persistir.

## Privacidad

La bitácora no toma payloads completos de request ni referencia campos privados de propiedades.

No se guardan:

- nonces/cookies;
- tokens o claves;
- IP/user-agent;
- dirección privada;
- notas internas;
- valores de email/teléfono/WhatsApp;
- payloads arbitrarios.

Para cambios de Ajustes se guarda únicamente la lista de nombres de keys modificadas. El cambio de `property_base` registra sus slugs anterior/nuevo porque son datos técnicos no sensibles necesarios para trazabilidad.

## Observación de cambios

`Activity\Observer` escucha eventos WordPress y de WLA Inmo para no depender exclusivamente del formulario HTML del editor.

- creación de `wla_property`;
- transición real de estado de publicación;
- precio CLP/UF/USD;
- estado comercial;
- destacada;
- cambios de settings;
- cambio de base de URL;
- aplicación exitosa de reglas de enlaces.

Autosaves/revisiones se ignoran. Cambios sin diferencia efectiva no generan eventos.

`Settings\RewriteManager` publica `wla_inmo_rewrite_rules_applied` solo después del flush controlado exitoso, manteniendo Settings desacoplado del almacenamiento de Actividad.

## Administración

`WLA Inmo → Actividad` deja de ser placeholder y entrega:

- listado paginado server-side;
- fecha/hora convertida a la zona del sitio;
- etiqueta humana del evento;
- objeto/propiedad enlazable cuando el usuario puede editarlo;
- actor;
- detalle compacto;
- filtros por evento, propiedad y rango de fecha.

Los usuarios sin `view_wla_inmo_activity` no pueden acceder a la pantalla.

La ficha de propiedad incorpora un metabox `Historial operativo` con los últimos 10 eventos y enlace al historial completo. Las revisiones de WordPress siguen siendo la fuente para historia editorial del título/contenido.

## Retención

`Activity\Retention` consume `activity_retention_months`, cuyo esquema actual acepta 1–120 meses y usa 12 meses por defecto.

- cron diario;
- cleanup por lotes de 500;
- nunca se borra durante cada request ni al guardar Ajustes;
- deactivation elimina solo el evento programado y conserva la tabla/datos;
- uninstall conserva los datos según D58.

## Tests

- smoke `tests/smoke/activity-log.php`;
- integración real `tests/integration/assert-activity.php`;
- workflow `Activity Integration` en WordPress 6.6.2/PHP 8.1 y WordPress latest/PHP 8.3;
- release smoke exige clases y assets de Activity;
- guard de privacidad bloquea request payloads, tracking y campos privados en `src/Activity`;
- CI heredado continúa ejecutando tests de Core, Settings, Calidad y Ayuda.

## Producción

`propiedadesmartinez.cl` no ha sido modificado.

## Cierre pendiente

Antes de pasar PR 2.8 a `DONE` deben registrarse PR, CI final, Activity Integration final, artifact/checksum, findings/correcciones y squash merge.
