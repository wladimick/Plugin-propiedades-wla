# Estado del proyecto

Este documento es el registro vivo para auditorías rápidas. Debe actualizarse cuando una fase cambie de estado o cuando se cierre un hito relevante.

## Estado general

- Proyecto: WLA Inmo
- Tema de referencia: WLA Inmo Light
- Etapa actual: `PHASE-0 / GOVERNANCE & DESIGN`
- Estado: `IN_PROGRESS`
- Código de producto: aún no iniciado
- Producción: no afectada

## Fases

| Fase | Nombre | Estado | Evidencia principal |
|---|---|---|---|
| 0 | Gobierno y diseño | IN_PROGRESS | `/docs`, PRs de documentación |
| 1 | Core del plugin | PLANNED | pendiente |
| 2 | Administración | PLANNED | pendiente |
| 3 | Import/Export | PLANNED | pendiente |
| 4 | Frontend agnóstico al tema | PLANNED | pendiente |
| 5 | WLA Inmo Light | PLANNED | pendiente |
| 6 | SEO/GEO/AEO | PLANNED | pendiente |
| 7 | Leads e indicadores | PLANNED | pendiente |
| 8 | Security hardening | PLANNED | pendiente |
| 9 | Migración Propiedades Martínez | PLANNED | pendiente |
| 10 | Release 1.0 | PLANNED | pendiente |

## Checklist de Fase 0

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
- [ ] Validar decisiones críticas de arquitectura mediante ADR aceptados
- [ ] Validar versión mínima PHP/WordPress
- [ ] Definir esquema físico final del índice de búsqueda
- [ ] Definir contrato público inicial (hooks/templates/API)
- [ ] Aprobar entrada a Fase 1

## Riesgos abiertos de Fase 0

1. El esquema exacto de tabla índice aún es diseño, no implementación.
2. La dependencia XLSX todavía debe seleccionarse mediante ADR.
3. La estrategia exacta de mapas/proveedor no está fijada.
4. La matriz mínima de PHP/WordPress debe confirmarse antes de CI definitivo.
5. La convivencia con plugins SEO debe definirse con adaptadores/detección concreta.

## Regla de actualización

Cuando un ítem pase a `DONE` debe existir una PR, test, evidencia o documento que lo sustente. No marcar tareas como completas solo porque fueron conversadas.

## Auditoría

Para una auditoría completa usar `AUDIT-TRACEABILITY.md`. Para una revisión rápida comenzar por este archivo, `DEVELOPMENT-PHASES.md`, PRs abiertas y `TEST-CASE-CATALOG.md`.
