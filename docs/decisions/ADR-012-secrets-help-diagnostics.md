# ADR-012 — Secrets, ayuda y diagnóstico

Estado: accepted  
Fecha: 2026-09-03  
Decisiones: D71–D73  
Issue: #2

## Contexto

El producto debe ser fácil de administrar por personas no técnicas y, al mismo tiempo, permitir soporte sin exponer información sensible.

## Decisión

- Secrets, tokens, credenciales y datos equivalentes nunca se almacenan en el repositorio ni aparecen en exportaciones de diagnóstico.
- WLA Inmo incluirá un centro de ayuda embebido en el administrador.
- Las pantallas y campos relevantes tendrán ayuda contextual y enlaces directos al artículo correspondiente.
- El diagnóstico podrá copiarse/exportarse para soporte, pero será sanitizado y excluirá claves API, cookies, nonces, contraseñas, tokens y PII innecesaria.

## Alternativas consideradas

- Documentación solo en GitHub: insuficiente para administradores no técnicos.
- Diagnóstico completo sin sanitizar: facilita soporte pero crea riesgo de fuga de secretos.

## Consecuencias

### Positivas
- Menor dependencia de soporte técnico para tareas habituales.
- Mejor seguridad en tickets/auditorías.
- Onboarding más simple.

### Trade-offs
- El centro de ayuda debe mantenerse sincronizado con la UI.
- Diagnóstico sanitizado puede requerir pasos adicionales para debugging avanzado.

## Impacto

- Seguridad: no filtración de credenciales.
- UX: ayuda forma parte del producto.
- Auditoría: diagnóstico reproducible y seguro.

## Revisión futura

Agregar telemetría/soporte remoto solo mediante una decisión futura explícita y opt-in.