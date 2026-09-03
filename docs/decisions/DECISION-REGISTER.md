# Registro de decisiones — WLA Inmo

Estado del registro: **APROBADO**  
Fecha de aprobación: **2026-09-03**  
Aprobación: propietario del proyecto  
Issue de trazabilidad: **#2**

Este documento registra las decisiones críticas D01–D75 aprobadas para cerrar la Fase 0. Los ADR agrupados conservan el identificador original de cada decisión para permitir auditorías posteriores.

## Regla de gobierno

- Una decisión `accepted` rige el desarrollo hasta que un ADR posterior la reemplace.
- Cambiar una decisión aceptada requiere nueva PR, motivación, impacto, tests afectados y ADR de reemplazo.
- No se debe modificar silenciosamente una decisión estructural durante implementación.

## Mapa D01–D75

| Decisiones | ADR | Tema | Estado |
|---|---|---|---|
| D01–D04 | ADR-001 | Plataforma, compatibilidad y dependencias | accepted |
| D05–D14 | ADR-002 | Entidad Property, fuente de verdad, índice, taxonomías y precios | accepted |
| D15–D19 | ADR-003 | Privacidad de ubicación, mapas y multimedia | accepted |
| D20–D26 | ADR-004 | Contrato plugin/tema y frontend | accepted |
| D27–D29 | ADR-005 | Administración, revisiones e historial | accepted |
| D30–D38 | ADR-006 | Importación, XLSX, jobs y rollback | accepted |
| D39–D41 | ADR-007 | REST API, hooks y extensibilidad | accepted |
| D42–D47 | ADR-008 | Leads, email e indicadores | accepted |
| D48–D53 | ADR-009 | SEO, GEO, AEO y Schema | accepted |
| D54–D61 | ADR-010 | Roles, seguridad operativa, datos, telemetría e internacionalización | accepted |
| D62–D70 | ADR-011 | Accesibilidad, performance, testing, PR y releases | accepted |
| D71–D73 | ADR-012 | Secrets, ayuda y diagnóstico | accepted |
| D74–D75 | ADR-013 | WLA Inmo Light y compatibilidad con temas | accepted |

## Decisiones explícitas aprobadas

### D01–D04 — Plataforma
- D01: PHP mínimo 8.1+.
- D02: WordPress mínimo 6.6+, probado también contra la última estable.
- D03: Multisite compatible por diseño, no bloqueante para v0.1.
- D04: cero dependencias funcionales obligatorias de WooCommerce, Elementor, ACF, WPCode, filtros de productos o jQuery frontend.

### D05–D14 — Datos
- D05: CPT `wla_property`.
- D06: una sola fuente de verdad; el índice es proyección, no registro canónico.
- D07: tabla `wp_wla_property_index` prevista desde el core.
- D08: operación, tipo, región, comuna y sector como taxonomías; características según utilidad.
- D09: `property_code` único cuando exista y `external_id` separado.
- D10: estados comerciales configurables con base estándar.
- D11: Venta/Arriendo base, extensible.
- D12: CLP/UF/USD almacenables con una moneda principal.
- D13: conversiones derivadas y opcionales, nunca nueva fuente de verdad.
- D14: `price_on_request` explícito.

### D15–D19 — Ubicación y media
- D15: separar ubicación pública y privada.
- D16: mapas mediante adapter desacoplado.
- D17: OpenStreetMap + Leaflet como referencia inicial; Google Maps opcional.
- D18: galería basada en Media Library y attachment IDs.
- D19: videos locales o URLs permitidas mediante política/whitelist.

### D20–D26 — Frontend
- D20: plugin = datos/lógica/API; tema = presentación.
- D21: templates fallback neutrales dentro del plugin.
- D22: overrides `theme-child → theme → plugin` bajo `wla-inmo/`.
- D23: Gutenberg progresivo, no dependencia del core.
- D24: shortcodes de compatibilidad, no API única.
- D25: frontend Vanilla JS + progressive enhancement.
- D26: React no será requisito global del administrador.

