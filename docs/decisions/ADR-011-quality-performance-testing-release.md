# ADR-011 — Accesibilidad, performance, testing, PR y releases

Estado: accepted  
Fecha: 2026-09-03  
Decisiones: D62–D70  
Issue: #2

## Contexto

La velocidad, accesibilidad y auditabilidad son requisitos de producto y deben medirse, no asumirse.

## Decisión

- WCAG 2.2 AA como criterio de aceptación para admin y frontend.
- Performance budget obligatorio.
- Objetivo Lighthouse inicial con WLA Inmo Light en páginas de referencia: ≥95 Performance, Accessibility, Best Practices y SEO.
- Core Web Vitals objetivo `Good` con datos reales cuando exista producción.
- Stack de pruebas/calidad: PHPUnit, WordPress integration tests, Playwright, Lighthouse CI, WPCS y PHPStan.
- PR pequeñas y temáticas con tests, documentación y evidencia en la misma PR.
- Squash merge por defecto.
- SemVer para releases.
- Distribución mediante ZIP instalable que no requiere Composer/Node en producción.

## Alternativas consideradas

- QA manual sin budgets: difícil de auditar y propenso a regresiones.
- Megapull requests: menor trazabilidad y revisiones de peor calidad.
- Merge commits por defecto: historial más ruidoso para este proyecto.

## Consecuencias

### Positivas
- Calidad objetiva y auditable.
- Regresiones detectables en CI.
- Historial de main más legible.

### Trade-offs
- Más infraestructura CI y mantenimiento de tests.
- Lighthouse de laboratorio no sustituye RUM/CWV real.

## Impacto

- Performance: budgets forman parte de Definition of Done.
- Accesibilidad: pruebas automáticas + QA manual de teclado/lectores donde aplique.
- Release: artefacto reproducible y versionado.

## Revisión futura

Ajustar budgets solo con evidencia/benchmark y ADR o actualización documentada.