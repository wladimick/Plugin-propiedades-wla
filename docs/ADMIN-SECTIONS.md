# Secciones del administrador

## Objetivo UX

El administrador de WLA Inmo debe poder ser utilizado por una persona con conocimientos básicos de WordPress. La interfaz debe hablar en lenguaje inmobiliario y evitar conceptos técnicos innecesarios.

## Menú principal

```text
WLA Inmo
├── Resumen
├── Propiedades
├── Nueva propiedad
├── Inicio y destacados
├── Importar / Exportar
├── Consultas / Leads
├── Ubicaciones
├── Clasificaciones
├── Multimedia
├── SEO y visibilidad
├── Indicadores
├── Calidad del catálogo
├── Actividad
├── Ayuda
├── Herramientas
└── Ajustes
```

La visibilidad de cada sección depende de capabilities.

---

## 1. Resumen

Dashboard operativo.

Widgets previstos:

- total de propiedades;
- en venta;
- en arriendo;
- disponibles/no disponibles;
- destacadas;
- propiedades nuevas del período;
- consultas nuevas;
- calidad del catálogo;
- fichas incompletas;
- propiedades sin precio;
- propiedades sin fotos;
- propiedades sin SEO mínimo;
- estado del importador;
- último valor UF/dólar;
- accesos rápidos.

Debe priorizar acciones concretas, no gráficos decorativos.

---

## 2. Propiedades

Listado principal con:

- miniatura;
- código;
- título;
- operación;
- tipo;
- ubicación;
- precio principal;
- estado;
- destacado;
- completitud;
- fecha actualización.

Filtros rápidos:

- todas;
- venta;
- arriendo;
- disponibles;
- no disponibles;
- destacadas;
- incompletas;
- sin precio;
- sin imágenes.

Acciones:

- editar;
- ver;
- duplicar;
- archivar;
- cambiar estado;
- destacar/quitar destacado;
- edición rápida;
- acciones masivas.

Búsqueda por:

- código;
- título;
- dirección/sector;
- comuna;
- external_id.

---

## 3. Nueva propiedad / Editar propiedad

### A. Estado de publicación

- borrador/publicada/archivada;
- disponible/no disponible/reservada/vendida/arrendada según configuración;
- vista previa;
- última actualización;
- autor/editor.

### B. Identificación

- código único;
- external_id opcional;
- título;
- operación;
- tipo;
- destacada;
- orden manual opcional.

### C. Precio

- precio CLP;
- precio UF;
- precio USD opcional;
- moneda principal;
- precio visible/consultar;
- gastos comunes opcionales;
- política de conversión si se implementa.

Regla: una sola fuente de verdad; las representaciones derivadas no se guardan duplicadas sin necesidad.

### D. Superficies

- terreno;
- construido;
- útil;
- terraza u otros configurables;
- unidad m².

### E. Características

- dormitorios;
- baños;
- estacionamientos;
- bodegas;
- piscina;
- calefacción;
- orientación;
- extras configurables.

### F. Ubicación

- región;
- provincia opcional;
- comuna;
- ciudad/localidad;
- sector/barrio;
- dirección privada;
- dirección pública;
- latitud/longitud;
- mostrar mapa sí/no;
- precisión/privacidad de ubicación.

### G. Descripción

- resumen corto;
- descripción completa;
- puntos destacados;
- notas internas separadas del contenido público.

### H. Multimedia

- imagen principal;
- galería ordenable;
- texto alternativo;
- videos;
- videos externos/propios según política;
- documentos opcionales futuros.

### I. Contacto

- CTA principal;
- agente/responsable opcional;
- teléfono;
- WhatsApp;
- email de destino;
- habilitar solicitud de visita.

### J. SEO / GEO / AEO

- preview de título;
- título SEO override;
- meta description override;
- canonical override solo avanzado;
- indexar sí/no;
- resumen semántico generado desde datos;
- estado de Schema;
- breadcrumbs;
- checklist SEO;
- advertencias por contradicciones.

### K. Calidad

Checklist visible:

- código;
- precio;
- operación;
- ubicación;
- superficie;
- imágenes;
- descripción;
- alt;
- SEO;
- datos de contacto.

Debe mostrar porcentaje y explicar qué falta.

### L. Historial

- cambios críticos;
- quién cambió precio/estado;
- origen del cambio manual/importación/API;
- import batch relacionado.

---

## 4. Inicio y destacados

Administración visual de bloques sin page builder obligatorio.

Funciones:

- carrusel/hero;
- destacadas;
- últimas propiedades;
- venta;
- arriendo;
- terrenos;
- bloques configurables futuros;
- búsqueda para agregar propiedad;
- drag/drop u orden accesible alternativo;
- cantidad;
- título/descripción;
- visibilidad.

El plugin expone los datos; el tema decide la presentación final mediante templates/hooks.

---

## 5. Importar / Exportar

Pestañas:

### Importar
- subir XLSX/CSV/JSON;
- elegir preset;
- mapear columnas;
- validar;
- dry-run;
- ejecutar;
- progreso;
- reporte.

