# Fases de desarrollo

Este documento es la hoja de ruta auditable de implementación. Cada fase debe cerrar con evidencia, QA y documentación actualizada.

## Fase 0 — Gobierno y diseño

**Objetivo:** congelar principios, alcance y metodología antes de programar.

Entregables:

- requisitos de producto;
- arquitectura;
- modelo de datos;
- stack;
- metodología;
- estrategia de tests;
- secciones del administrador;
- estrategia SEO/GEO/AEO;
- seguridad;
- migración;
- estándares de PR y auditoría.

Salida requerida:

- documentación revisada;
- decisiones críticas registradas;
- backlog de Fase 1.

## Fase 1 — Core del plugin

**Objetivo:** plugin instalable y desacoplado.

Incluye:

- bootstrap `wla-inmo.php`;
- autoloading;
- activación/desactivación;
- requisitos mínimos;
- CPT `wla_property`;
- taxonomías base;
- capabilities y roles;
- esquema de datos/indexación;
- settings básicos;
- hooks públicos iniciales.

Tests obligatorios:

- activación/desactivación;
- registro CPT/taxonomías;
- permisos;
- instalaciones multisite si se declara compatible;
- PHP/WP mínimos soportados;
- no colisión de prefijos/namespaces.

## Fase 2 — Administración de propiedades

**Objetivo:** permitir que un usuario no técnico gestione propiedades sin WooCommerce ni ACF.

Incluye:

- listado administrativo;
- alta/edición;
- validación de campos;
- autosave/revisiones según diseño;
- galería;
- videos;
- ubicación;
- precio y operación;
- estado;
- destacados;
- acciones rápidas y masivas;
- indicadores de ficha incompleta.

Tests:

- crear, editar, duplicar, archivar;
- permisos por rol;
- campos obligatorios;
- sanitización;
- carga multimedia;
- accesibilidad del formulario;
- navegación sin conocimientos técnicos.

## Fase 3 — Importación y exportación masiva

**Objetivo:** XLSX/CSV/JSON seguro, trazable e idempotente.

Incluye:

- upload;
- detección de columnas;
- mapeo;
- validación;
- dry-run;
- actualización por código/external_id;
- importación por lotes;
- manejo de fotos remotas;
- historial;
- reporte de errores;
- exportación.

Tests:

- archivos válidos e inválidos;
- duplicados;
- actualizaciones;
- columnas desconocidas;
- tipos erróneos;
- archivos grandes;
- timeout/memoria;
- URL de imágenes inválidas;
- permisos;
- rollback cuando aplique.

## Fase 4 — Frontend independiente del tema

**Objetivo:** WLA Inmo debe funcionar con cualquier tema compatible.

Incluye:

- templates fallback;
- archive;
- single;
- cards;
- buscador;
- filtros;
- paginación;
- galería;
- mapa;
- CTA/contacto;
- sistema de overrides desde el tema.

Tests:

- tema WLA Inmo Light;
- tema core de WordPress;
- tema de terceros;
- sin Elementor;
- sin WooCommerce;
- responsive;
- teclado;
- rendimiento.

## Fase 5 — WLA Inmo Light

**Objetivo:** tema de referencia ultraligero, opcional.

Incluye:

- header;
- footer;
- home;
- navegación;
- templates;
- `theme.json`;
- tokens visuales;
- accesibilidad;
- CSS/JS mínimo.

Criterio crítico: desactivar WLA Inmo Light no puede romper los datos ni la administración del plugin.

## Fase 6 — SEO, GEO y AEO

**Objetivo:** indexación y comprensión semántica de primer nivel.

Incluye:

- titles/descriptions;
- canonical;
- Open Graph;
- sitemap;
- breadcrumbs;
- schema/JSON-LD;
- páginas de taxonomía útiles;
- control de indexación de filtros;
- datos semánticos de propiedades;
- contenido orientado a respuestas.

Tests:

- HTML meta;
- canonical;
- schema válido;
- URLs;
- sitemap;
- noindex en combinaciones no deseadas;
- duplicación de schema con plugins SEO comunes;
- degradación correcta si otro SEO plugin está activo.

## Fase 7 — Leads e indicadores

Incluye:

- solicitar visita;
- consultas;
- WhatsApp/teléfono;
- almacenamiento de leads;
- email;
- UTM/origen;
- UF, dólar, UTM, euro;
- caché y fallback de indicadores.

Tests:

- CSRF;
- spam/rate limiting;
- validación;
- entrega email;
- caída de API externa;
- privacidad de datos.

## Fase 8 — Seguridad y hardening

Incluye revisión completa de:

- capabilities;
- nonces;
- REST;
- uploads;
- SQL;
- XSS;
- CSRF;
- SSRF en imágenes remotas;
- logs;
- secretos;
- importador;
- formularios públicos.

Salida: checklist de seguridad cerrado o riesgos aceptados y documentados.

## Fase 9 — Migración desde Propiedades Martínez

Incluye:

- inventario WooCommerce/ACF/WPCode;
- dry-run;
- mapping;
- migración de propiedades;
- galerías;
- categorías;
- precios;
- URLs;
- redirects si son necesarios;
- comparación de muestras;
- QA completo;
- rollback.

WooCommerce/Elementor/ACF/WPCode solo se desactivan cuando las evidencias confirman equivalencia funcional.

## Fase 10 — Release 1.0

Requisitos:

- calidad y seguridad aprobadas;
- documentación de usuario completa;
- migración validada;
- performance budget cumplido;
- API pública documentada;
- tests automatizados estables;
- instalación limpia probada;
- upgrade probado;
- auditoría final sin bloqueantes.

## Estado por fase

Cada fase debe registrarse con uno de estos estados:

- `PLANNED`
- `IN_PROGRESS`
- `QA`
- `BLOCKED`
- `DONE`

La evidencia detallada se registra siguiendo `AUDIT-TRACEABILITY.md`.