### D27–D29 — Administración
- D27: administrador inmobiliario propio y orientado al dominio.
- D28: edición guiada por secciones + score de completitud.
- D29: revisiones WP para contenido e historial específico para cambios críticos.

### D30–D38 — Import/Export
- D30: XLSX, CSV y JSON desde v1.
- D31: PhpSpreadsheet encapsulada en Import/Export.
- D32: identidad de upsert: `external_id → property_code`; nunca título/dirección.
- D33: vacío conserva valor existente por defecto.
- D34: dry-run obligatorio antes de importación masiva.
- D35: procesamiento por lotes reanudable.
- D36: WP-Cron inicialmente; backend de jobs reemplazable; Action Scheduler solo si se justifica.
- D37: media remota con controles SSRF, MIME, tamaño y timeout.
- D38: rollback best-effort, con protección ante cambios posteriores.

### D39–D41 — APIs
- D39: REST API versionada `/wla-inmo/v1`, publicada progresivamente.
- D40: hooks/API PHP públicos y estables para 1.0.
- D41: webhooks fuera del MVP, dejando extensión futura.

### D42–D47 — Leads e indicadores
- D42: módulo de leads propio y opcional.
- D43: honeypot + rate limiting; Turnstile opcional.
- D44: email mediante APIs WordPress; SMTP corresponde al sitio.
- D45: retención de leads configurable; referencia inicial 24 meses.
- D46: indicadores mediante servicio desacoplado; Mindicador.cl adapter inicial Chile.
- D47: caché 6 horas + último valor válido como fallback.

### D48–D53 — SEO/GEO/AEO
- D48: SEO esencial propio sin dependencia de plugin SEO.
- D49: detectar/adaptarse a plugins SEO para evitar duplicados.
- D50: un grafo Schema coherente basado en datos reales.
- D51: páginas GEO/locales solo con valor real.
- D52: filtros funcionales sin indexar combinaciones arbitrarias.
- D53: AEO mediante información/respuestas visibles derivadas de datos reales.

### D54–D61 — Seguridad y producto reutilizable
- D54: roles Administrador WP, Administrador inmobiliario, Editor de propiedades y Gestor de leads.
- D55: capabilities granulares.
- D56: bitácora de eventos relevantes, no de cada clic.
- D57: retención de actividad 12 meses por defecto, configurable.
- D58: desactivar nunca elimina datos; uninstall conserva por defecto y borrar exige decisión explícita.
- D59: ninguna telemetría remota por defecto.
- D60: translation-ready desde el primer commit.
- D61: Chile como preset, no limitación del core.

### D62–D70 — Calidad de ingeniería
- D62: WCAG 2.2 AA como criterio de aceptación.
- D63: performance budget obligatorio.
- D64: Lighthouse objetivo inicial ≥95 en Performance, Accessibility, Best Practices y SEO para páginas de referencia con WLA Inmo Light.
- D65: Core Web Vitals objetivo `Good` usando datos reales cuando exista producción.
- D66: PHPUnit + WP integration + Playwright + Lighthouse CI + WPCS + PHPStan.
- D67: PR pequeñas/temáticas con tests, documentación y evidencia.
- D68: Squash merge por defecto.
- D69: SemVer.
- D70: ZIP instalable sin Composer/Node en producción.

### D71–D73 — Soporte
- D71: secrets nunca en repo ni exportables.
- D72: centro de ayuda embebido + ayuda contextual.
- D73: diagnóstico exportable y sanitizado.

### D74–D75 — Tema
- D74: WLA Inmo Light clásico/híbrido ultraligero con `theme.json`; FSE no obligatorio.
- D75: QA de independencia con WLA Inmo Light, un tema core y un tema de terceros.

## Evidencia

- Issue: #2
- Documentos base: `ARCHITECTURE.md`, `STACK.md`, `DATA-MODEL.md`, `ADMIN-SECTIONS.md`, `IMPORT-EXPORT.md`, `SECURITY.md`, `SEO-GEO-AEO.md`, `THEME-INTEGRATION.md`.
- Aprobación explícita registrada el 2026-09-03.
