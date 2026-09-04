# Evidencia — Fase 2 / PR 2.8 Actividad e historial

Estado documental: `QA_PASSED / MERGE_PENDING`.

Issue: #37  
PR: #38  
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

## QA final

Head validado: `3b179b8574f5dfe02818723dc6d424e42bb8e47b`.

- Phase 1 CI `33863103340`: `SUCCESS`;
- Quality Gate / PHP 8.1: `SUCCESS`;
- PHP syntax: `SUCCESS`;
- WordPress Coding Standards security profile: `SUCCESS`;
- PHPStan: `SUCCESS`;
- PHPUnit: `3 tests / 40 assertions`;
- todos los smoke tests, incluido Activity: `SUCCESS`;
- release ZIP smoke: `SUCCESS`;
- WordPress 6.6.2 + PHP 8.1: `SUCCESS`;
- WordPress latest + PHP 8.3: `SUCCESS`;
- deactivate/uninstall preservan datos: `SUCCESS`;
- Activity Integration `33863103343`: `SUCCESS` en ambas matrices;
- Settings UI Integration `33863103400`: `SUCCESS`;
- Catalogue Quality Integration `33863103383`: `SUCCESS`;
- Help Center Integration `33863103337`: `SUCCESS`;
- Bootstrap Smoke `33863103346`: `SUCCESS`;
- Artifact `9932885215`;
- Artifact digest: `sha256:8f0a0e0f8c339ec85ce033904c7c73317b5da32dc9b8c5b767f88688d2a645af`;
- ZIP SHA-256: `9251c7ae23ed4342a151f2d2ee7ef99697b0e8b6022c6c40389ba6e7838543e8`.

## Findings corregidos

1. El primer Quality Gate de PR 2.8 detectó que la pantalla de Actividad utilizaba filtros GET de solo lectura sin una excepción documentada para el sniff de nonce y que un helper devolvía HTML ya escapado que WPCS no podía inferir como seguro. Se documentaron explícitamente los filtros como consultas GET sin mutación y el HTML del objeto ahora pasa además por `wp_kses_post()` antes de imprimirse.
2. Activity Integration ya había quedado verde en ambas matrices antes de esa corrección, confirmando que el finding era de endurecimiento/quality gate y no un fallo del flujo funcional.

No quedan findings críticos ni altos abiertos dentro del alcance de PR 2.8. Los warnings Node observados en GitHub Actions pertenecen a actions de terceros y no al runtime de WLA Inmo.

## Producción

`propiedadesmartinez.cl` no ha sido modificado.

## Cierre pendiente

PR 2.8 queda `QA_PASSED / MERGE_PENDING`. Solo debe pasar a `DONE` después del squash merge efectivo de PR #38 y del registro del SHA final en `main`.
