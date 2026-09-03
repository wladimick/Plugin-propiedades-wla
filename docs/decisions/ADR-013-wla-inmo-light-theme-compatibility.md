# ADR-013 — WLA Inmo Light y compatibilidad con temas

Estado: accepted  
Fecha: 2026-09-03  
Decisiones: D74–D75  
Issue: #2

## Contexto

WLA Inmo Light será el tema de referencia de máximo rendimiento, pero el plugin debe funcionar correctamente sin él.

## Decisión

- WLA Inmo Light será un tema clásico/híbrido ultraligero con `theme.json` cuando aporte valor.
- Full Site Editing no será requisito de funcionamiento.
- El tema no contendrá lógica de negocio inmobiliaria.
- Cada release importante deberá probar el plugin con:
  1. WLA Inmo Light;
  2. al menos un tema core de WordPress;
  3. al menos un tema de terceros razonablemente estándar.
- Cambiar o desactivar WLA Inmo Light no puede afectar datos, importaciones, administración ni API del plugin.

## Alternativas consideradas

- Tema obligatorio: contradice el producto reutilizable.
- FSE obligatorio: aumenta el contrato visual y no es necesario para el objetivo de rendimiento.

## Consecuencias

### Positivas
- Independencia real entre motor y presentación.
- WLA Inmo Light puede optimizar agresivamente el frontend.
- Mayor reutilización en sitios con identidad visual propia.

### Trade-offs
- QA multiplataforma/tema obligatorio.
- Templates fallback deben mantenerse visualmente neutrales.

## Impacto

- Performance: Light sirve como implementación de referencia y benchmark.
- Compatibilidad: pruebas con varios temas forman parte del release gate.
- Migración: permite activar el plugin antes de cambiar de tema.

## Revisión futura

Revisar la estrategia clásico/híbrido cuando el ecosistema WordPress haga recomendable otra arquitectura sin comprometer independencia.