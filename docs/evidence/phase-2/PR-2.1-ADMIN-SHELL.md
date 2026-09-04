# Evidencia — Fase 2 / PR 2.1 Admin shell y navegación

Estado documental: `QA_PASSED / MERGE_PENDING`.

Issue: #23  
PR: #24  
Rama: `feat/phase2-admin-shell`

## Objetivo

Crear el shell administrativo de WLA Inmo con navegación declarativa, mínimo privilegio, protección por capability también en acceso directo y assets aislados.

## Componentes

- `Admin\\ScreenRegistry` — contrato declarativo de pantallas;
- `Admin\\Menu` — navegación filtrada por capability;
- `Admin\\PageRenderer` — renderer común y Resumen inicial;
- `Admin\\Assets` — CSS cargado únicamente en contextos WLA;
- `Admin\\ContextHelp` — patrón de ayuda contextual;
- `Admin\\Bootstrap` — registro de hooks administrativos;
- `assets/admin/admin.css` — estilos namespaced y responsivos.

## Navegación registrada

El shell reserva las 16 secciones/enlaces documentados: Resumen, Propiedades, Nueva propiedad, Inicio y destacados, Importar/Exportar, Consultas/Leads, Ubicaciones, Clasificaciones, Multimedia, SEO y visibilidad, Indicadores, Calidad del catálogo, Actividad, Ayuda, Herramientas y Ajustes.

Las funciones aún no construidas aparecen únicamente como espacios preparados; no simulan funcionalidad inexistente.

## Integración de menús nativos

`wla_property` usa `show_in_menu = wla-inmo`.

Propiedades y Nueva propiedad permanecen como pantallas nativas de WordPress. `ScreenRegistry` las documenta como `native`, pero `Admin\\Menu` no vuelve a registrarlas. Así se evita duplicar submenús mientras se conservan las capabilities nativas del CPT.

## Seguridad

- no se utiliza `manage_options` como fallback;
- cada pantalla declara una capability concreta;
- `Menu::renderCurrentPage()` vuelve a verificar la capability antes del render;
- acceso directo sin permiso se rechaza con 403;
- Propiedades utiliza capabilities del CPT;
- Herramientas mantiene `manage_wla_inmo_tools`;
- Ajustes mantiene `manage_wla_inmo_settings`;
- no hay escrituras de negocio nuevas;
- no se pasan datos privados a JavaScript;
- los parámetros GET usados para routing/ayuda son exclusivamente de lectura y se sanitizan inmediatamente.

## Performance

- el Resumen todavía no ejecuta consultas de métricas;
- CSS admin únicamente en pantallas WLA / `wla_property`;
- cero JavaScript nuevo;
- cero React;
- cero assets frontend.

## Accesibilidad

La base utiliza headings semánticos, enlaces/botones nativos de WordPress, `aria-label` donde corresponde, layout responsive y navegación sin dependencia de JavaScript.

## QA automático final

### Phase 1 CI heredado

Run final: `33827079706`  
Resultado global: `SUCCESS`.

- Quality Gate / PHP 8.1: SUCCESS;
- WPCS security profile: SUCCESS;
- PHPStan 2.2: SUCCESS;
- PHPUnit: `3 tests / 40 assertions`;
- todos los smoke tests: SUCCESS;
- build ZIP: SUCCESS;
- release ZIP smoke: SUCCESS;
- WordPress `6.6.2` + PHP `8.1`: SUCCESS;
- WordPress `latest` + PHP `8.3`: SUCCESS;
- desactivación/uninstall siguen conservando datos: SUCCESS.

`tests/smoke/admin-shell.php` valida registry, mínimo privilegio, pantallas nativas sin duplicación, acceso directo 403, aislamiento de assets y ayuda contextual.

La integración WordPress real valida que `wla_property` usa `wla-inmo` como parent del menú.

### Bootstrap Smoke

Run final: `33827079713`  
Resultado: `SUCCESS`.

## Artifact final

- Artifact ID: `9920346563`;
- Nombre: `wla-inmo-0.1.0-alpha-quality`;
- Tamaño del contenedor: `59703` bytes;
- Digest: `sha256:79527fccc452b33b2b6c2b269d67ff48d2d6c87f0c5c5d86b0dd9ac869f4fe19`;
- ZIP instalable SHA-256: `f78779284caae48896a1c7f74de5f3d416fcac8eac2540a052ea0938fddfba6f`;
- Expira: `2026-12-03`.

## Historial de findings

### ADMIN-QA-1 — WPCS y GET de routing

El primer run de PR #24 (`33826994472`) llegó al security profile y falló por cuatro advertencias de `NonceVerification` al leer `$_GET['page']` en `Menu` y `ContextHelp`.

Análisis: el parámetro selecciona exclusivamente una ruta/pestaña de ayuda de solo lectura; no ejecuta escritura ni acción privilegiada. Exigir nonce para navegar rompería el patrón normal de páginas administrativas y no aportaría protección CSRF sobre una mutación inexistente.

Corrección: mantener sanitización `wp_unslash()` + `sanitize_key()` y documentar excepciones WPCS **solo en las líneas concretas** de routing de lectura. No se deshabilitó el sniff globalmente.

Resultado: WPCS pasó en el run final.

### ADMIN-UX-1 — posible duplicación de submenús nativos

Durante revisión previa a QA se identificó que registrar manualmente Propiedades/Nueva propiedad mientras el CPT usa `show_in_menu = wla-inmo` podía duplicar los submenús que WordPress genera nativamente.

Corrección: el registry conserva esas rutas como contrato `native`, pero `Admin\\Menu` no las registra nuevamente.

Resultado: arquitectura simplificada y responsabilidad nativa preservada.

## Producción

No afectada. PR #24 no se ha desplegado en `propiedadesmartinez.cl`.

## Cierre

Todos los quality gates aplicables están verdes. PR 2.1 queda `QA_PASSED / MERGE_PENDING` y solo pasará a `DONE` después del squash merge de PR #24.
