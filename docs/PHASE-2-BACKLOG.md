# Fase 2 — Administración

Estado: `QA_PASSED / MERGE_PENDING`  
Dependencia: Fase 1 `DONE`  
Versión de entrada: `0.1.0-alpha`

## Objetivo

Construir una administración inmobiliaria propia, rápida, segura y entendible por personas con conocimientos básicos de WordPress, reutilizando el modelo de datos, roles, capabilities, settings e índice definidos en Fase 1.

La interfaz debe hablar en lenguaje de negocio. Un usuario no debería necesitar conocer CPT, postmeta, taxonomías, hooks o tablas SQL para crear y mantener una propiedad.

## Principios UX

- acciones principales visibles y predecibles;
- ayuda contextual junto al campo o decisión relevante;
- formularios divididos por secciones lógicas;
- validaciones explicadas en lenguaje humano;
- no ocultar errores técnicos importantes, pero traducirlos a una acción concreta;
- evitar pantallas sobrecargadas;
- keyboard-first y WCAG 2.2 AA como objetivo de producto;
- mobile/tablet usable para tareas operativas, aunque desktop sea el contexto principal;
- no introducir React como framework global si PHP + JS ligero resuelve el flujo;
- no cargar assets de WLA Inmo fuera de sus propias pantallas salvo necesidad demostrada.

## Resultado de implementación

| PR | Alcance | GitHub | Estado |
|---|---|---|---|
| 2.1 | Admin shell, navegación y screen registry | #24 | DONE |
| 2.2 | Listado profesional de Propiedades | #26 | DONE |
| 2.3 | Editor guiado de Propiedad | #28 | DONE |
| 2.4 | Multimedia y galería | #30 | DONE |
| 2.5 | Calidad del catálogo | #32 | DONE |
| 2.6 | Centro de Ayuda y ayuda contextual | #34 | DONE |
| 2.7 | Ajustes UI | #36 | DONE |
| 2.8 | Actividad e historial administrativo base | #38 | DONE |
| 2.9 | Dashboard / Resumen operativo | #40 | DONE |
| 2.10 | Quality Gate de Administración | #42 | QA_PASSED / MERGE_PENDING |

La evidencia detallada vive en `docs/evidence/phase-2/` y el estado ejecutivo en `docs/PROJECT-STATUS.md`.

## Alcance entregado

### Admin shell y navegación

- menú superior `WLA Inmo`;
- registro declarativo de pantallas;
- capability por pantalla;
- Resumen como landing;
- Propiedades/Nueva propiedad mediante pantallas nativas WordPress;
- assets namespaced y condicionales;
- acceso directo por URL protegido por capability.

### Listado profesional

Columnas y lectura operativa para miniatura, código, título, operación, tipo, ubicación, precio, estado, destacada, calidad y actualización, con filtros y búsqueda sin reutilizar el índice público para borradores.

### Editor guiado

Secciones de negocio:

1. Estado de publicación
2. Identificación
3. Precio
4. Superficies
5. Características
6. Ubicación
7. Descripción
8. Multimedia
9. Contacto
10. SEO / GEO / AEO
11. Calidad
12. Historial

La escritura propia usa nonce, autorización por objeto, MetaSchema, Sanitizer y Validator. El código duplicado se previene y la dirección privada queda explícitamente separada.

### Multimedia

- Media Library nativa;
- imagen principal WordPress;
- galería ordenable;
- ALT según permisos;
- videos como URLs validadas;
- desasociar no borra físicamente attachments;
- sin HTML/iframe arbitrario como valor canónico.

### Calidad del catálogo

- proyección administrativa propia;
- score de completitud explicable, no ranking Google;
- checks accionables;
- filtros y pantalla de prioridad de corrección;
- rebuild seguro.

### Centro de Ayuda

- artículos locales y versionados;
- búsqueda, FAQ y glosario;
- onboarding por usuario;
- ayuda contextual;
- módulos futuros claramente marcados como próximos.

### Ajustes

