# ADR-008 — Leads, email e indicadores

Estado: accepted  
Fecha: 2026-09-03  
Decisiones: D42–D47  
Issue: #2

## Contexto

WLA Inmo debe resolver consultas inmobiliarias e indicadores sin convertir el core en un paquete de servicios externos obligatorios.

## Decisión

- Leads como módulo propio y opcional del plugin.
- Antispam base: honeypot + rate limiting; Cloudflare Turnstile u otros como integración opcional.
- Envío de email mediante APIs WordPress; SMTP/transport se configura a nivel del sitio.
- Retención de leads configurable; referencia inicial: 24 meses.
- Indicadores económicos mediante servicio desacoplado.
- Adapter inicial para Chile: Mindicador.cl.
- Caché de indicadores: 6 horas y uso del último valor válido como fallback ante caída externa.
- Nunca bloquear el render público crítico esperando una API externa.

## Alternativas consideradas

- Plugin de formularios obligatorio: contradice independencia.
- SMTP embebido: amplía innecesariamente el alcance.
- Consultar indicadores en cada request: perjudica rendimiento y resiliencia.

## Consecuencias

### Positivas
- Formularios y leads consistentes con propiedades.
- Menos dependencias.
- Alta resiliencia de indicadores.

### Trade-offs
- El sitio sigue necesitando una estrategia de entregabilidad de correo.
- La retención debe adaptarse a la política de privacidad de cada instalación.

## Impacto

- Seguridad: validación, rate limiting y privacidad de PII.
- Performance: caché/fallback obligatorio.
- Internacionalización: indicadores son adapters por país, no parte fija del dominio.

## Revisión futura

Revisar retención predeterminada y adapters de indicadores al preparar otros países.