# Evidencia — Fase 2 / PR 2.10 Quality Gate de Administración

Estado documental: `QA_PASSED / MERGE_PENDING`.

Issue: #41  
PR: #42  
Rama: `test/phase2-admin-quality-gate`  
Head funcional validado: `190cdf8787e92c17c715ce195e7620cd55cf704d`

## Objetivo

Cerrar Fase 2 con una auditoría reproducible de la administración completa de WLA Inmo, sin sumar funcionalidades inmobiliarias de fases posteriores y sin modificar producción.

## Baseline auditado

PR 2.1–2.9 están mergeadas. El baseline comprende:

- Admin Shell y navegación;
- listado profesional de Propiedades;
- editor guiado;
- Multimedia y galería;
- Calidad del catálogo;
- Centro de Ayuda/onboarding;
- Ajustes;
- Actividad/Historial operativo;
- Dashboard/Resumen operativo.

## Resultado ejecutivo

El Quality Gate final quedó verde para el head validado.

- Administration Quality Gate `33874413262`: `SUCCESS`;
- Phase 1 CI `33874412820`: `SUCCESS`;
- Bootstrap Smoke `33874412840`: `SUCCESS`;
- Activity Integration `33874412801`: `SUCCESS`;
- Catalogue Quality Integration `33874413102`: `SUCCESS`;
- Settings UI Integration `33874412970`: `SUCCESS`;
- Help Center Integration `33874412781`: `SUCCESS`;
- Dashboard Integration `33874412792`: `SUCCESS`;
- WordPress 6.6.2 + PHP 8.1: `SUCCESS`;
- WordPress latest + PHP 8.3: `SUCCESS`;
- deactivate/uninstall preservation checks: `SUCCESS`.

No quedan findings críticos ni altos abiertos dentro del alcance de Fase 2.

## E2E Playwright

Tooling reproducible:

- `@playwright/test` `1.62.1`;
- `@axe-core/playwright` `4.13.0`;
- Node 22;
- `package-lock.json` versionado;
- instalación mediante `npm ci --ignore-scripts`;
- `npm` audit del job: `0 vulnerabilities`;
- Chromium instalado por Playwright.

Configuración deliberadamente estricta:

- `retries: 0`, por lo que una prueba flaky no puede quedar escondida como aprobada;
- un worker en CI para mantener estado administrativo determinista;
- screenshots solo al fallar;
- video y trace conservados al fallar;
- timeouts acotados.

Resultado final: **8/8 pruebas Playwright aprobadas en 32,3 s, sin reintentos**.

Cobertura:

1. Administrator abre Resumen y sus bloques operativos.
2. Crea una propiedad desde WordPress admin.
3. Completa código, estado, moneda, precio, operación, tipo y comuna.
4. Guarda borrador.
5. Publica.
6. Cambia precio y estado comercial.
7. Revisa Calidad del catálogo.
8. Revisa Actividad.
9. Navega Centro de Ayuda.
10. Editor restringido intenta abrir Ajustes por URL directa y es rechazado.
11. Se prueba interacción por teclado con el disclosure `6. Ubicación`.
12. Se verifica responsive de las pantallas prioritarias en cinco viewports.

## Seguridad / autorización negativa

`tests/integration/assert-admin-security.php` se ejecutó sobre WordPress real instalado desde el ZIP del plugin y quedó `SUCCESS`.

La matriz comprobada incluye:

- **Administrator:** capabilities gestionadas completas;
- **Administrador inmobiliario:** operación de propiedades/settings/actividad sin recibir Herramientas técnicas;
- **Editor de propiedades:** puede operar sus propiedades y publicar según sus capabilities, pero no editar objetos de otros autores ni acceder a Settings/Actividad por conveniencia;
- **Gestor de leads:** capabilities de leads sin permisos implícitos de propiedades o Settings.

Casos negativos comprobados:

