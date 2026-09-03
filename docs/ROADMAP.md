# Roadmap

## Estrategia

Construir WLA Inmo por capas, manteniendo el sitio actual operativo hasta que cada módulo tenga reemplazo validado.

## Fase 0 — Documentación y decisiones

Estado: **en curso**.

Entregables:

- visión de producto;
- arquitectura;
- modelo de datos;
- UX administrativa;
- centro de ayuda;
- importación/exportación;
- estrategia SEO/GEO/AEO;
- seguridad;
- independencia del tema;
- migración;
- roadmap.

Gate de salida:

- aprobar nombres de campos;
- aprobar taxonomías;
- aprobar estrategia de URLs;
- aprobar reglas de precio;
- aprobar estrategia de migración.

## Fase 1 — Core 0.1

- Bootstrap del plugin.
- Autoloading/namespaces.
- Activación/desactivación segura.
- CPT `wla_property`.
- Taxonomías iniciales.
- Capabilities/roles.
- Metadatos base.
- Primeras pruebas.

## Fase 2 — Administración 0.2

- Dashboard.
- Lista de propiedades.
- Formulario inmobiliario.
- Edición rápida.
- Acciones masivas.
- Galería.
- Validaciones.
- Score de calidad básico.
- Centro de ayuda inicial.

## Fase 3 — Importador 0.3

- CSV.
- XLSX.
- JSON.
- Mapeo.
- Dry-run.
- Upsert por código/external_id.
- Batch processing.
- Historial.
- Exportación.

## Fase 4 — Frontend 0.4

- Template loader.
- Archivo.
- Ficha.
- Cards.
- Galería.
- Buscador/filtros.
- Paginación.
- Propiedades relacionadas.
- Formularios de contacto.

Pruebas con tema externo obligatorias.

## Fase 5 — WLA Inmo Light 0.5

- Tema base.
- Header.
- Home.
- Archivo.
- Ficha.
- Footer.
- Responsive.
- Accesibilidad.
- Performance budget.

El plugin ya debe funcionar sin este tema antes de comenzar esta fase.

## Fase 6 — SEO/GEO/AEO 0.6

- metadata.
- canonical.
- OG.
- sitemap.
- breadcrumbs.
- JSON-LD.
- páginas de operación/tipo/ubicación.
- quality checks.
- información rápida/AEO.

## Fase 7 — Servicios 0.7

- Leads.
- Solicitudes de visita.
- WhatsApp.
- Indicadores económicos.
- caché y fallback.
- diagnóstico.

## Fase 8 — Migrador legado 0.8

- Inventario WooCommerce.
- Adaptador ACF/postmeta.
- Mapping de categorías.
- Mapping de galerías.
- Mapping de videos/ubicación.
- Simulación.
- Migración muestra.
- Migración completa.
- Mapping de URLs.

## Fase 9 — Hardening 0.9

- Auditoría de seguridad.
- Pruebas de permisos.
- Pruebas de importador malicioso/malformado.
- Performance tests.
- Compatibilidad con temas.
- Accesibilidad.
- QA responsive.
- SEO validation.
- migraciones de base de datos.

## Fase 10 — Release 1.0

Criterios:

- instalación limpia sin dependencias obligatorias;
- administración completa;
- importación masiva estable;
- frontend independiente del tema;
- WLA Inmo Light disponible;
- SEO/GEO/AEO implementado;
- migración del sitio actual validada;
- documentación de usuario;
- documentación técnica;
- plan de backup/rollback probado.

## Backlog posterior

Posibles módulos futuros:

- API externa de propiedades.
- sincronización con CRM.
- feeds para portales inmobiliarios.
- multiagente/corredores.
- agenda de visitas.
- favoritos.
- alertas de nuevas propiedades.
- comparador.
- integraciones de mapas configurables.
- generación asistida de descripciones, siempre con revisión humana.
- multisite/white-label.

Estos módulos no deben complicar la primera versión estable.