- ocho pestañas;
- settings sanitizados;
- contacto/privacidad/retención;
- `property_base` con estado pendiente y aplicación controlada de rewrites;
- sin `flush_rewrite_rules()` en cada request.

### Actividad e historial

- tabla versionada con contexto allowlisted;
- eventos relevantes de negocio;
- historial por propiedad;
- retención configurable y limpieza por lotes;
- sin secretos, cookies, nonces, IP/user-agent ni campos privados innecesarios.

### Dashboard

- excepciones y trabajo pendiente primero;
- métricas de catálogo;
- distribuciones accesibles sin librería gráfica;
- actividad reciente bounded;
- acciones rápidas por capability;
- presupuesto base de cinco queries sin Actividad.

## Matriz de permisos verificada

### Administrator

Acceso completo según capabilities instaladas.

### Administrador inmobiliario

Acceso operativo a propiedades, settings permitidos y actividad, sin recibir Herramientas técnicas por conveniencia.

### Editor de propiedades

Puede operar sus propiedades según capabilities, pero no editar objetos de otros autores ni acceder a settings/actividad sensibles.

### Gestor de leads

No recibe permisos de propiedades/settings por el mero hecho de aparecer en el ecosistema WLA.

La matriz fue verificada positiva y negativamente en PR 2.10, incluyendo autorización por objeto con nonce válido.

## Seguridad de salida

PR 2.10 verificó:

- nonce ausente e inválido;
- capability ausente;
- autorización sobre objeto ajeno;
- código duplicado;
- dominio de moneda inválido;
- acceso directo por URL;
- assets condicionales;
- no exposición deliberada de datos privados en el Dashboard;
- regresión de WPCS/PHPStan/smoke tests.

## Performance de salida

Benchmark sintético final del runner CI:

- Dashboard 100: 5 queries / 0,0033 s;
- Dashboard 1.000: 5 queries / 0,0037 s;
- Dashboard 5.000: 5 queries / 0,0085 s;
- listado con catálogo 5k: 2 queries / 0,0040 s;
- Actividad: 2 queries / 0,0011 s.

Son referencias sintéticas para detectar regresiones, no SLA de producción.

## Accesibilidad y responsive de salida

- axe sobre UI propia en flujos prioritarios, sin findings serious/critical en la ejecución final;
- prueba de teclado focus + Enter sobre disclosure del editor;
- labels/controles HTML nativos/mensajes accesibles revisados en código/render;
- viewports 360, 390, 768, 1024 y 1440 px;
- Resumen, Calidad, Actividad, Ayuda, Ajustes, Editor, Multimedia y listado incluidos en la revisión responsive;
- finding real de overflow en Calidad 360/390 corregido mediante scroll local de la tabla.

La evidencia constituye QA automatizado y revisión asistida por código, no una certificación humana externa de accesibilidad.

## Quality Gate de salida

Head funcional validado: `190cdf8787e92c17c715ce195e7620cd55cf704d`.

- Administration Quality Gate `33874413262`: SUCCESS;
- Playwright: 8/8 SUCCESS con `retries=0`;
- Phase 1 CI `33874412820`: SUCCESS;
- integraciones heredadas de Calidad, Ayuda, Settings, Actividad, Dashboard y Bootstrap: SUCCESS;
- artifact plugin `9937251373`;
- ZIP SHA-256 `cb567d3a5abf320f49fbb238ec308ee64548303b0198e64667632e18876e2581`;
- artifact E2E `9937305918`;
- cero findings críticos/altos abiertos conocidos.

Fase 2 pasa formalmente a `DONE` únicamente después del squash merge efectivo de PR #42 y registro del SHA final en `main`.

## Fuera de alcance de Fase 2

- importación/exportación XLSX/CSV/JSON completa — Fase 3;
- frontend público final — Fase 4;
- WLA Inmo Light — Fase 5;
- SEO/GEO/AEO completo — Fase 6;
- leads e indicadores reales — Fase 7;
- security hardening final global — Fase 8;
- migración Propiedades Martínez — Fase 9.

## Producción

`propiedadesmartinez.cl` permanece sin cambios. Fase 2 se desarrolló y validó fuera de producción.
