# ADR-004 — Contrato plugin/tema y frontend

Estado: accepted  
Fecha: 2026-09-03  
Decisiones: D20–D26  
Issue: #2

## Contexto

WLA Inmo debe funcionar con cualquier tema y WLA Inmo Light debe ser opcional.

## Decisión

- Plugin = datos, lógica, consultas, API de presentación y templates fallback.
- Tema = identidad visual, layout global, header/footer y composición.
- El plugin incluye templates neutrales usables de inmediato.
- Resolución de templates: tema hijo → tema activo → plugin.
- Overrides bajo `wla-inmo/`.
- Gutenberg se integrará progresivamente, sin ser requisito del core.
- Shortcodes existen como compatibilidad, no como única API.
- Frontend: HTML SSR, Vanilla JS y progressive enhancement.
- React no será requisito global del administrador.

## Alternativas consideradas

- Theme obligatorio: reduce reutilización.
- Todo dentro del plugin: mezcla lógica y branding.
- SPA/React frontend: añade peso y dependencia sin beneficio para el contenido principal.

## Consecuencias

### Positivas
- Reutilización real entre inmobiliarias.
- SEO y accesibilidad robustos sin depender de JS.
- WLA Inmo Light puede cambiar sin afectar datos.

### Trade-offs
- Hay que mantener contratos de templates/hooks.
- Se debe probar con múltiples temas.

## Impacto

- Performance: SSR y assets condicionales.
- SEO/GEO/AEO: contenido esencial en HTML.
- Compatibilidad: contrato de override estable.

## Revisión futura

Revisar API pública de presentación antes de 1.0 para congelar nombres y compatibilidad.