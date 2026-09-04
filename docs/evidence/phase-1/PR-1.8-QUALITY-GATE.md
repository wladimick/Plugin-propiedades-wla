# Evidencia — Fase 1 / PR 1.8 Quality Gate y `0.1.0-alpha`

Estado documental: `DONE`.

Issue: #19 — `CLOSED`  
PR: #20 — `MERGED`  
Squash merge: `a142a4373ef37e14cd20b5a99105abeab0c1778d`  
Rama histórica: `ci/phase1-quality-gate`

## Objetivo

Cerrar el desarrollo técnico de Fase 1 mediante un quality gate repetible sobre el ZIP instalable real de WLA Inmo, sin desplegar ni modificar Propiedades Martínez.

## Release alpha

- Producto: `WLA Inmo`
- Versión: `0.1.0-alpha`
- PHP mínimo: `8.1`
- WordPress mínimo: `6.6`
- Licencia declarada: `GPL-2.0-or-later`

## Quality Gate final

Workflow: `Phase 1 CI`  
Run final: `33826185833`  
Head ejecutable validado: `2769d14c547b5ad1221bb8236d5b8672122290ef`

Resultado global: `SUCCESS`.

### Quality Gate / PHP 8.1

Resultado: `SUCCESS`.

Validaciones ejecutadas:

- Composer manifest;
- PHP syntax del código propio y tests, excluyendo vendor;
- WordPress Coding Standards con perfil de seguridad;
- PHPStan `2.2.13`, nivel inicial 6 sobre contratos puros del core;
- PHPUnit `10.5.64`;
- `3 tests / 40 assertions`;
- todos los smoke tests históricos;
- build del ZIP instalable;
- smoke del ZIP de release;
- SHA-256;
- artifact final.

### WordPress mínimo soportado

Entorno: WordPress `6.6.2` + PHP `8.1` + MySQL `8.0`.

Resultado: `SUCCESS`.

Se construyó e instaló el ZIP real y se validó activación, CPT, cinco taxonomías, meta schema, roles/capabilities, settings, tabla índice, creación/indexación de propiedad sintética y preservación de datos después de desactivar y eliminar el plugin.

### WordPress actual

Entorno: WordPress `latest` al momento del run + PHP `8.3` + MySQL `8.0`.

Resultado: `SUCCESS` con el mismo contrato de integración y preservación.

### Bootstrap Smoke

Run: `33826185820`  
Resultado: `SUCCESS`.

El smoke histórico permanece como guardrail adicional.

## Artefacto final

Artifact ID: `9920034253`  
Nombre: `wla-inmo-0.1.0-alpha-quality`  
Tamaño del artifact contenedor: `52985` bytes  
Digest del artifact contenedor: `sha256:fd4cc13c55f9dec8d8355b1836b429f76345d9f64c62777d3a5c60547e4ccd45`  
Expira: `2026-12-03`.

El artifact contiene:

- `dist/wla-inmo-0.1.0-alpha.zip`;
- `dist/wla-inmo-0.1.0-alpha.zip.sha256`;
- el `composer.lock` exacto generado para la resolución de herramientas de QA de ese run.

Checksum del ZIP instalable:

```text
c6189cd0a295fbec807c412e93ffe1c545df1b594e9219a8d18465db02767dde  wla-inmo-0.1.0-alpha.zip
```

## Historial de findings de PR 1.8

### CI-1 — ruta duplicada del ZIP

El primer intento de integración antepuso `GITHUB_WORKSPACE` a una ruta ya absoluta. WP-CLI rechazó el archivo antes de ejecutar WLA Inmo.

Corrección: utilizar directamente el output absoluto de `bin/build-plugin.sh`.

Clasificación: harness CI, no defecto runtime.

### CI-2 — lint accidental de dependencias vendor

El lint genérico podía recorrer dependencias instaladas por Composer.

Corrección: limitar PHP syntax a código propio y tests; dependencias verificadas mediante Composer.

### QA-1 — actualización PHPStan

Un run verde con PHPStan 1.12 indicó obsolescencia. Se elevó el requisito a `^2.2`; el run final resolvió `2.2.13` y continuó verde.

### SEC-1 — credencial estática de WordPress CI

Una contraseña sintética fija del entorno CI fue reemplazada por una credencial efímera generada con `openssl rand` durante el job. Nunca fue una credencial productiva.

## Composer / reproducibilidad

El ZIP de producción no requiere Composer ni Node en el servidor. Composer se usa para construir el autoloader optimizado y las herramientas de desarrollo.

El lock exacto del quality gate queda preservado dentro del artifact. El lock de tooling no está versionado todavía en el repositorio y permanece como mejora de prioridad baja antes de Beta. No se considera riesgo runtime del alpha porque el ZIP probado está fijado mediante checksum y no incluye dependencias de desarrollo.

## Seguridad y datos

- fixtures exclusivamente sintéticos;
- sin secretos productivos;
- sin datos de Propiedades Martínez;
- sin dependencia de WooCommerce, Elementor, ACF o WPCode;
- desactivación no elimina datos;
- uninstall no elimina datos por defecto;
- producción no fue afectada.

## Cierre de Fase 1

PR #20 fue mergeada después de quedar verdes los quality gates aplicables. Por tanto PR 1.8 está `DONE` y cumple el último hito técnico de Fase 1.

El cierre global de Fase 1 y la entrada a Fase 2 quedan registrados en `PROJECT-STATUS.md` y `PHASE-2-BACKLOG.md` mediante la PR documental posterior al merge.
