# Estrategia de migración

## Objetivo

Migrar el sitio actual hacia WLA Inmo sin perder propiedades, imágenes, URLs, datos comerciales ni posicionamiento.

La migración debe ser reversible durante la fase de transición.

## Principio

**No desactivar WooCommerce, ACF, Elementor, WPCode ni filtros hasta que WLA Inmo haya importado, validado y servido correctamente el catálogo.**

## Fuentes actuales a considerar

El migrador debe poder leer, según disponibilidad:

- productos WooCommerce usados como propiedades;
- precio normal/regular de WooCommerce;
- imagen destacada;
- galería WooCommerce;
- categorías de producto usadas como operación/tipo;
- campos ACF y post meta históricos;
- contenido/descripción del producto;
- códigos de propiedad;
- estado/disponibilidad;
- superficies;
- baños;
- habitaciones;
- estacionamientos;
- piscina;
- calefacción;
- otros;
- ubicación/coordenadas;
- videos;
- destacados/orden de portada.

## Fases

### Fase 0 — Inventario

Generar informe sin modificar datos:

```text
Total productos/propiedades
Total publicadas
Total borradores
Precios encontrados por fuente
Campos ACF detectados
Categorías detectadas
Galerías e imágenes
URLs actuales
Códigos duplicados
Campos vacíos
```

### Fase 1 — Instalar WLA Inmo en paralelo

- Registrar CPT y taxonomías.
- Crear tablas propias.
- Mantener frontend actual sin cambios.

### Fase 2 — Simulación

El migrador debe mostrar por propiedad:

- origen;
- destino;
- conflictos;
- campos que no se pueden mapear automáticamente.

Los conflictos de precio son importantes: si existen varias fuentes con valores distintos, no se debe elegir silenciosamente una fuente sin una regla explícita.

### Fase 3 — Migración de prueba

Migrar una muestra representativa:

- casa;
- departamento;
- terreno;
- venta;
- arriendo;
- propiedad con múltiples fotos;
- propiedad con video;
- propiedad con coordenadas;
- propiedad antigua con campos incompletos.

Comparar visual y técnicamente.

### Fase 4 — Migración completa

Procesar por lotes.

Registrar:

- ID origen;
- ID destino;
- código;
- URL antigua;
- URL nueva;
- estado del proceso;
- media migrada;
- advertencias.

### Fase 5 — Frontend paralelo

Antes del cambio final:

- listado WLA Inmo en URL de prueba/no indexable;
- fichas de prueba;
- buscador;
- formularios;
- Schema;
- responsive;
- rendimiento.

### Fase 6 — Cambio de rutas

Mantener URLs actuales cuando sea viable.

Si una URL cambia:

- crear redirect 301 específico;
- actualizar enlaces internos;
- actualizar canonical;
- verificar sitemap.

Nunca redirigir masivamente todas las fichas a la home.

### Fase 7 — Validación

Checklist:

```text
□ total de propiedades coincide
□ códigos coinciden
□ precios coinciden
□ estados coinciden
□ imágenes principales coinciden
□ galerías coinciden
□ URLs verificadas
□ formularios funcionan
□ páginas indexables correctas
□ canonical correcto
□ Schema válido
□ no hay 404 inesperados
□ rendimiento medido
```

### Fase 8 — Retiro de dependencias

Solo después de aprobación:

1. desactivar componentes WPCode ya incorporados;
2. sustituir filtros externos;
3. retirar Elementor de páginas migradas;
4. retirar ACF si ningún otro módulo lo utiliza;
5. retirar WooCommerce si ningún otro proceso del sitio lo necesita.

Hacerlo de forma escalonada, verificando después de cada cambio.

## Compatibilidad temporal

Durante la transición WLA Inmo puede incluir adaptadores de lectura para WooCommerce/ACF, pero estos deben estar aislados bajo `Migration/Legacy` y no convertirse en dependencia del core.

## Reejecución

La migración debe poder ejecutarse nuevamente de forma controlada usando el mapping origen/destino, evitando duplicados.

## Rollback

Antes del cutover:

- respaldo de base de datos;
- respaldo de uploads;
- export del mapping;
- registro de plugins activos;
- versión del tema.

El plan de rollback debe permitir volver temporalmente al frontend anterior mientras se corrige un problema.

## SEO post-migración

Durante las primeras semanas verificar:

- 404;
- 5xx;
- indexación;
- sitemap;
- canonical;
- redirects;
- metadata;
- datos estructurados;
- enlaces internos;
- Core Web Vitals.