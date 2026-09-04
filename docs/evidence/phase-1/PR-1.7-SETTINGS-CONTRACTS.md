# Evidencia — Fase 1 / PR 1.7 Settings y contratos públicos mínimos

Estado documental: `IN_PROGRESS`.

Issue: #17  
Rama: `feat/phase1-settings-contracts`

## Objetivo

Definir configuración base reutilizable y el contrato inicial plugin↔tema sin introducir una UI final ni depender de WLA Inmo Light.

## Componentes

- `Localization\\ChilePreset` — valores chilenos iniciales encapsulados.
- `Settings\\Schema` — contrato y sanitización.
- `Settings\\Repository` — lectura con cache/defaults.
- `Settings\\Registry` — Settings API con capability WLA.
- `Frontend\\TemplateResolver` — theme override → plugin fallback.
- `Properties\\PostType` — consume `property_base` configurado.

## Configuración inicial

- `country_code`;
- `currency_primary`;
- `area_unit`;
- `map_provider`;
- `property_base`;
- `business_name` opcional/vacío por defecto.

Claves desconocidas se descartan. Raw settings no se exponen por REST.

## Seguridad

- settings autorizados mediante `manage_wla_inmo_settings`, no `manage_options`;
- slug de propiedades sanitizado;
- resolver rechaza `..`, null bytes, segmentos inválidos y archivos no PHP;
- paths no provienen directamente de una entrada de usuario;
- no se ejecuta `flush_rewrite_rules()` por request.

## Extensibilidad pública mínima

- `wla_inmo_settings_defaults`;
- `wla_inmo_template_candidates`;
- `wla_inmo_template_path`.

No se congela una API mayor antes de existir casos de uso reales.

## Tests definidos

`tests/smoke/settings-contracts.php` valida preset sin branding, sanitización, discard de claves desconocidas, repository, Settings API/capability, base configurable del CPT, overrides de tema y protección de paths.

El release smoke exige/autoloadea Preset, Settings y TemplateResolver.

## Documentación

- `docs/SETTINGS-CONTRACT.md`;
- `docs/TEMPLATE-CONTRACT.md`;
- `docs/THEME-INTEGRATION.md` conserva el diseño global; las plantillas visuales completas permanecen en Fase 4.

## Producción

No afectada.

## Cierre

Completar con PR, workflow, artifact, digest y squash merge después de QA.
