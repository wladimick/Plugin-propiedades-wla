# ADR-010 — Roles, seguridad operativa, datos, telemetría e internacionalización

Estado: accepted  
Fecha: 2026-09-03  
Decisiones: D54–D61  
Issue: #2

## Contexto

WLA Inmo debe operar con mínimo privilegio, conservar datos de forma segura y ser reutilizable fuera del sitio/país inicial.

## Decisión

- Roles base: Administrador WP, Administrador inmobiliario, Editor de propiedades y Gestor de leads.
- Capabilities granulares para propiedades, publicación, importación, ajustes, SEO y leads.
- Bitácora de eventos relevantes de negocio/seguridad, no de cada clic.
- Retención de actividad: 12 meses por defecto, configurable.
- Desactivar el plugin nunca borra datos.
- En uninstall, conservar datos por defecto; eliminación requiere decisión/configuración explícita y confirmada.
- Ninguna telemetría remota por defecto.
- Translation-ready desde el primer commit.
- Chile es preset inicial, no limitación del core; UF, regiones/comunas e indicadores son configuración/adapters.

## Alternativas consideradas

- Usar solo `manage_options`: demasiado privilegio.
- Borrar al desinstalar automáticamente: riesgo inaceptable para inventario inmobiliario.
- Hardcodear Chile: reduce reutilización.

## Consecuencias

### Positivas
- Mejor seguridad por roles.
- Menor riesgo de pérdida accidental.
- Producto reutilizable internacionalmente.
- Privacidad por defecto respecto de telemetría.

### Trade-offs
- Más capabilities y tests de permisos.
- Internacionalización exige evitar supuestos locales en el dominio.

## Impacto

- Seguridad: principio de menor privilegio.
- Datos: política explícita de conservación.
- Compatibilidad: presets/adapters regionales.
- Auditoría: retención configurable de actividad.

## Revisión futura

Revisar roles y retención con feedback real de instalaciones y requisitos legales de cada país.