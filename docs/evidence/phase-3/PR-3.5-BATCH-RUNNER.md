# Evidencia — PR 3.5 Runner reanudable de batches

Estado: `IN_PROGRESS / QA_PENDING`.

Issue: #54  
Rama: `feat/phase3-batch-runner`

## Objetivo

Implementar el primer orquestador real de batches confirmados sobre los contratos de persistencia, identidad, dry-run y ejecución idempotente de Fase 3.2–3.4.

## Principios

- el progreso solo avanza después de una fila ejecutada y checkpointed correctamente;
- reanudar nunca debe duplicar propiedades ya persistidas;
- el cursor y la revisión son la autoridad operacional del batch;
- el origen debe coincidir con el SHA-256 persistido;
- la memoria debe permanecer acotada por fila/lote, no por cantidad total;
- los hooks/resultados no deben exponer payload privado.

## Producción

`propiedadesmartinez.cl` permanece sin cambios durante esta fase.
