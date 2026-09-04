# Evidencia — Fase 1 / PR 1.7 Settings y contratos públicos mínimos

Estado documental: `QA_PASSED / MERGE_PENDING`.

Issue: #17  
PR: #18  
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

## Seguridad y extensibilidad

- settings con `manage_wla_inmo_settings`, no `manage_options`;
- raw settings fuera de REST;
- property slug sanitizado;
- resolver rechaza traversal/null bytes/no-PHP;
- no hay flush de rewrites por request;
- hooks mínimos: `wla_inmo_settings_defaults`, `wla_inmo_template_candidates`, `wla_inmo_template_path`.

## Historial de QA

### Run `33825179697` — FAILURE

El nuevo smoke falló en la expectativa de slug `Región`. El código productivo utiliza `sanitize_title()` de WordPress; el stub del test no simulaba la normalización de caracteres acentuados y producía un resultado diferente.

Acción: se corrigió **el fixture/stub del test**, no se relajó el contrato productivo. El stub ahora emula la transliteración relevante antes de validar el slug esperado.

### Run final `33825238074` — SUCCESS

Job: `PHP 8.1 / Build Smoke`.

Pasaron:

1. Composer validation.
2. PHP syntax.
3. Todos los source smoke tests.
4. `settings-contracts.php` con sanitización y theme resolver.
5. Build ZIP.
6. Release ZIP smoke.
7. Composer autoload de Preset/Settings/TemplateResolver.
8. Upload de artifact.

## Artefacto final

- Artifact ID: `9919688589`
- Nombre: `wla-inmo-0.1.0-alpha.1`
- Tamaño: `36301` bytes
- Digest: `sha256:24d3319a48f27633df06baded63a959912722fb5afb22287323a5cc7b239224d`
- Expira: 2026-12-03

## Documentación

- `docs/SETTINGS-CONTRACT.md`;
- `docs/TEMPLATE-CONTRACT.md`;
- `docs/THEME-INTEGRATION.md` mantiene el diseño global; plantillas visuales completas quedan en Fase 4.

## Producción

No afectada.

## Cierre

QA requerido para merge aprobado. Después del squash merge, PR #18 será la evidencia canónica y el siguiente alcance será PR 1.8 — CI de Fase 1 y release alpha.
