# Evidencia — Fase 1 / PR 1.8 Quality Gate y `0.1.0-alpha`

Estado documental: `QA_PASSED / MERGE_PENDING`.

Issue: #19  
PR: #20  
Rama: `ci/phase1-quality-gate`

## Objetivo

Cerrar el desarrollo técnico de Fase 1 mediante un quality gate repetible sobre el ZIP instalable real de WLA Inmo, sin desplegar ni modificar Propiedades Martínez.

## Release candidate

- Producto: `WLA Inmo`
- Versión: `0.1.0-alpha`
- PHP mínimo: `8.1`
- WordPress mínimo: `6.6`
- Licencia declarada: `GPL-2.0-or-later`

## Quality Gate final

Workflow: `Phase 1 CI`  
Run final: `33826185833`  
Head validado: `2769d14c547b5ad1221bb8236d5b8672122290ef`

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

Entorno:

- WordPress `6.6.2`;
- PHP `8.1`;
- MySQL `8.0`.

Resultado: `SUCCESS`.

Se construyó e instaló el ZIP real y se validó:

- activación;
- CPT `wla_property`;
- cinco taxonomías WLA;
- meta schema canónico;
- roles/capabilities;
- settings;
- tabla de índice;
- creación/indexación de propiedad sintética;
- preservación después de desactivar;
- preservación después de eliminar los archivos del plugin y ejecutar la política de uninstall.

### WordPress actual

Entorno:

- WordPress `latest` al momento del run;
- PHP `8.3`;
- MySQL `8.0`.

Resultado: `SUCCESS` con el mismo contrato de integración y preservación.

### Bootstrap Smoke

Run: `33826185820`  
Resultado: `SUCCESS`.

El smoke histórico se mantiene como guardrail adicional y no fue reemplazado por el nuevo quality gate.

## Artefacto final

Artifact ID: `9920034253`  
Nombre: `wla-inmo-0.1.0-alpha-quality`  
Tamaño del artifact contenedor: `52985` bytes  
Digest del artifact contenedor: `sha256:fd4cc13c55f9dec8d8355b1836b429f76345d9f64c62777d3a5c60547e4ccd45`  
Expira: `2026-12-03`.

El artifact contiene:

- `dist/wla-inmo-0.1.0-alpha.zip`;
- `dist/wla-inmo-0.1.0-alpha.zip.sha256`;
- el `composer.lock` exacto generado para la resolución de herramientas de QA de este run.

Checksum del ZIP instalable:

```text
c6189cd0a295fbec807c412e93ffe1c545df1b594e9219a8d18465db02767dde  wla-inmo-0.1.0-alpha.zip
```

## Historial de findings de PR 1.8

### Finding CI-1 — ruta duplicada del ZIP

El primer intento de integración construyó una ruta incorrecta al anteponer `GITHUB_WORKSPACE` a un path que ya era absoluto. WP-CLI rechazó la instalación antes de ejecutar el plugin.

Corrección: usar directamente el output absoluto de `bin/build-plugin.sh`.

Clasificación: harness CI, no defecto runtime del plugin.

### Finding CI-2 — lint accidental de dependencias vendor

Al introducir herramientas Composer, el comando genérico de sintaxis podía recorrer `vendor`. El gate fue acotado explícitamente a código propio y tests; las dependencias se validan mediante Composer.

Clasificación: diseño de QA.

### Finding QA-1 — PHPStan 1.12 desactualizado

Un run verde inicial informó que la rama `1.12` estaba obsoleta. Se elevó el requisito a PHPStan `^2.2`; el run final resolvió `2.2.13` y permaneció verde.

Clasificación: mejora preventiva de tooling.

### Finding SEC-1 — credencial estática de WordPress CI

La instalación de prueba utilizaba inicialmente una contraseña sintética fija. Se reemplazó por una contraseña efímera generada durante el job mediante `openssl rand`.

Clasificación: hardening del harness; nunca existió una credencial productiva.

## Composer / reproducibilidad

El build de producción no requiere Composer ni Node en el servidor. Composer se utiliza para generar el autoloader optimizado y para herramientas de desarrollo.

El lock exacto de herramientas del run final queda preservado dentro del artifact de evidencia. En esta PR el lock de desarrollo no queda incorporado al repositorio; se registra como mejora de trazabilidad de prioridad baja antes de Beta, no como riesgo runtime del ZIP alpha, porque el artefacto probado ya contiene exclusivamente dependencias de producción y su checksum está fijado.

## Seguridad y datos

- no se utilizaron datos de producción;
- fixtures de integración son sintéticos;
- no se cargaron secretos productivos;
- WLA Inmo no depende de WooCommerce, Elementor, ACF o WPCode;
- desactivar/eliminar el plugin mantiene los datos por defecto;
- no se ejecutó ninguna acción sobre `propiedadesmartinez.cl`.

## Quality Gate de salida de Fase 1

Antes del merge de PR #20:

- PR 1.1–1.7: `DONE`;
- PR 1.8: `QA_PASSED / MERGE_PENDING`;
- CI final: `SUCCESS`;
- integración WordPress mínima: `SUCCESS`;
- integración WordPress actual: `SUCCESS`;
- bootstrap smoke: `SUCCESS`;
- artifact: disponible;
- findings críticos/altos abiertos del alcance: ninguno conocido.

Fase 1 **no debe marcarse DONE en este documento antes del merge de PR #20**. El cierre formal se documentará después del squash merge.
