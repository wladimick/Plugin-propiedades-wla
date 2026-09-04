# Evidencia — Fase 2 / PR 2.1 Admin shell y navegación

Estado documental: `IN_PROGRESS`.

Issue: #23  
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

El shell reserva las 16 secciones/enlaces documentados:

1. Resumen;
2. Propiedades;
3. Nueva propiedad;
4. Inicio y destacados;
5. Importar / Exportar;
6. Consultas / Leads;
7. Ubicaciones;
8. Clasificaciones;
9. Multimedia;
10. SEO y visibilidad;
11. Indicadores;
12. Calidad del catálogo;
13. Actividad;
14. Ayuda;
15. Herramientas;
16. Ajustes.

Las funciones aún no construidas aparecen solo como espacios preparados y no simulan funcionalidad inexistente.

## Seguridad

- no se utiliza `manage_options` como fallback;
- cada pantalla declara una capability concreta;
- `Menu::renderCurrentPage()` vuelve a verificar la capability antes de renderizar;
- acceso directo sin permiso se rechaza;
- Propiedades utiliza las capabilities del CPT;
- Herramientas mantiene `manage_wla_inmo_tools`;
- Ajustes mantiene `manage_wla_inmo_settings`;
- no hay escrituras de negocio nuevas en esta PR;
- no se pasan datos privados a JavaScript.

## Integración del CPT

`wla_property` cambia `show_in_menu` desde un top-level independiente a `wla-inmo`, manteniendo intactas sus capabilities, rutas, REST y fuente de verdad.

## Performance

- no se ejecutan consultas de métricas en el Resumen todavía;
- el CSS admin se carga solo en pantallas WLA o pantallas `wla_property`;
- no hay JavaScript nuevo;
- no hay React;
- no hay assets frontend.

## Accesibilidad

La base utiliza headings semánticos, enlaces/botones nativos de WordPress, `aria-label` donde corresponde, layout responsive y no depende de JavaScript para navegar.

## Tests definidos

`tests/smoke/admin-shell.php` verifica:

- 16 slugs únicos;
- capabilities de Dashboard/Propiedades/Settings/Tools;
- propiedad anidada bajo WLA Inmo;
- menú visible para Editor según mínimo privilegio;
- ausencia de Settings/Tools/Import para Editor;
- acceso directo sin capability → 403;
- assets no cargan en pantallas WordPress ajenas;
- assets sí cargan en propiedades;
- ayuda contextual en propiedades.

La integración WordPress real extiende `assert-active.php` para comprobar que el CPT utiliza el parent admin `wla-inmo`.

## Producción

No afectada. Esta rama no se ha desplegado en `propiedadesmartinez.cl`.

## Cierre

Completar con número de PR, CI final, artifact/digest y squash merge después del QA.
