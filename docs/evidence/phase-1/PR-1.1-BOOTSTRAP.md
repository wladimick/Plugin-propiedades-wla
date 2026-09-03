# Evidencia — Fase 1 / PR 1.1 Bootstrap y build

Estado documental: `DONE`.

Issue: #4 — cerrada  
PR: #5 — mergeada mediante squash  
Merge commit: `7ca5b05f6763a7f8dc83f60995b2dc0760f68114`

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

## Controles de smoke finales

Workflow run final de la PR: `33817719440`  
Job: `PHP 8.1 / Build Smoke`  
Resultado: `SUCCESS`

Pasaron correctamente:

1. Setup PHP 8.1 / Composer 2.
2. `composer validate --no-check-lock`.
3. `php -l` sobre PHP del plugin/tests.
4. smoke de requisitos mínimos.
5. build del ZIP.
6. smoke del release ZIP.
7. verificación de archivos obligatorios.
8. verificación de autoload Composer.
9. rechazo de referencias runtime a WooCommerce, Elementor, WPCode o `get_field()`.
10. publicación del ZIP como artifact.

## Artefacto final de PR #5

- Nombre: `wla-inmo-0.1.0-alpha.1`
- Artifact ID: `9917130469`
- Tamaño: `14961` bytes
- Digest: `sha256:2b55dabfe9f9392cf97436812c203fd1ea58daee961d7173e2ad832c3614cc86`
- Expiración informada por GitHub Actions: 2026-12-02

## Riesgo / producción

Bajo. PR #5 no registró CPT, taxonomías ni tablas y no realizó migraciones. Producción no fue afectada.

## Cierre

PR 1.1 está completada y auditada. La Fase 1 continúa `IN_PROGRESS`; el siguiente alcance es PR 1.2 — entidad `wla_property`.
