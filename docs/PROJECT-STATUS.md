# Estado del proyecto

Este documento es el registro vivo para auditorías rápidas. Debe actualizarse cuando una fase cambie de estado o cuando se cierre un hito relevante.

## Estado general

- Proyecto: WLA Inmo
- Tema de referencia: WLA Inmo Light
- Etapa actual: `PHASE-1 / CORE`
- Fase 0: `DONE`
- Fase 1: `IN_PROGRESS`
- Código de producto: `0.1.0-alpha.1`
- Producción: no afectada
- Decisiones críticas: D01–D75 `ACCEPTED`
- Registro: `docs/decisions/DECISION-REGISTER.md`
- Issue de cierre Fase 0: #2
- PR 1.1: #5 `DONE`
- PR 1.2: #8 `DONE`
- PR 1.3: #10 `DONE`
- Issue activo Fase 1.4: #11

## Fases

| Fase | Nombre | Estado | Evidencia principal |
|---|---|---|---|
| 0 | Gobierno y diseño | DONE | `/docs`, PR #1, Issue #2, ADR-001–ADR-013 |
| 1 | Core del plugin | IN_PROGRESS | `PHASE-1-BACKLOG.md`, PR #5, PR #8, PR #10, Issue #11, `docs/evidence/phase-1/` |
| 2 | Administración | PLANNED | pendiente |
| 3 | Import/Export | PLANNED | pendiente |
| 4 | Frontend agnóstico al tema | PLANNED | pendiente |
| 5 | WLA Inmo Light | PLANNED | pendiente |
| 6 | SEO/GEO/AEO | PLANNED | pendiente |
| 7 | Leads e indicadores | PLANNED | pendiente |
| 8 | Security hardening | PLANNED | pendiente |
| 9 | Migración Propiedades Martínez | PLANNED | pendiente |
| 10 | Release 1.0 | PLANNED | pendiente |

## Checklist de Fase 0 — CERRADO

- [x] Visión y requisitos iniciales
- [x] Arquitectura inicial
- [x] Modelo de datos inicial
- [x] Admin/UX inicial
- [x] Help Center inicial
- [x] Import/Export inicial
- [x] SEO/GEO/AEO inicial
- [x] Seguridad inicial
- [x] Integración con temas
- [x] Estrategia de migración
- [x] Roadmap inicial
- [x] Metodología de desarrollo
- [x] Fases de desarrollo auditables
- [x] Stack propuesto
- [x] Estrategia de testing
- [x] Catálogo base de tests
- [x] Quality Gates
- [x] Definition of Done
- [x] Flujo de PR
- [x] Auditoría y trazabilidad
- [x] Estándar de documentación
- [x] CI/CD propuesto
- [x] Proceso de releases
- [x] Secciones completas del administrador
- [x] Plantilla de PR
- [x] Plantilla ADR
- [x] Validar decisiones críticas mediante ADR aceptados
- [x] Validar versión mínima PHP/WordPress — PHP 8.1+ / WP 6.6+
- [x] Aprobar arquitectura del índice de búsqueda como proyección canónica → índice
- [x] Definir contrato plugin/tema inicial
- [x] Definir proveedor de mapas de referencia
- [x] Seleccionar estrategia XLSX
- [x] Definir estrategia API/hooks
- [x] Aprobar entrada a Fase 1

## Progreso de Fase 1

### PR 1.1 — Bootstrap y build

Estado: `DONE`

- PR #5 — squash merge `7ca5b05f6763a7f8dc83f60995b2dc0760f68114`.
- CI: SUCCESS.
- Evidencia: `docs/evidence/phase-1/PR-1.1-BOOTSTRAP.md`.

### PR 1.2 — Entidad Property

Estado: `DONE`

- PR #8 — squash merge `da989ef50a9d066023ae2c00d776d05af3d3499c`.
- CI run `33818077411`: SUCCESS.
- CPT `wla_property` y capabilities base incorporadas.
- Evidencia: `docs/evidence/phase-1/PR-1.2-PROPERTY-ENTITY.md`.

### PR 1.3 — Taxonomías base

Estado: `DONE`

- PR #10 — squash merge `61954bfbab9827b6d07d6b6151b9095677951dee`.
- CI run `33818338049`: SUCCESS.
- Cinco taxonomías inmobiliarias base y capabilities de términos incorporadas.
- Evidencia: `docs/evidence/phase-1/PR-1.3-TAXONOMIES.md`.

### PR 1.4 — Meta schema canónico y validación

Estado: `IN_PROGRESS / QA PENDING`

Issue: #11  
Rama: `feat/phase1-meta-schema`

Alcance:

- `Properties\\MetaSchema`;
- meta keys protegidos `_wla_inmo_*`;
- `Properties\\Sanitizer`;
- `Properties\\Validator`;
- separación de campos públicos/internos;
- datos geográficos/monetarios/multimedia normalizados;
- raw postmeta no expuesto por REST;
- smoke tests del contrato.

Regla de diseño: operación, tipo, región, comuna y sector continúan exclusivamente como taxonomías y no se duplican en postmeta.

No marcar PR 1.4 como `DONE` hasta que su PR esté mergeada y CI esté verde.

## Decisiones aceptadas

Los ADR aceptados son:

- ADR-001 Plataforma, compatibilidad y dependencias.
- ADR-002 Property, fuente de verdad, índice, taxonomías y precios.
- ADR-003 Ubicación, mapas y multimedia.
- ADR-004 Contrato plugin/tema y frontend.
- ADR-005 Administración, revisiones e historial.
- ADR-006 Importación, XLSX, jobs y rollback.
- ADR-007 REST API, hooks y extensibilidad.
- ADR-008 Leads, email e indicadores.
- ADR-009 SEO, GEO, AEO y Schema.
- ADR-010 Roles, seguridad operativa, datos, telemetría e internacionalización.
- ADR-011 Accesibilidad, performance, testing, PR y releases.
- ADR-012 Secrets, ayuda y diagnóstico.
- ADR-013 WLA Inmo Light y compatibilidad con temas.

El detalle D01–D75 está en `docs/decisions/DECISION-REGISTER.md`.

## Entrada a Fase 1

**Aprobada.** El backlog inicial se encuentra en `docs/PHASE-1-BACKLOG.md`.

La implementación continúa sobre un core nativo y no está migrando el sitio productivo.

## Riesgos trasladados a implementación

No bloquean el cierre de Fase 0, pero deben validarse con evidencia en sus fases correspondientes:

1. Índices SQL exactos se ajustarán con benchmarks reales; no sobreindexar anticipadamente.
2. PhpSpreadsheet debe medirse en tamaño/memoria antes de 1.0.
3. Proveedor de tiles/geocoding de OpenStreetMap debe definirse para instalaciones de tráfico relevante.
4. Adaptadores concretos para plugins SEO se implementarán y probarán en Fase 6.
5. Compatibilidad Multisite se valida progresivamente y no bloquea v0.1.
6. Lighthouse ≥95 es budget de referencia; CWV reales requerirán RUM/datos productivos.

## Regla de actualización

Cuando un ítem pase a `DONE` debe existir una PR, test, evidencia o documento que lo sustente. No marcar tareas como completas solo porque fueron conversadas.

Una decisión `accepted` no se modifica silenciosamente: requiere nuevo ADR/PR con impacto y motivo.

## Auditoría

Para auditoría completa usar `AUDIT-TRACEABILITY.md`. Para revisión rápida comenzar por este archivo, `docs/decisions/DECISION-REGISTER.md`, `DEVELOPMENT-PHASES.md`, PRs, `TEST-CASE-CATALOG.md` y evidencias.
