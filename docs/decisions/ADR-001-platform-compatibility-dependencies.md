# ADR-001 — Plataforma, compatibilidad y dependencias

Estado: accepted  
Fecha: 2026-09-03  
Decisiones: D01–D04  
Issue: #2

## Contexto

WLA Inmo debe ser reutilizable, seguro y rápido sin quedar amarrado al stack histórico de Propiedades Martínez.

## Decisión

- PHP mínimo: **8.1+**.
- WordPress mínimo: **6.6+**, manteniendo pruebas contra la última versión estable.
- Multisite: arquitectura compatible cuando sea razonable, pero no bloquea v0.1.
- El core no tendrá dependencia funcional obligatoria de WooCommerce, Elementor, ACF, WPCode, plugins de filtros de productos ni jQuery frontend.
- Composer y Node podrán usarse en desarrollo/build, pero el ZIP distribuible deberá funcionar sin ejecutarlos en producción.

## Alternativas consideradas

- Mantener compatibilidad con PHP legacy: aumenta carga de compatibilidad y reduce calidad del diseño.
- Exigir solo últimas versiones: reduce adopción innecesariamente.
- Reutilizar WooCommerce/ACF como dependencias: contradice el objetivo de producto independiente.

## Consecuencias

### Positivas
- Base moderna y mantenible.
- Menor superficie de dependencias.
- Instalación más ligera.

### Trade-offs
- Sitios con PHP/WP antiguos deberán actualizarse antes de instalar.
- Multisite tendrá cobertura progresiva.

## Impacto

- Seguridad: permite APIs modernas y soporte razonable.
- Performance: elimina cargas ajenas al dominio inmobiliario.
- Compatibilidad: requiere matriz CI.
- Migración: adaptadores históricos serán opcionales.

## Revisión futura

Revisar mínimos en cada MAJOR release y cuando WordPress/PHP cambien sus ramas soportadas.