### Historial
- fecha;
- usuario;
- archivo;
- nuevas;
- actualizadas;
- omitidas;
- errores;
- estado;
- detalle.

### Exportar
- todas/filtradas;
- formato;
- columnas;
- descarga segura.

### Plantillas
- descargar plantilla ejemplo;
- guardar mapeos frecuentes.

---

## 6. Consultas / Leads

Listado:

- fecha;
- propiedad;
- nombre;
- teléfono;
- email;
- tipo de consulta;
- origen/UTM;
- estado;
- responsable.

Estados configurables iniciales:

- nueva;
- contactada;
- visita agendada;
- cerrada;
- descartada.

Debe existir exportación y política de retención/privacidad.

---

## 7. Ubicaciones

Gestión de:

- regiones;
- comunas;
- sectores/barrios;
- aliases para importación;
- páginas indexables habilitadas;
- contenido editorial opcional para landing local.

Evitar taxonomías duplicadas por diferencias de escritura.

---

## 8. Clasificaciones

- tipos de propiedad;
- operaciones;
- estados;
- características configurables;
- etiquetas controladas si se habilitan.

No permitir que el usuario cree estructuras incoherentes sin advertencia.

---

## 9. Multimedia

Vista enfocada en activos asociados a propiedades:

- imágenes sin alt;
- imágenes huérfanas;
- imágenes pesadas;
- propiedades con pocas imágenes;
- videos;
- optimización futura.

No reemplaza Media Library; agrega contexto inmobiliario.

---

## 10. SEO y visibilidad

Panel global:

- reglas de titles;
- descriptions;
- base de URLs;
- sitemap;
- canonical;
- indexación de archivos/taxonomías;
- política de filtros;
- Open Graph;
- schema;
- Organization/RealEstateAgent;
- verificación de configuración;
- compatibilidad/detección de plugins SEO.

Debe advertir, no duplicar silenciosamente, si otro plugin controla la misma salida.

---

## 11. Indicadores

- UF;
- dólar;
- UTM;
- euro;
- última actualización;
- fuente;
- estado del caché;
- actualizar manualmente;
- comportamiento fallback.

La API externa nunca debe bloquear el render público crítico.

---

## 12. Calidad del catálogo

Auditoría operativa interna:

- sin precio;
- sin ubicación;
- sin imágenes;
- imágenes sin alt;
- descripción muy corta;
- duplicados por código;
- links/videos inválidos;
- SEO incompleto;
- propiedades desactualizadas;
- datos contradictorios.

Filtros + acción directa para corregir.

---

## 13. Actividad

Bitácora enfocada en eventos relevantes:

- propiedad creada;
- precio modificado;
- estado modificado;
- propiedad archivada;
- importación;
- exportación;
- cambios de ajustes;
- acciones de seguridad críticas.

No almacenar secretos ni contenido sensible innecesario.

---

## 14. Ayuda

Centro pensado para usuarios no técnicos.

Contenido mínimo:

- primeros pasos;
- crear propiedad;
- actualizar precio;
- cambiar disponibilidad;
- fotografías;
- videos;
- destacar en inicio;
- carga masiva;
- resolver errores comunes;
- conceptos SEO básicos;
- preguntas frecuentes.

Debe incluir ayuda contextual en cada pantalla y enlaces directos al artículo relevante.

---

## 15. Herramientas

Solo capacidades técnicas/operativas:

- reconstruir índice;
- revisar integridad;
- regenerar rewrites cuando sea seguro;
- migradores;
- diagnóstico;
- exportar diagnóstico sin secretos;
- modo dry-run;
- limpieza controlada de datos temporales.

Acciones destructivas requieren confirmación explícita, capability y nonce.

---

## 16. Ajustes

Pestañas propuestas:

### General
- datos inmobiliaria;
- logo/datos estructurados relacionados al negocio;
- moneda;
- unidades;
- zona/país.

### Propiedades
- estados;
- campos opcionales;
- URL base;
- comportamiento de archivos.

### Contacto
- email;
- WhatsApp;
- teléfono;
- formularios.

### SEO
- configuración global;
- integración con plugin SEO.

### Integraciones
- mapas;
- indicadores;
- webhooks/API futuros.

### Rendimiento
- caché interno;
- herramientas de índice;
- opciones avanzadas justificadas.

### Privacidad
- retención de leads/logs;
- consentimiento/formularios.

### Avanzado
- solo opciones que realmente necesiten administración técnica.

---

## Roles administrativos previstos

### Administrador WordPress
Acceso total.

### Administrador inmobiliario
Puede gestionar propiedades, destacados, importaciones, leads y configuraciones inmobiliarias permitidas sin administrar todo WordPress.

### Editor de propiedades
Puede crear/editar propiedades y multimedia, pero no modificar ajustes sensibles, migraciones o seguridad.

### Gestor de leads opcional
Puede consultar y actualizar leads sin editar propiedades.

Las capabilities específicas se definirán en el core y se probarán individualmente.
