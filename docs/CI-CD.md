# CI/CD

## Objetivo

Automatizar controles repetibles para que una PR no dependa solo de revisión manual.

## Workflows previstos

### `ci.yml`

Se ejecuta en cada PR y push relevante.

Jobs previstos:

1. **PHP syntax**
2. **PHPCS / WordPress Coding Standards**
3. **Static analysis**
4. **Unit tests**
5. **WordPress integration tests**
6. **Build validation**
7. **Security/dependency checks**

### `e2e.yml`

Para PRs funcionales y/o ejecución manual:

- levantar WordPress de prueba;
- instalar plugin;
- activar;
- cargar fixture;
- ejecutar Playwright;
- guardar screenshots/videos en fallos.

### `performance.yml`

En cambios frontend/release candidate:

- Lighthouse CI;
- páginas archive/single/home de referencia;
- guardar resultados comparables.

### `release.yml`

Al crear tag/release:

- validar versión;
- correr suite requerida;
- construir ZIP limpio;
- checksum;
- adjuntar artefacto.

## Matriz

La matriz definitiva debe definirse según mínimos soportados. Antes de 1.0 debe existir prueba contra múltiples versiones de PHP y WordPress soportadas.

## Required checks propuestos

Cuando los workflows estén implementados y estables:

- PHP Syntax
- Coding Standards
- Static Analysis
- Unit Tests
- Integration Tests
- Build

E2E/Performance pueden hacerse requeridos progresivamente para evitar CI frágil durante etapas tempranas.

## Artefactos

Conservar cuando aporten auditoría:

- reportes de tests;
- coverage;
- screenshots E2E fallidos;
- Lighthouse;
- ZIP de release;
- reportes de análisis estático.

## Fallos

No desactivar checks para “hacer pasar” una PR. Si un check es incorrecto o inestable:

1. documentar la causa;
2. abrir corrección;
3. registrar excepción temporal en la PR;
4. restaurar el gate cuanto antes.

## Entornos

Previstos:

- desarrollo local;
- CI efímero;
- staging;
- producción.

Nunca usar producción como entorno primario de QA.

## Datos de test

Usar fixtures sintéticos. No copiar bases productivas con datos personales a CI.
