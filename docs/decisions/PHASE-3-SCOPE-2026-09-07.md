# Decisión de alcance — Fase 3 / Exportación CSV y XLSX

Fecha: 2026-09-07  
Estado: `ACCEPTED`  
Aprobación: propietario del proyecto

## Decisión

La **exportación CSV y XLSX no se implementará dentro de la Fase 3** de WLA Inmo.

El hito canónico **PR 3.10 — Exportación CSV/XLSX** se conserva en la numeración para auditoría, pero cambia su estado a:

`OMITTED / OUT_OF_SCOPE`

No se renumeran los hitos posteriores.

## Qué cambia

- Fase 3 no requiere exportador CSV para considerarse completa.
- Fase 3 no requiere exportador XLSX para considerarse completa.
- Los tests específicos de spreadsheet/formula injection de **salida CSV/XLSX** dejan de ser criterio de salida de Fase 3.
- La seguridad frente a fórmulas en **archivos de entrada CSV/XLSX** permanece obligatoria y ya pertenece al pipeline de importación.

## Qué no cambia

- Importación CSV sigue dentro de Fase 3 y permanece implementada.
- Importación XLSX sigue dentro de Fase 3 y permanece implementada.
- Importación JSON WLA sigue dentro de Fase 3.
- **Exportación JSON WLA v1 se conserva**, porque ya fue implementada y mergeada en PR 3.7.
- D30/D31 no se reemplazan: esta decisión modifica el alcance de entrega de Fase 3, no la arquitectura ni la compatibilidad de formatos de importación.
- PR 3.11 continúa siendo Rollback seguro.
- PR 3.12 continúa siendo Quality Gate de Fase 3.

## Motivo

Reducir alcance de esta etapa y concentrar el cierre de Fase 3 en la robustez del importador, media remota, idempotencia, rollback y quality gate final.

La exportación CSV/XLSX puede reconsiderarse en una fase o release posterior si existe una necesidad de producto concreta.

## Regla de auditoría

Los documentos de estado deben mostrar PR 3.10 como `OMITTED / OUT_OF_SCOPE`; no debe eliminarse el hito ni presentarse como trabajo pendiente o requisito bloqueante de Fase 3.
