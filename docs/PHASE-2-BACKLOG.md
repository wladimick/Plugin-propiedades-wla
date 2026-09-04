# Fase 2 — Administración

Estado: `PLANNED / ENTRY APPROVED`  
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
- keyboard-first y WCAG 2.2 AA;
- mobile/tablet usable para tareas operativas, aunque desktop sea el contexto principal;
- no introducir React como framework global si PHP + JS ligero resuelve el flujo;
- no cargar assets de WLA Inmo fuera de sus propias pantallas salvo necesidad demostrada.

## Orden de PR

### PR 2.1 — Admin shell, navegación y screen registry

Objetivo: crear la estructura administrativa sin implementar aún cada módulo completo.

Incluye:

- menú superior `WLA Inmo`;
- registro declarativo de pantallas;
- capability por pantalla;
- Resumen como landing del plugin;
- enlaces a Propiedades y Nueva propiedad;
- placeholders controlados para módulos futuros;
- patrón de notices/mensajes;
- patrón de ayuda contextual;
- assets namespaced y cargados solo en pantallas WLA;
- tests de visibilidad por rol/capability;
- ninguna pantalla accesible solo por conocer la URL si falta capability.

### PR 2.2 — Listado profesional de Propiedades

Objetivo: transformar el listado de `wla_property` en una herramienta operativa.

Columnas previstas:

- miniatura;
- código;
- título;
- operación;
- tipo;
- ubicación;
- precio principal;
- estado comercial;
- destacada;
- calidad/completitud;
- actualización.

Filtros:

- operación;
- estado;
- tipo;
- región/comuna;
- destacadas;
- incompletas;
- sin precio;
- sin imágenes.

Búsqueda ampliada por código, título y campos aprobados, sin consultas N+1.

### PR 2.3 — Editor guiado de Propiedad

Objetivo: reemplazar la experiencia técnica de metaboxes dispersos por una ficha organizada.

Secciones:

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

Reglas:

- nonce y capability en toda escritura propia;
- usar `MetaSchema`, `Sanitizer` y `Validator`, sin segunda lógica paralela;
- errores asociados al campo y resumen accesible;
- drafts permitidos aunque falten datos de calidad;
- código duplicado prevenido antes de guardar cuando sea posible;
- dirección privada claramente marcada como privada.

### PR 2.4 — Multimedia y galería

Objetivo: hacer simple la gestión visual de cada propiedad usando Media Library nativa.

Incluye:

- imagen principal;
- galería ordenable y accesible;
- selección múltiple desde Media Library;
- eliminar/reordenar sin borrar el attachment accidentalmente;
- contador y advertencias de imágenes;
- ALT visible/editable según permisos;
- video URLs con validación existente;
- no aceptar iframe/HTML arbitrario como valor canónico.

### PR 2.5 — Calidad del catálogo

Objetivo: convertir completitud/calidad en una guía accionable, no en un score decorativo.

Checks iniciales:

- código;
- operación;
- tipo;
- precio o precio a consultar;
- ubicación;
- superficie;
- descripción;
- imagen principal;
- cantidad mínima recomendada de imágenes;
- ALT;
- última verificación;
- SEO mínimo cuando el módulo exista.

Entregables:

- score interno explicable;
- reasons/checks individuales;
- filtros de listado;
- panel `Calidad del catálogo`;
- links directos a corregir.

El score no debe presentarse como factor de ranking de Google.

### PR 2.6 — Centro de Ayuda y ayuda contextual

Objetivo: permitir que una persona no técnica aprenda dentro del producto.

Artículos mínimos:

- primeros pasos;
- crear una propiedad;
- actualizar precio;
- cambiar disponibilidad;
- fotografías y galería;
- videos;
- destacar una propiedad;
- conceptos de ubicación privada/pública;
- preparación para carga masiva;
- errores frecuentes;
- SEO básico de una propiedad;
- preguntas frecuentes.

Incluye:

- buscador simple de ayuda;
- enlaces contextuales desde editor/listado;
- glosario;
- contenido versionable dentro del repo;
- no depender de un sitio externo para la ayuda esencial.

### PR 2.7 — Ajustes UI

Objetivo: exponer el contrato `wla_inmo_settings` con UX segura.

Pestañas iniciales:

- General;
- Propiedades;
- Contacto;
- SEO (preparación/placeholder funcional mínimo si Fase 6 aún no existe);
- Integraciones;
- Rendimiento;
- Privacidad;
- Avanzado.

