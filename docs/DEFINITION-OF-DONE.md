# Definition of Done

Una tarea, módulo o PR solo se considera terminado cuando cumple todos los puntos aplicables.

## Funcionalidad

- [ ] Cumple criterios de aceptación.
- [ ] Maneja estados vacíos y errores previsibles.
- [ ] No rompe flujos existentes.
- [ ] No deja comportamiento temporal sin issue/documentación.

## Código

- [ ] Estructura coherente con arquitectura.
- [ ] Sin código muerto/debug accidental.
- [ ] Nombres consistentes.
- [ ] Sin duplicación evitable.
- [ ] APIs/hooks públicos documentados si se agregaron.

## Datos

- [ ] Fuente de verdad definida.
- [ ] Validación y normalización implementadas.
- [ ] Migración versionada si aplica.
- [ ] Idempotencia evaluada.
- [ ] No hay riesgo de pérdida silenciosa.

## Seguridad

- [ ] Authorization/capabilities.
- [ ] Nonce/CSRF cuando aplica.
- [ ] Sanitización de entrada.
- [ ] Escaping contextual.
- [ ] SQL seguro.
- [ ] Archivos/URLs remotas validados.
- [ ] No se exponen secretos ni PII innecesaria.

## Tests

- [ ] Unit tests aplicables.
- [ ] Integration tests aplicables.
- [ ] E2E crítico aplicable.
- [ ] Smoke test PASS.
- [ ] Regression relevante PASS.
- [ ] Evidencia registrada.

## UX y accesibilidad

- [ ] Flujo entendible por usuario objetivo.
- [ ] Mensajes de error claros.
- [ ] Teclado y foco revisados.
- [ ] Responsive revisado.
- [ ] Ayuda contextual actualizada si el flujo es administrativo.

## Performance

- [ ] No carga assets innecesarios.
- [ ] Consultas revisadas.
- [ ] No N+1 nuevo.
- [ ] Medición antes/después si se declara mejora.

## SEO/GEO/AEO

- [ ] URLs/canonical revisados si aplica.
- [ ] Meta/schema/sitemap revisados si aplica.
- [ ] No contradice datos visibles.
- [ ] No crea indexación accidental.

## Documentación

- [ ] Documento funcional/arquitectura actualizado.
- [ ] Centro de ayuda actualizado cuando corresponde.
- [ ] Changelog/release note cuando corresponde.
- [ ] ADR agregado si hubo decisión estructural.

## Operación

- [ ] Rollback o recuperación definida.
- [ ] Logs útiles y seguros cuando corresponde.
- [ ] Diagnóstico posible.

## PR

- [ ] Template completo.
- [ ] Checks verdes.
- [ ] Review crítico resuelto.
- [ ] Sin cambios no relacionados.

Si algún punto aplicable se omite, debe explicarse explícitamente en la PR.
