# Evidencia — Fase 2 / PR 2.10 Quality Gate de Administración

Estado documental: `IN_PROGRESS / QA_RUNNING`.

Issue: #41  
Rama: `test/phase2-admin-quality-gate`

## Objetivo

Cerrar Fase 2 con una auditoría reproducible de la administración completa de WLA Inmo, sin sumar nuevas funcionalidades de negocio y sin modificar producción.

## Baseline auditado

PR 2.1–2.9 están mergeadas. El baseline incluye:

- Admin Shell;
- listado profesional de propiedades;
- editor guiado;
- multimedia;
- Calidad del catálogo;
- Centro de Ayuda/onboarding;
- Ajustes;
- Actividad/historial;
- Dashboard/Resumen operativo.

## E2E Playwright

Se incorpora una suite Playwright propia con tooling versionado explícitamente:

- `@playwright/test` `1.55.0`;
- `@axe-core/playwright` `4.10.2`;
- Node 22 en CI;
- Chromium instalado por Playwright.

Configuración:

- screenshots solo al fallar;
- video retenido al fallar;
- trace en primer retry;
- un worker en CI para mantener estado administrativo determinista;
- timeouts acotados;
- report HTML como evidencia.

### Flujos cubiertos inicialmente

- login de Administrator;
- Resumen operativo;
- creación de propiedad por WordPress admin;
- código/estado/precio/operación/tipo/comuna;
- guardar borrador;
- publicar;
- cambiar precio y estado comercial;
- abrir Calidad del catálogo;
- abrir Actividad;
- abrir Centro de Ayuda;
- usuario Editor intentando entrar a Ajustes por URL directa;
- responsive del Resumen.

## Accesibilidad automática

La suite integra axe sobre la UI propia de WLA Inmo, no sobre todo WordPress core.

Se ejecutan reglas WCAG A/AA soportadas por la versión fijada de axe y se consideran bloqueantes las violaciones `serious` y `critical` dentro del componente WLA analizado.

La revisión automática no reemplaza la revisión manual de teclado/focus/lectura que debe quedar documentada antes del cierre.

## Responsive

La configuración incluye viewports:

- 360×800;
- 390×844;
- 768×1024;
- 1024×768;
- 1440×1000.

La primera prueba responsive verifica que el contenedor propio del Resumen no genere overflow horizontal y que sus bloques principales continúen visibles.

## Seguridad de credenciales E2E

Los usuarios y contraseñas de CI son efímeros:

- se generan durante el workflow;
- se enmascaran con GitHub Actions;
- se entregan a Playwright solo mediante variables de entorno;
- no existen credenciales reales ni passwords fijos versionados en el repositorio.

## Reproducibilidad de dependencias Node

`package.json` usa versiones exactas. El primer workflow genera además `package-lock.json` y lo incluye en el artifact de evidencia; antes de cerrar PR 2.10 ese lock debe quedar versionado y el workflow migrará a `npm ci`.

## CI agregado

Workflow: `Administration Quality Gate`.

Baseline inicial:

- WordPress 6.6.2;
- PHP 8.1;
- MySQL 8;
- Node 22;
- Chromium.

El workflow:

1. construye el ZIP instalable real;
2. instala WordPress limpio;
3. activa WLA Inmo desde el ZIP;
4. crea taxonomías fixture;
5. crea un Editor de propiedades temporal;
6. levanta WordPress local en el runner;
7. ejecuta Playwright;
8. conserva report, screenshots/videos/traces, lockfile generado y log del servidor cuando corresponda.

## Quality Gate pendiente

Todavía deben completarse y registrarse antes de `DONE`:

- resultado real del nuevo workflow;
- package-lock versionado + `npm ci`;
- matriz de permisos positiva/negativa ampliada;
- pruebas negativas de nonce/capability/IDOR;
- revisión manual de accesibilidad/teclado;
- revisión responsive de las pantallas administrativas prioritarias;
- benchmark sintético 100/1.000/5.000 según viabilidad CI;
- auditoría final de assets condicionales;
- limpieza de copias obsoletas del editor;
- findings y correcciones;
- integraciones heredadas completas;
- artifact final y SHA-256;
- squash merge.

## Producción

`propiedadesmartinez.cl` no ha sido modificado.