Cambios de `property_base` deben advertir sobre rewrites y ejecutarse mediante una operación controlada; nunca hacer `flush_rewrite_rules()` en cada request.

### PR 2.8 — Actividad e historial administrativo base

Objetivo: establecer la bitácora que fases posteriores reutilizarán.

Eventos iniciales:

- propiedad creada;
- cambios de precio;
- cambios de estado;
- destacado activado/desactivado;
- cambios de ajustes;
- futuras importaciones podrán anexar batch/origen.

No registrar secretos, cookies, nonces ni contenido sensible innecesario.

### PR 2.9 — Dashboard/Resumen operativo

Objetivo: completar la portada administrativa una vez que existan datos confiables de calidad y actividad.

Indicadores:

- total propiedades;
- venta/arriendo;
- estados comerciales;
- destacadas;
- nuevas/actualizadas;
- calidad del catálogo;
- sin precio/fotos/ubicación;
- acciones rápidas.

Priorizar tareas y excepciones sobre gráficos decorativos.

### PR 2.10 — Quality Gate de Administración

Incluye:

- smoke/unit/integration de capacidades;
- E2E con Playwright para flujos críticos;
- accesibilidad automática + revisión manual documentada;
- responsive admin;
- performance de listado/editor con catálogo sintético;
- pruebas negativas de nonce/capability;
- verificación de assets condicionales;
- actualización de evidencias;
- artifact alpha actualizado.

## Matriz de permisos esperada

### Administrator

Acceso completo a pantallas WLA según capabilities instaladas.

### Administrador inmobiliario

Acceso operativo a propiedades, destacados, import/export futuro, leads futuro, SEO y settings permitidos; herramientas técnicas reservadas continúan restringidas por capability.

### Editor de propiedades

Puede crear/editar/publicar sus propiedades y multimedia y asignar términos existentes. No puede administrar taxonomías, settings sensibles, imports, SEO global, leads o herramientas técnicas.

### Gestor de leads

Durante Fase 2 solo debe ver las pantallas que correspondan a sus capabilities existentes. No recibe permisos de propiedades por conveniencia del menú.

## Seguridad mínima por PR

Toda PR administrativa que escriba datos debe demostrar:

- capability exacta;
- nonce/CSRF protection;
- sanitización;
- validación de dominio;
- escaping tardío;
- protección contra IDOR mediante autorización sobre el objeto;
- no confiar en campos ocultos como control de acceso;
- no exponer datos privados en HTML/JS por comodidad;
- pruebas negativas.

## Performance budget administrativo

Metas iniciales:

- assets del admin solo en pantallas WLA;
- evitar consultas por fila en listados;
- paginación server-side;
- no traer galerías completas para construir listados;
- acciones masivas por lotes cuando corresponda;
- editor sin requests externos críticos durante render;
- medir con catálogo sintético creciente antes del cierre de fase.

## Tests mínimos para cerrar Fase 2

- cada menú/pantalla respeta capabilities;
- acceso directo por URL también respeta capabilities;
- crear borrador;
- publicar propiedad válida;
- editar precio y estado;
- rechazo de datos inválidos;
- prevención/explicación de código duplicado;
- dirección privada no filtrada en outputs públicos;
- galería seleccionable/reordenable;
- calidad explica qué falta;
- ayuda accesible desde editor;
- settings sanitizados;
- cambios críticos auditables;
- assets no cargan globalmente;
- teclado/focus/labels adecuados;
- no regressions en Phase 1 CI.

## Fuera de alcance

Fase 2 NO implementa todavía:

- importación XLSX/CSV/JSON completa — Fase 3;
- frontend final — Fase 4;
- WLA Inmo Light — Fase 5;
- SEO/GEO/AEO completo — Fase 6;
- leads reales e indicadores — Fase 7;
- security hardening final — Fase 8;
- migración Propiedades Martínez — Fase 9.

Puede crear contratos/espacios de UI necesarios para esas fases, pero no duplicar su lógica anticipadamente.

## Quality Gate de salida

Fase 2 pasa a `DONE` solamente si:

1. PR 2.1–2.10 aplicables están mergeadas con evidencia;
2. CI/E2E relevante está verde;
3. no hay findings críticos/altos abiertos del alcance;
4. la matriz de permisos está verificada positiva y negativamente;
5. los principales flujos son utilizables por una persona no técnica;
6. accesibilidad y performance tienen evidencia;
7. `PROJECT-STATUS.md` está actualizado;
8. producción continúa sin cambios salvo que exista una solicitud explícita posterior.
