# ADR-005 — Administración, revisiones e historial

Estado: accepted  
Fecha: 2026-09-03  
Decisiones: D27–D29  
Issue: #2

## Contexto

El administrador debe ser comprensible para usuarios inmobiliarios y permitir auditoría de cambios críticos.

## Decisión

- Crear una experiencia administrativa propia dentro de WP Admin con lenguaje inmobiliario.
- La edición se organiza por secciones: identificación, precio, superficies, características, ubicación, descripción, multimedia, contacto, SEO/GEO/AEO, calidad e historial.
- Mostrar score/checklist de completitud con recomendaciones accionables.
- Mantener revisiones nativas de WordPress para contenido editorial cuando aplique.
- Crear historial específico para eventos críticos como cambios de precio, estado, publicación, importación y origen del cambio.

## Alternativas consideradas

- Metaboxes técnicos genéricos: menor esfuerzo, peor UX.
- Log completo de cada modificación/clic: demasiado ruido y crecimiento de datos.

## Consecuencias

### Positivas
- Menor curva de aprendizaje.
- Mejor trazabilidad comercial.
- Soporte y auditorías más sencillos.

### Trade-offs
- Requiere más trabajo de UI que usar campos genéricos.
- Deben definirse políticas de retención del historial.

## Impacto

- Seguridad: capabilities por acción/sección.
- Datos: historial separado del registro canónico.
- UX: acciones rápidas, masivas y ayuda contextual.

## Revisión futura

Revisar la navegación con pruebas reales de usuarios antes de declarar estable la Fase 2.