# SEO, GEO y AEO

## Objetivo

WLA Inmo debe producir contenido técnicamente sólido para buscadores tradicionales y fácilmente interpretable por sistemas generativos y asistentes.

SEO, GEO y AEO deben partir del modelo de datos real de cada propiedad.

## SEO técnico

### URLs

Base sugerida:

```text
/propiedades/
/propiedades/casa-boldo-curico-cod-001254/
```

Páginas útiles adicionales:

```text
/propiedades/venta/
/propiedades/arriendo/
/propiedades/tipo/casa/
/propiedades/tipo/terreno/
/propiedades/comuna/curico/
```

No se deben indexar automáticamente combinaciones arbitrarias de filtros.

### Metadata

Cada propiedad debe tener:

- `<title>`.
- meta description.
- canonical.
- Open Graph.
- Twitter metadata si corresponde.
- robots index/noindex configurable.

Los valores automáticos deben poder sobrescribirse manualmente.

### Sitemap

El plugin debe registrar sus propiedades en sitemap y excluir:

- borradores;
- propiedades no indexables;
- contenido eliminado;
- URLs de filtros no canónicas.

### Breadcrumbs

Ejemplo:

```text
Inicio > Propiedades > Venta > Curicó > Casa / Boldo / Curicó
```

Debe existir markup semántico y JSON-LD coherente.

## Datos estructurados

La implementación final debe validarse contra los tipos Schema.org vigentes al momento de desarrollo.

Conceptualmente el grafo relacionará:

```text
Organization / RealEstateAgent
        │
        ├── WebSite
        └── Listing / Property
                ├── Offer
                ├── Place
                ├── PostalAddress
                ├── GeoCoordinates
                ├── ImageObject
                └── BreadcrumbList
```

Reglas:

- Nunca inventar valores.
- No marcar como visible un dato que el sitio oculta por privacidad.
- No duplicar varios grafos contradictorios.
- Precios y monedas deben coincidir con la ficha.

## GEO — Generative Engine Optimization

La propiedad debe ser entendible sin depender de una descripción larga.

Bloque estructurado recomendado:

```text
Tipo: Terreno
Operación: Venta
Comuna: Curicó
Sector: Boldo
Superficie: 1.610 m²
Precio: $390.000.000
Estado: Disponible
Código: 001254
```

Esto debe existir como HTML visible cuando tenga utilidad para el usuario y como datos estructurados coherentes.

### Entidades

El contenido debe dejar claras las relaciones entre:

- inmobiliaria;
- propiedad;
- ubicación;
- operación;
- precio;
- características;
- contacto.

### Actualización

Mostrar una fecha de actualización/verificación cuando sea útil ayuda a expresar vigencia comercial.

No se debe afirmar disponibilidad reciente si el dato no ha sido validado.

## AEO — Answer Engine Optimization

La ficha debe responder preguntas frecuentes usando datos reales.

Ejemplos:

- ¿Cuál es el precio?
- ¿Dónde está ubicada?
- ¿Cuántos metros cuadrados tiene?
- ¿Cuántos dormitorios tiene?
- ¿Está disponible?
- ¿Cómo solicitar una visita?

Estas respuestas pueden presentarse como un bloque de información rápida. Solo usar FAQ estructurado si el contenido visible y las directrices vigentes lo justifican.

## Páginas locales

Se pueden generar páginas como:

```text
Propiedades en Curicó
Casas en venta en Curicó
Terrenos en Curicó
```

Solo deben indexarse si tienen valor real:

- inventario suficiente;
- texto editorial útil;
- enlaces internos;
- contenido único;
- intención de búsqueda clara.

No generar miles de páginas vacías o casi idénticas.

## Calidad de contenido

El administrador mostrará recomendaciones, no promesas de posicionamiento.

Checklist sugerido:

```text
✓ título claro
✓ código
✓ ubicación
✓ precio o precio a consultar
✓ operación
✓ tipo
✓ descripción
✓ 5+ imágenes
✓ alt text relevante
✓ meta description
✓ URL limpia
✓ fecha de verificación
```

## Rendimiento y SEO

- HTML server-side.
- Evitar contenido esencial dependiente de JS.
- LCP optimizado en imagen principal.
- `srcset` y tamaños adecuados.
- lazy loading bajo el primer viewport.
- CSS crítico reducido.
- evitar scripts globales innecesarios.

## Integración con plugins SEO

WLA Inmo debe ser capaz de funcionar solo en lo esencial, pero evitar conflictos con plugins SEO conocidos.

Si existe un plugin SEO externo:

- detectar integración disponible;
- no duplicar title/canonical/OG;
- aportar Schema inmobiliario solo donde sea necesario;
- documentar claramente qué capa controla cada dato.

## Métricas de calidad

El dashboard puede medir cobertura, por ejemplo:

- porcentaje con meta description;
- porcentaje con ubicación completa;
- porcentaje con imágenes y alt text;
- propiedades sin precio;
- propiedades no verificadas recientemente;
- URLs no indexables por decisión editorial.

El score interno es una guía, no un factor de ranking.