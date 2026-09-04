# Evidencia — Fase 2 / PR 2.7 Ajustes UI

Estado documental: `DONE`.

Issue: #35  
PR: #36  
Squash merge: `5c99ed02f4a71cf57716cc8d9a46cb4094856464`  
Rama histórica: `feat/phase2-settings-ui`

## Objetivo

Reemplazar el placeholder `WLA Inmo → Ajustes` por una configuración segura y entendible, reutilizando el contrato canónico `wla_inmo_settings` y evitando controles que simulen funcionalidades aún no implementadas.

## Ajustes canónicos

`Settings\Schema` continúa siendo la allowlist y sanitización única del option `wla_inmo_settings`.

Se conservan:

- `country_code`;
- `currency_primary`;
- `area_unit`;
- `map_provider`;
- `property_base`;
- `business_name`.

Se agregan datos reutilizables por frontend/leads futuros:

- `business_email`;
- `business_phone`;
- `whatsapp_number`;
- `business_address`.

También se formalizan dos políticas ya aprobadas en Fase 0:

- `lead_retention_months = 24` por D45;
- `activity_retention_months = 12` por D57.

Ambas están disponibles como contrato de configuración antes de que los módulos que las consumen estén activos; guardarlas todavía no elimina datos.

## Pestañas

`Admin\SettingsPage` expone:

1. General;
2. Propiedades;
3. Contacto;
4. SEO;
5. Integraciones;
6. Rendimiento;
7. Privacidad;
8. Avanzado.

General, Propiedades, Contacto, Integraciones y Privacidad contienen campos reales. SEO y Rendimiento son deliberadamente informativos hasta sus fases correspondientes y no crean toggles sin implementación.

## URLs y reglas de enlaces

`Settings\RewriteManager` implementa una operación controlada para `property_base`.

Flujo:

1. `property_base` se guarda sanitizado;
2. los hooks específicos de creación/actualización de `wla_inmo_settings` detectan la base vigente;
3. se guarda `wla_inmo_rewrite_flush_pending` cuando corresponde aplicar una nueva base;
4. no se ejecuta `flush_rewrite_rules()` durante sanitización ni guardado;
5. la UI muestra una advertencia y enlaza a Avanzado;
6. en una solicitud posterior, cuando el CPT ya fue registrado con la base nueva, el usuario autorizado puede ejecutar `Aplicar reglas de enlaces`;
7. la acción requiere nonce + `manage_wla_inmo_settings`;
8. `flush_rewrite_rules(false)` se ejecuta una sola vez;
9. el pending state se elimina.

El guard del release verifica que el flush no aparezca en `SettingsPage`, `Schema` ni `Registry`, y que exista exactamente una llamada controlada dentro de `RewriteManager`.

También se cubre el caso de primera configuración: si `wla_inmo_settings` todavía no existe y el primer guardado define una base distinta de `propiedades`, `add_option_wla_inmo_settings` marca el pending state para no saltarse el flujo seguro.

## Contacto

Contacto deja un contrato neutral y reutilizable para:

- email público;
- teléfono;
- WhatsApp normalizado;
- dirección pública de la inmobiliaria.

Estos datos son distintos de la dirección privada de cada propiedad y no almacenan secrets.

## Seguridad

- `manage_wla_inmo_settings` para pantalla y mutaciones;
- nonce dedicado para guardar ajustes;
- nonce dedicado para aplicar rewrites;
- allowlist por pestaña;
- `Settings\Schema` descarta keys desconocidas;
- email, teléfono, WhatsApp, slug, enums y retenciones se sanitizan por tipo;
- escaping tardío;
- cero requests remotos;
- no se modifica `wp-config.php` ni `WP_DEBUG`;
- no se usa `manage_options` como sustituto de capabilities WLA.

## UX

