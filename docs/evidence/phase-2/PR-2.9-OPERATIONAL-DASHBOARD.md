# Evidencia — Fase 2 / PR 2.9 Dashboard / Resumen operativo

Estado documental: `DONE`.

Issue: #39  
PR: #40  
Rama: `feat/phase2-operational-dashboard`  
Head funcional validado: `d0e7f7db675bad568b9f747599c0eb5a607d14d9`  
Squash merge en `main`: `bcff7e17eeda5122d6845c3cc38f14a71d04b57c`

## Objetivo

Convertir `WLA Inmo → Resumen` en una portada operativa basada en datos reales del catálogo, orientada a excepciones, trabajo pendiente y acciones rápidas, sin gráficos decorativos ni dependencias frontend adicionales.

## Arquitectura

Se incorpora `Dashboard\Snapshot` como capa de lectura administrativa.

Principios aplicados:

- no crea estado canónico nuevo;
- no usa el índice público para contar borradores o pendientes;
- usa consultas agregadas y bounded;
- reutiliza la proyección `Quality` para completitud y excepciones;
- usa `Activity\Repository::recent()` para actividad compacta sin ejecutar el `COUNT` de una paginación;
- no carga galerías completas ni contenido de todas las propiedades;
- no introduce caché antes de medir necesidad real.

## Resumen operativo

La pantalla incluye:

1. **Necesita atención**
   - incompletas;
   - sin precio;
   - sin imagen principal;
   - sin ubicación suficiente;
   - sin verificación registrada;
   - hasta 6 propiedades de menor calidad con enlaces de edición según permiso.

2. **Estado del catálogo**
   - total administrado;
   - publicadas;
   - borradores + pendientes;
   - destacadas;
   - calidad promedio;
   - actualizadas durante los últimos 7 días.

3. **Distribuciones**
   - operaciones;
   - estados comerciales;
   - HTML/CSS accesible, sin librería de gráficos.

4. **Actividad reciente**
   - visible únicamente con `view_wla_inmo_activity`;
   - máximo 6 eventos;
   - actor y propiedad se resuelven por lote;
   - sin contexto privado.

5. **Acciones rápidas**
   - Nueva propiedad;
   - Propiedades;
   - Calidad;
   - Actividad;
   - Ayuda;
   - Ajustes;
   - cada acción se muestra solo si la capability correspondiente lo permite.

## Privacidad

`Dashboard\Snapshot` no referencia ni selecciona:

- `private_address`;
- `internal_notes`;
- `external_id`;
- email/teléfono/WhatsApp del negocio;
- payloads de formularios;
- datos de leads.

El release smoke bloquea regresiones sobre estos campos dentro del módulo Dashboard.

## Performance

El snapshot sin actividad usa cinco consultas principales independientemente del tamaño del catálogo:

1. conteos por estado WordPress;
2. distribución por meta comercial/destacadas;
3. distribución por operación;
4. resumen de Calidad;
5. propiedades que necesitan atención.

Con actividad reciente se agrega una consulta bounded adicional.

La integración crea además 100 propiedades sintéticas y verifica que el snapshot mantenga un presupuesto de `<= 5` queries sin actividad, en lugar de escalar linealmente con el número de propiedades.

## UX / accesibilidad

- orden de lectura: atención → catálogo → distribución → actividad → acciones;
- cifras siempre acompañadas por texto;
- barras de distribución poseen `aria-label` con nombre y porcentaje;
- no se depende exclusivamente de color;
- enlaces y acciones usan HTML nativo;
- layout responsive para pantallas administrativas pequeñas;
- CSS cargado únicamente en la pantalla Resumen.

## Tests incorporados

- `tests/smoke/dashboard.php`;
- `tests/integration/assert-dashboard.php`;
- workflow `Dashboard Integration` en WordPress 6.6.2/PHP 8.1 y latest/PHP 8.3;
- release ZIP smoke requiere `Dashboard\Snapshot`, `Admin\DashboardPage` y `dashboard.css`;
- guards de privacidad, ausencia de Chart.js/scripts inline, ausencia de requests remotos y separación del índice público.

## QA final

Head funcional validado: `d0e7f7db675bad568b9f747599c0eb5a607d14d9`.

- Phase 1 CI `33865163656`: `SUCCESS`;
- Quality Gate / PHP 8.1: `SUCCESS`;
- PHP syntax: `SUCCESS`;
- WordPress Coding Standards security profile: `SUCCESS`;
- PHPStan: `SUCCESS`;
- PHPUnit: `3 tests / 40 assertions`;
- todos los smoke tests, incluido `operational dashboard`: `SUCCESS`;
- release ZIP smoke: `SUCCESS`;
- WordPress 6.6.2 + PHP 8.1: `SUCCESS`;
- WordPress latest + PHP 8.3: `SUCCESS`;
- deactivate/uninstall preservation checks: `SUCCESS`;
- Dashboard Integration `33865163644`: `SUCCESS` en ambas matrices;
- Catalogue Quality Integration `33865163488`: `SUCCESS`;
- Help Center Integration `33865163670`: `SUCCESS`;
- Settings UI Integration `33865163568`: `SUCCESS` en ambas matrices;
- Activity Integration `33865163516`: `SUCCESS` en ambas matrices;
- Bootstrap Smoke `33865163647`: `SUCCESS`;
- Artifact `9933662105`;
- Artifact digest: `sha256:0e55f530c2a0030b61abc0bf690ca8ebce6ed8be8c465f037c9fcb20ede0d13b`;
- ZIP SHA-256: `690d78dfe8af14ebb465ef7ac1b5f1a69d44c854eff2180e480e95a212eea04b`.

## Findings corregidos

1. Durante el desarrollo se detectó que el estado canónico de WordPress es `publish`, mientras el contrato del snapshot expone `published`. Se corrigió el mapeo explícito `publish → published` y se añadió una aserción de integración para evitar regresión.
2. El primer Quality Gate de PR #40 falló únicamente porque el smoke heredado de Admin Shell esperaba un solo stylesheet en la portada. PR 2.9 agrega deliberadamente un segundo stylesheet, `wla-inmo-dashboard`, limitado al Resumen. El smoke fue actualizado para validar ambos handles y el Quality Gate final quedó verde.

No quedan findings críticos ni altos abiertos dentro del alcance de PR 2.9. Los warnings Node observados en GitHub Actions pertenecen a actions de terceros/GitHub y no al runtime de WLA Inmo.

## Producción

`propiedadesmartinez.cl` no ha sido modificado.

## Cierre

PR 2.9 queda `DONE` después del squash merge de PR #40 en `bcff7e17eeda5122d6845c3cc38f14a71d04b57c`. La siguiente actividad de Fase 2 es PR 2.10 — Quality Gate de Administración.
