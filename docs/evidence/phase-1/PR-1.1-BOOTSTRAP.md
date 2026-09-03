# Evidencia — Fase 1 / PR 1.1 Bootstrap y build

Estado documental: `QA_PASSED / MERGE_PENDING`.

Issue: #4  
PR: #5  
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

## Controles de smoke ejecutados

Workflow run: `33817665522`  
Job: `PHP 8.1 / Build Smoke`  
Resultado: `SUCCESS`

Pasaron correctamente:

1. Setup PHP 8.1 / Composer 2.
2. `composer validate --no-check-lock`.
3. `php -l` sobre PHP del plugin/tests.
4. `php tests/smoke/requirements.php`.
5. `bash bin/build-plugin.sh`.
6. `bash bin/smoke-plugin.sh <zip>`.
7. Verificación de archivos obligatorios dentro del ZIP.
8. Verificación de autoload Composer en artefacto.
9. Rechazo de referencias runtime a WooCommerce, Elementor, WPCode o `get_field()` en el core actual.
10. Publicación del ZIP como artifact del workflow.

## Artefacto

- Nombre: `wla-inmo-0.1.0-alpha.1`
- Artifact ID: `9917111397`
- Tamaño del artifact: `14970` bytes
- Digest: `sha256:0f4bd1aac5c278bce2cc6f2399ee236149cf722eec439d149087562c714d9fb9`
- Expiración informada por GitHub Actions: 2026-12-02

El ZIP dentro del artifact fue construido en CI y pasó el smoke de release antes de subirse.

## Riesgo

Bajo. No registra CPT, taxonomías ni tablas. No modifica contenido del sitio actual y no realiza migraciones.

## Producción

No afectada. Esta implementación existe únicamente en el repositorio/artefacto de desarrollo.

## Cierre

La evidencia técnica requerida para merge está verde. Después del merge, PR #5 será la evidencia canónica de cierre de PR 1.1. La Fase 1 permanece `IN_PROGRESS`; el siguiente alcance es PR 1.2 — entidad `wla_property`.
