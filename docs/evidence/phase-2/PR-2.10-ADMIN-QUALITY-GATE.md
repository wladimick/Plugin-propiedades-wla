# Evidencia — Fase 2 / PR 2.10 Quality Gate de Administración

Estado documental: `IN_PROGRESS / QA_RUNNING`.

Issue: #41  
PR: #42  
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

- `@playwright/test` `1.62.1`;
- `@axe-core/playwright` `4.13.0`;
- Node 22 en CI;
- Chromium instalado por Playwright.

Configuración:

- screenshots solo al fallar;
- video retenido al fallar;
- trace en primer retry;
- un worker en CI para mantener estado administrativo determinista;
- timeouts acotados;
- report HTML como evidencia.

Las versiones iniciales propuestas durante el scaffolding fueron reemplazadas antes del cierre de QA por versiones estables publicadas y fijadas de forma exacta.

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

## Seguridad / autorización negativa

`tests/integration/assert-admin-security.php` ejecuta sobre un WordPress real instalado desde el ZIP del plugin y verifica:

- Administrator conserva todas las capabilities gestionadas por WLA;
- Administrador inmobiliario puede operar propiedades/settings/actividad pero no recibe Herramientas técnicas;
- Editor de propiedades puede editar/publicar sus propiedades pero no editar propiedades de otros autores ni acceder a Settings/Actividad por conveniencia;
- Gestor de leads no recibe permisos de propiedades o Settings;
- una escritura de la ficha sin nonce no modifica meta;
- un nonce inválido no modifica meta;
- un nonce válido + usuario autorizado sí persiste;
- un Editor con nonce válido no puede modificar la propiedad de otro autor;
- código duplicado se rechaza;
- moneda fuera del contrato se rechaza.

Esto complementa el E2E de acceso directo por URL; ocultar el menú nunca se considera suficiente control de acceso.

## Performance administrativo

`tests/integration/assert-admin-performance.php` crea un catálogo sintético incremental hasta 5.000 propiedades y registra benchmarks para:

- Dashboard con 100, 1.000 y 5.000 propiedades;
- listado administrativo paginado a 20 elementos;
- paginación de Actividad.

Budgets iniciales de regresión CI:

- Dashboard sin Actividad: `<= 5` queries y `< 5 s` por milestone;
- primera página de listado: `<= 4` queries y `< 5 s`;
- Actividad: `<= 2` queries, máximo 30 filas y `< 5 s`.

Estos límites son guards conservadores de CI para detectar regresiones; no son promesas de rendimiento de un hosting productivo.

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

`package.json` usa versiones exactas. El workflow genera además `package-lock.json` y lo incluye en el artifact de evidencia; antes de cerrar PR 2.10 ese lock debe quedar versionado y el workflow migrará a `npm ci`.

## CI agregado

Workflow: `Administration Quality Gate`.

Baseline inicial:

- WordPress 6.6.2;
- PHP 8.1;
- MySQL 8;
- Node 22;
- Chromium.

El workflow:

1. instala tooling Node fijado;
2. instala Chromium;
3. construye el ZIP instalable real;
4. instala WordPress limpio;
5. activa WLA Inmo desde el ZIP;
6. crea taxonomías fixture y un Editor temporal;
7. ejecuta integración negativa de seguridad/autorización;
8. ejecuta benchmark sintético 100/1.000/5.000;
9. levanta WordPress local en el runner;
10. ejecuta Playwright/axe/responsive;
11. conserva report, screenshots/videos/traces, lockfile generado, benchmark y log del servidor.

## Quality Gate pendiente

Todavía deben completarse y registrarse antes de `DONE`:

- resultado final del nuevo workflow y corrección de findings;
- package-lock versionado + `npm ci`;
- revisión manual de accesibilidad/teclado;
- revisión responsive ampliada de pantallas administrativas prioritarias;
- auditoría final de assets condicionales;
- limpieza de copias obsoletas del editor;
- integraciones heredadas completas;
- artifact final y SHA-256;
- squash merge.

## Producción

`propiedadesmartinez.cl` no ha sido modificado.
