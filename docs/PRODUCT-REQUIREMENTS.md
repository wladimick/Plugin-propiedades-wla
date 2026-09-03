# WLA Inmo — Visión y requisitos

## 1. Propósito

WLA Inmo debe ser un motor inmobiliario reutilizable para WordPress. Debe permitir que una inmobiliaria administre su catálogo, publique propiedades, reciba consultas y mantenga una presencia optimizada para buscadores y sistemas generativos sin depender de WooCommerce, Elementor, ACF o WPCode.

## 2. Público objetivo

- Inmobiliarias pequeñas y medianas.
- Corredores de propiedades.
- Equipos administrativos con conocimientos básicos de WordPress.
- Agencias que necesiten reutilizar la solución en múltiples clientes.

## 3. Requisitos funcionales

### Propiedades

- Crear, editar, duplicar, archivar y eliminar propiedades.
- Código único por propiedad.
- Estados configurables: disponible, reservada, vendida, arrendada, no disponible, borrador.
- Operaciones: venta, arriendo y otras extensibles.
- Tipos: casa, departamento, terreno, oficina, local, parcela, bodega, industrial y otros extensibles.
- Precio CLP, UF y USD sin duplicar fuentes de verdad.
- Dirección, región, comuna, sector, coordenadas.
- Superficie total y construida.
- Dormitorios, baños, estacionamientos y atributos adicionales.
- Imagen destacada, galería y videos.
- Descripción comercial y resumen estructurado.
- Propiedades destacadas y orden manual.

### Frontend

- Archivo/listado de propiedades.
- Búsqueda y filtros rápidos.
- Ficha individual.
- Páginas por operación, tipo y ubicación.
- Propiedades relacionadas.
- Solicitud de visita y consulta.
- WhatsApp y llamada configurables.
- Indicadores económicos con caché.

### Administración

- Dashboard inmobiliario.
- Listado limpio y orientado al negocio.
- Edición rápida.
- Acciones masivas.
- Indicadores de calidad/incompletitud de ficha.
- Centro de ayuda integrado.
- Roles y permisos específicos.

### Importación

- XLSX, CSV y JSON.
- Mapeo de columnas.
- Previsualización.
- Validación previa.
- Modo simulación.
- Identificación por código/external_id.
- Crear o actualizar sin duplicar.
- Procesamiento por lotes.
- Historial de importaciones.
- Reporte de errores y advertencias.

### SEO/GEO/AEO

- URLs limpias.
- Metadatos SEO.
- canonical.
- Open Graph.
- Sitemap.
- Schema JSON-LD.
- Breadcrumbs.
- Datos inmobiliarios estructurados.
- Páginas locales útiles, evitando thin content.
- Respuestas directas a preguntas frecuentes derivadas de datos reales.

## 4. Requisitos no funcionales

### Rendimiento

- Sin page builder requerido.
- Sin jQuery obligatorio en frontend.
- CSS y JS cargados solo cuando correspondan.
- Caché para datos externos.
- Consultas optimizadas.
- Índice especializado para filtros si el volumen lo requiere.
- Compatible con caché de página/CDN.

### Seguridad

- Nonces.
- Capabilities.
- Sanitización y escaping.
- Consultas preparadas.
- Validación de archivos.
- Protección CSRF.
- Rate limiting en formularios sensibles.
- Registro de acciones administrativas importantes.

### Portabilidad

- El plugin debe funcionar con cualquier tema WordPress razonablemente compatible.
- WLA Inmo Light es opcional.
- Ninguna función crítica puede depender de clases CSS o plantillas exclusivas de WLA Inmo Light.

## 5. Criterios de éxito

WLA Inmo v1 se considera exitoso cuando:

1. Puede instalarse en un WordPress limpio sin WooCommerce, Elementor, ACF ni WPCode.
2. Permite crear una propiedad completa desde el administrador.
3. Permite importar un catálogo masivo sin duplicados.
4. Muestra listados y fichas usando un tema externo.
5. Tiene plantillas fallback funcionales.
6. Genera metadata y Schema correctamente.
7. Un usuario no técnico puede crear y actualizar propiedades siguiendo el centro de ayuda.
8. El sitio actual puede migrarse sin perder URLs, imágenes ni datos esenciales.