# ADR-003 — Ubicación, mapas y multimedia

Estado: accepted  
Fecha: 2026-09-03  
Decisiones: D15–D19  
Issue: #2

## Contexto

La ubicación puede contener datos sensibles y el sistema no debe depender de un proveedor único de mapas. Galerías y videos deben ser nativos, seguros y reutilizables.

## Decisión

- Separar dirección/ubicación pública de información privada.
- La salida pública, REST y Schema nunca exponen campos marcados privados.
- Crear interfaz/adapter de mapas desacoplada del proveedor.
- Referencia inicial: **OpenStreetMap + Leaflet**.
- Google Maps u otros podrán instalarse/configurarse como integración opcional.
- Galería basada en Media Library y attachment IDs ordenados.
- Videos: archivos permitidos de Media Library o URLs de proveedores/autores autorizados; no aceptar HTML/iframe arbitrario como dato de negocio.

## Alternativas consideradas

- Google Maps obligatorio: buena cobertura, pero introduce clave, facturación y dependencia externa.
- Guardar URLs de imágenes como galería primaria: dificulta media, tamaños responsive y migración.
- Permitir embeds HTML libres: flexible pero incrementa XSS y mantenimiento.

## Consecuencias

### Positivas
- Privacidad explícita.
- Cero API key obligatoria para mapas de referencia.
- Multimedia integrada con WordPress.
- Menor superficie XSS.

### Trade-offs
- Leaflet/tiles requieren política de proveedor adecuada en producción.
- Integraciones externas requieren adapters y QA propio.

## Impacto

- Seguridad: protección de ubicación y validación de media.
- Performance: assets de mapas solo donde se usan.
- SEO: imágenes/alt y geodatos consistentes.
- Compatibilidad: proveedor intercambiable.

## Revisión futura

Revisar el proveedor de tiles y política de geocodificación antes de release estable para sitios con tráfico alto.