- escritura sin nonce no cambia meta;
- nonce inválido no cambia meta;
- nonce válido + usuario autorizado sí persiste;
- Editor con nonce válido no puede modificar una propiedad de otro autor;
- código de propiedad duplicado se rechaza;
- moneda fuera del contrato se rechaza;
- acceso directo a Ajustes por un rol restringido se deniega.

Ocultar un menú no se considera control de acceso: el Quality Gate prueba autorización efectiva sobre la operación y la URL.

## Performance administrativo

`tests/integration/assert-admin-performance.php` crea un catálogo sintético incremental hasta 5.000 propiedades. Resultado final del runner CI:

| Escenario | Queries | Tiempo de referencia CI |
|---|---:|---:|
| Dashboard — 100 propiedades | 5 | 0,0033 s |
| Dashboard — 1.000 propiedades | 5 | 0,0037 s |
| Dashboard — 5.000 propiedades | 5 | 0,0085 s |
| Listado administrativo — catálogo 5k, primera página | 2 | 0,0040 s |
| Actividad paginada | 2 | 0,0011 s |

Budgets de regresión usados:

- Dashboard sin Actividad: `<= 5` queries;
- primera página del listado: `<= 4` queries;
- Actividad: `<= 2` queries y máximo 30 filas.

Los tiempos son **referencias sintéticas del runner de CI**, útiles para detectar regresiones. No constituyen un SLA ni una promesa de rendimiento del hosting productivo.

## Accesibilidad

### Automatizada

La suite ejecuta axe sobre la UI propia de WLA Inmo y bloquea violaciones `serious` y `critical` de las reglas WCAG A/AA soportadas por la versión fijada.

Se cubren componentes propios en:

- Resumen;
- editor guiado;
- Calidad;
- Actividad;
- Ayuda.

La ejecución final quedó verde.

### Teclado y estructura

Se añadió una prueba explícita de teclado para la ficha guiada:

- el `<summary>` de `6. Ubicación` recibe focus;
- `Enter` abre el `<details>`;
- el control Comuna queda visible e interactuable.

La revisión de código/render confirma además el uso de labels asociados, controles HTML nativos, `aria-invalid` para errores, `role="alert"` para mensajes y disclosures semánticos `<details>/<summary>`.

Esta es una revisión automatizada y asistida por código, **no una certificación externa ni una auditoría humana formal de accesibilidad**. Una revisión humana especializada puede añadirse antes de una release comercial si se requiere certificación adicional.

## Responsive

Viewports ejecutados:

- 360×800;
- 390×844;
- 768×1024;
- 1024×768;
- 1440×1000.

La prueba final recorre:

- Resumen;
- Calidad;
- Actividad;
- Ayuda;
- Ajustes;
- editor de propiedad;
- Multimedia;
- listado nativo de Propiedades con columnas WLA.

El criterio para componentes propios es que no exista overflow horizontal global de la UI WLA. Las tablas que necesitan ancho mayor pueden desplazarse dentro de un contenedor local accesible.

## Assets condicionales

Se agregó `tests/smoke/admin-assets.php` y quedó incluido en el Quality Gate general.

Contratos comprobados:

- pantallas WordPress ajenas: sin assets WLA;
- Resumen: `wla-inmo-admin` + Dashboard;
- Ayuda: assets de Help solo en Ayuda;
- Ajustes: CSS de Settings solo en Ajustes;
- Actividad: CSS de Activity donde corresponde;
- listado de Propiedades: base administrativa sin Multimedia;
- editor de Propiedades: base + Activity + Multimedia y una carga de Media Library;
- contextos no se confunden entre sí.

## Findings detectados y corregidos durante QA

### F2Q-001 — Interacción con Comuna dentro de sección cerrada

La primera prueba E2E intentaba seleccionar Comuna mientras `6. Ubicación` estaba plegada. El control existía en DOM pero era invisible.

Clasificación: finding del test, no defecto del producto.  
Corrección: la prueba abre el disclosure como lo haría una persona antes de interactuar y posteriormente se añadió cobertura de teclado.

