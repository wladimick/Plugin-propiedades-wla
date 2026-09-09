# Evidencia — Fase 4 Frontend agnóstico al tema

Estado: `IN_PROGRESS / ENTRY_GATE`

Issue de entrada: #72  
Backlog: `docs/PHASE-4-BACKLOG.md`  
Producción: `NO AFECTADA`.

## Objetivo de la carpeta

Concentrar evidencia auditable de cada PR de Fase 4: arquitectura aplicada, tests, matrices WordPress/PHP, accesibilidad, performance, artifacts, findings y decisión de merge.

## Estructura esperada

- `PR-4.1-FRONTEND-FOUNDATION.md`
- `PR-4.2-ARCHIVE-CARD.md`
- `PR-4.3-SEARCH-FILTERS.md`
- `PR-4.4-SINGLE-GALLERY.md`
- `PR-4.5-MAP-PRIVACY.md`
- `PR-4.6-CTA-PRESENTATION-API.md`
- `PR-4.7-THEME-COMPATIBILITY.md`
- `PR-4.8-PHASE-4-QUALITY-GATE.md`
- cierre post-merge si 4.8 requiere registrar el squash definitivo.

Los nombres pueden ajustarse si el scope real de un PR cambia documentalmente, pero la trazabilidad 4.1–4.8 debe preservarse.

## Decisiones ya aceptadas

Fase 4 implementa D15–D17, D20–D26, D62–D66 y D74–D75. No se deben cambiar silenciosamente durante implementación. Una desviación estructural requiere ADR/decisión de reemplazo.

## Evidencia mínima por PR

Cada archivo de evidencia debe registrar como mínimo:

1. issue y PR;
2. base/head SHA;
3. scope implementado y fuera de alcance;
4. decisiones/ADR aplicados;
5. archivos/componentes principales;
6. tests unit/integration/smoke/E2E ejecutados;
7. matriz WP/PHP/tema relevante;
8. accesibilidad y responsive cuando exista UI;
9. performance/asset budget cuando aplique;
10. artifact/checksum de release cuando corresponda;
11. review comments/reviews/threads y findings P0/P1;
12. estado `IN_PROGRESS`, `QA_PENDING`, `QA_PASSED / READY_TO_MERGE` o cierre post-merge;
13. confirmación de producción sin cambios.

## Reglas específicas de Fase 4

- validar precedencia `child theme → parent theme → plugin`;
- demostrar que paths de template no provienen del request;
- demostrar que posts/páginas ajenos no son interceptados;
- datos privados fuera del HTML/JS público;
- assets condicionales;
- CSS namespaced y sin resets globales;
- Vanilla JS/progressive enhancement;
- compatibilidad sin Elementor/WooCommerce/ACF/jQuery;
- WCAG 2.2 AA y navegación por teclado;
- tema core + tema de terceros antes del cierre de fase.

## Entrada de la fase

La fase queda formalmente iniciada cuando el entry gate documental se integre a `main`, `PROJECT-STATUS.md` marque `PHASE-4 / IN_PROGRESS` y PR 4.1 tenga issue/scope propios.

## Producción

`propiedadesmartinez.cl` no se modifica durante Fase 4. La migración productiva sigue reservada para Fase 9 o una instrucción explícita posterior.