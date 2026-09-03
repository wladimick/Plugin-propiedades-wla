# ADR-007 — REST API, hooks y extensibilidad

Estado: accepted  
Fecha: 2026-09-03  
Decisiones: D39–D41  
Issue: #2

## Contexto

El producto debe permitir integraciones futuras sin obligar a modificar el core.

## Decisión

- Diseñar REST API versionada bajo `/wla-inmo/v1`.
- Publicar endpoints de manera progresiva; lecturas públicas solo exponen datos publicados y no privados.
- Escrituras REST requieren autenticación, capabilities y schema estricto.
- Exponer hooks y API PHP documentados; el contrato público deberá estabilizarse para 1.0.
- Webhooks no forman parte del MVP, pero la arquitectura no debe impedir agregarlos posteriormente.

## Alternativas consideradas

- Sin API pública: simplifica MVP pero fuerza integraciones acopladas.
- Webhooks desde v0.1: aumenta superficie y complejidad sin caso de uso confirmado.

## Consecuencias

### Positivas
- Extensiones desacopladas.
- Integraciones futuras con CRM/apps.
- Temas pueden consumir contratos estables.

### Trade-offs
- El API público aumenta responsabilidad de versionado y seguridad.

## Impacto

- Seguridad: `permission_callback` y exclusión de datos privados son obligatorios.
- Compatibilidad: SemVer y deprecaciones documentadas.
- Testing: contrato REST/hooks requiere integration tests.

## Revisión futura

Congelar contrato de API/hook público durante RC de 1.0.