### F2Q-002 — Flake de login móvil

Una ejecución temprana consiguió pasar mediante retry automático.

Clasificación: confiabilidad del Quality Gate.  
Corrección: `retries` se cambió a `0` y el login espera explícitamente la URL administrativa y `#wpadminbar`. La ejecución final quedó 8/8 sin retry.

### F2Q-003 — Overflow real en Calidad a 360/390 px

La ampliación de la cobertura responsive detectó que la tabla de prioridad de corrección aumentaba el `scrollWidth` del contenedor WLA en viewport pequeño.

Clasificación: UX responsive, prioridad media.  
Corrección: `.table-responsive` ahora contiene el ancho de la tabla y habilita scroll horizontal local mediante `overflow-x:auto`, evitando desbordar toda la pantalla WLA.

La ejecución final en 360 y 390 px quedó verde.

### F2Q-004 — Selector ambiguo del listado

El test usaba una clase de columna presente tanto en `<thead>` como en `<tfoot>`, generando strict-mode por dos coincidencias.

Clasificación: finding del test.  
Corrección: el test apunta a los IDs únicos de cabecera `#wla_code` y `#wla_price`.

### F2Q-005 — Copias obsoletas del editor

Las secciones Multimedia, Calidad e Historial todavía describían PR 2.4/2.5/2.8 como trabajo futuro aunque dichos módulos ya existían.

Clasificación: deuda UX/documental baja.  
Corrección: la ficha ahora describe la funcionalidad vigente — panel Multimedia, Calidad del catálogo e Historial operativo.

## Quality Gate PHP / paquete

Phase 1 CI final `33874412820`:

- PHP syntax: `SUCCESS`;
- WordPress Coding Standards security profile: `SUCCESS`;
- PHPStan: `SUCCESS`, sin errores;
- PHPUnit: **3 tests / 40 assertions**;
- todos los source smoke tests: `SUCCESS`, incluido `conditional admin asset smoke tests`;
- release ZIP smoke: `SUCCESS`;
- WordPress 6.6.2/PHP 8.1: `SUCCESS`;
- WordPress latest/PHP 8.3: `SUCCESS`;
- preservación tras deactivate/uninstall: `SUCCESS`.

## Artifacts y checksums

### Plugin QA

- workflow: `33874412820`;
- artifact: `9937251373`;
- artifact digest: `sha256:a699b9024db2932ee1a59b6940f0e0f7b53b397236831675087593f20a81a1a7`;
- ZIP `wla-inmo-0.1.0-alpha.zip` SHA-256: `cb567d3a5abf320f49fbb238ec308ee64548303b0198e64667632e18876e2581`.

### E2E / administración

- workflow: `33874413262`;
- artifact: `9937305918`;
- artifact digest: `sha256:4aee29b463c856e20b0084d270b8e03fd920f4e9dfbab3bb307112d894b23682`.

El artifact E2E conserva report HTML, resultados disponibles, lockfile, benchmark y log del servidor del runner.

## Warnings externos no bloqueantes

GitHub Actions informa warnings de deprecación Node para algunas actions mantenidas por GitHub/terceros que actualmente se ejecutan sobre Node 24. No corresponden al runtime PHP/JS público de WLA Inmo ni representan un finding del plugin.

Composer todavía informa que el `composer.lock` de tooling PHP no está versionado en el repositorio. Es deuda de tooling ya registrada para resolver antes de Beta; el paquete de producción no necesita Composer en el servidor.

## Criterio de salida

PR 2.10 queda `QA_PASSED / MERGE_PENDING`.

Fase 2 puede declararse `DONE` una vez realizado el squash merge efectivo de PR #42 y registrado su SHA final en `main`. No corresponde reabrir funcionalidades de Administración dentro de este cierre salvo que aparezca un finding crítico/alto nuevo.

## Producción

`propiedadesmartinez.cl` no ha sido modificado. No se instaló este alpha ni se ejecutó migración alguna en producción.
