# Evidencia — Fase 1 / PR 1.1 Bootstrap y build

Estado documental: `IN_PROGRESS` hasta que la PR sea mergeada.

Issue: #4  
Rama: `feat/phase1-bootstrap-build`

## Objetivo

Crear el primer WLA Inmo Core instalable sin introducir todavía modelo Property, UI, importación, frontend ni migración productiva.

## Implementación incluida

- bootstrap `plugin/wla-inmo/wla-inmo.php`;
- versión `0.1.0-alpha.1`;
- `Requires PHP: 8.1`;
- `Requires at least: 6.6`;
- constantes de bootstrap;
- Composer PSR-4 `WLA\\Inmo\\ => src/`;
- fallback PSR-4 seguro para source checkout sin `vendor/`;
- `Core\\Requirements`;
- `Core\\Plugin`;
- `Core\\Activator`;
- `Core\\Deactivator`;
- `uninstall.php` no destructivo;
- build ZIP automatizado;
- smoke tests de requisitos y release ZIP;
- workflow GitHub Actions `Bootstrap Smoke`.

## Decisiones aplicadas

- ADR-001: PHP 8.1+, WordPress 6.6+, sin dependencias obligatorias WooCommerce/Elementor/ACF/WPCode.
- ADR-004: core desacoplado del tema.
- ADR-010: seguridad por defecto y datos conservados.
- ADR-011: tests automatizados, build auditable y ZIP instalable.
- ADR-013: WLA Inmo Light no participa en el bootstrap del core.

## Controles de smoke definidos

1. `composer validate --no-check-lock`.
2. `php -l` sobre PHP del plugin/tests.
3. `php tests/smoke/requirements.php`.
4. `bash bin/build-plugin.sh`.
5. `bash bin/smoke-plugin.sh <zip>`.
6. Verificación de archivos obligatorios dentro del ZIP.
7. Verificación de autoload Composer en artefacto.
8. Rechazo de referencias runtime a WooCommerce, Elementor, WPCode o `get_field()` en el core actual.
9. Publicación del ZIP como artifact del workflow.

## Riesgo

Bajo. No registra CPT, taxonomías ni tablas. No modifica contenido del sitio actual y no realiza migraciones.

## Producción

No afectada. Esta implementación existe únicamente en el repositorio/artefacto de desarrollo.

## Cierre

Completar después del merge con la PR como evidencia canónica. La Fase 1 permanece `IN_PROGRESS`; el siguiente alcance es PR 1.2 — entidad `wla_property`.
