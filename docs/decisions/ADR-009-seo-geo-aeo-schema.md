# ADR-009 — SEO, GEO, AEO y Schema

Estado: accepted  
Fecha: 2026-09-03  
Decisiones: D48–D53  
Issue: #2

## Contexto

WLA Inmo debe rendir bien en buscadores tradicionales y ser interpretable por motores generativos sin depender obligatoriamente de un plugin SEO externo.

## Decisión

- Proveer SEO esencial propio: title, description, canonical, Open Graph, sitemap y salida estructurada necesaria.
- Detectar/adaptarse a plugins SEO compatibles para evitar duplicados o conflictos.
- Emitir un grafo Schema coherente y basado solo en datos reales/visibles.
- Crear páginas locales/GEO solo cuando tengan inventario y contenido útil; no generar thin content masivo.
- Combinaciones arbitrarias de filtros no serán indexables por defecto.
- AEO se implementará mediante información visible y respuestas derivadas de datos reales de la propiedad.
- Datos privados nunca se publican en HTML, Schema ni API.

## Alternativas consideradas

- Exigir Yoast/Rank Math: añade dependencia y no garantiza semántica inmobiliaria.
- Generar miles de landings automáticas: riesgo alto de contenido pobre/duplicado.
- FAQ/Schema artificial: puede crear contradicciones y mala calidad.

## Consecuencias

### Positivas
- Funciona bien en instalación limpia.
- Capa inmobiliaria semántica consistente.
- Mejor control de indexación.

### Trade-offs
- Se requieren adapters de compatibilidad con plugins SEO populares.
- Las reglas de Schema deben revisarse cuando cambien estándares/directrices.

## Impacto

- SEO/GEO/AEO: parte del modelo, no parche posterior.
- Performance: contenido crítico server-side.
- Datos: precio, estado y ubicación deben coincidir en todas las salidas.

## Revisión futura

Validar tipos Schema.org y políticas de motores de búsqueda antes de cada release importante.