- tabs navegables sin framework JS;
- textos en lenguaje de negocio;
- advertencia visible antes de cambios de URL;
- feedback de guardado/error;
- estado técnico sin secretos;
- CSS propio cargado solo en Ajustes;
- pestañas horizontales desplazables en pantallas pequeñas.

## Tests

`tests/smoke/settings-ui.php` cubre:

- defaults de contacto/privacidad;
- normalización de property base;
- sanitización de contacto;
- normalización WhatsApp;
- límites de retención;
- eliminación de unknown keys;
- 8 pestañas;
- allowlists por pestaña;
- SEO/Rendimiento sin settings falsos;
- nonce/capability presentes;
- ausencia de flush en save/sanitizer;
- una sola llamada controlada de rewrite;
- ausencia de requests remotos.

Integración WordPress:

- `tests/integration/assert-settings.php` guarda settings reales y comprueba que cambiar `property_base` solo deje pending state sin mutar rewrite rules en el mismo request;
- prueba contrato de contacto y retenciones;
- comprueba unknown-key discard;
- renderiza Contacto con datos canónicos;
- verifica que Editor de propiedades no tenga capability de Ajustes;
- `apply-settings-rewrite.php` ejecuta la acción controlada con nonce de un administrador;
- `assert-settings-rewrite-applied.php` comprueba pending state limpio, CPT registrado con nueva base y reglas regeneradas.

Workflow dedicado: `Settings UI Integration` en WordPress 6.6.2/PHP 8.1 y WordPress latest/PHP 8.3.

## QA final

Head de código validado: `f6cef07f0f165ea5ac7cd3ea46ef82ae68f5e4ac`.

- Phase 1 CI `33861845409`: `SUCCESS`;
- Quality Gate / PHP 8.1: `SUCCESS`;
- PHP syntax: `SUCCESS`;
- WordPress Coding Standards security profile: `SUCCESS`;
- PHPStan: `SUCCESS`;
- PHPUnit: `3 tests / 40 assertions`;
- todos los smoke tests, incluido Settings UI: `SUCCESS`;
- release ZIP smoke: `SUCCESS`;
- WordPress 6.6.2 + PHP 8.1: `SUCCESS`;
- WordPress latest + PHP 8.3: `SUCCESS`;
- deactivate/uninstall preservan datos: `SUCCESS`;
- Settings UI Integration `33861845590`: `SUCCESS` en ambas matrices;
- Catalogue Quality Integration `33861845599`: `SUCCESS`;
- Help Center Integration `33861845705`: `SUCCESS`;
- Bootstrap Smoke `33861845445`: `SUCCESS`;
- Artifact `9932423348`;
- Artifact digest: `sha256:a626ed57d9a2c36aeb2bd6413f73024669b2c04614c6c2347bb656814f1906a3`;
- ZIP SHA-256: `a04e74b6c19a1a296f9960e5237785be6c3352b042a4b91cb01317137b18e6a9`;
- squash merge final en `main`: `5c99ed02f4a71cf57716cc8d9a46cb4094856464`.

## Findings corregidos

1. La primera integración específica de Ajustes validaba `rewrite_rules` sobre una instalación WordPress recién creada con permalinks simples. En ese modo WordPress no persiste reglas bonitas, por lo que la prueba fallaba aunque el pending state, el CPT y la acción controlada funcionaran. El workflow ahora configura una estructura real de permalinks (`/%postname%/`) antes de probar la regeneración y ambas matrices quedan verdes.
2. Se añadió cobertura explícita para el primer guardado de `wla_inmo_settings` mediante `add_option_wla_inmo_settings`, evitando que una base personalizada inicial eluda el pending state.

No quedan findings críticos ni altos abiertos dentro del alcance de PR 2.7. Los warnings Node observados en GitHub Actions pertenecen a actions de terceros y no al runtime de WLA Inmo.

## Producción

`propiedadesmartinez.cl` no ha sido modificado.

## Cierre

PR 2.7 está `DONE`. PR #36 fue integrada mediante squash merge y el SHA final quedó registrado en este documento.
