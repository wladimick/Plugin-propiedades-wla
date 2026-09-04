# Evidencia — Fase 2 / PR 2.9 Dashboard / Resumen operativo

Estado documental: `IN_PROGRESS / QA_PENDING`.

Issue: #39  
Rama: `feat/phase2-operational-dashboard`

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

El snapshot sin actividad está diseñado para cinco consultas principales independientemente del tamaño del catálogo:

1. conteos por estado WordPress;
2. distribución por meta comercial/destacadas;
3. distribución por operación;
4. resumen de Calidad;
5. propiedades que necesitan atención.

Con actividad reciente se agrega una consulta bounded adicional.

La integración crea además 100 propiedades sintéticas y verifica que el snapshot mantenga el mismo presupuesto de consultas en lugar de escalar linealmente en número de queries.

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

## Finding preventivo corregido durante desarrollo

La primera implementación del conteo administrativo utilizaba el estado WordPress `publish` pero el contrato del snapshot expone la clave `published`. Antes de abrir la PR se corrigió el mapeo explícito `publish → published` y se añadió una aserción de integración para evitar regresión.

## Producción

`propiedadesmartinez.cl` no ha sido modificado.

## Cierre pendiente

Antes de pasar PR 2.9 a `DONE` deben registrarse:

- PR final;
- Quality Gate;
- Dashboard Integration en ambas matrices;
- integraciones heredadas relevantes;
- artifact y checksum;
- findings/correcciones;
- squash merge y SHA final en `main`.
