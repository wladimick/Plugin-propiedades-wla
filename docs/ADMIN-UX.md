# Administración y experiencia de usuario

## Objetivo

La administración de WLA Inmo debe poder ser utilizada por una persona que conozca el negocio inmobiliario aunque no tenga conocimientos técnicos de WordPress.

## Menú principal

```text
WLA Inmo
├── Dashboard
├── Propiedades
├── Nueva propiedad
├── Importar
├── Inicio y destacadas
├── Consultas
├── Indicadores
├── Calidad SEO
├── Ayuda
├── Herramientas
└── Ajustes
```

## Dashboard

Debe responder rápidamente:

- cuántas propiedades existen;
- cuántas están disponibles;
- venta/arriendo;
- destacadas;
- no disponibles;
- fichas incompletas;
- consultas recientes;
- importaciones recientes;
- problemas que requieren atención.

Ejemplo:

```text
156 Propiedades   112 Venta   31 Arriendo   13 Terrenos

Calidad del catálogo: 91%
12 sin descripción SEO
5 sin ubicación
3 sin fotografías suficientes
2 sin precio
```

## Listado de propiedades

Columnas prioritarias:

- selector.
- fotografía.
- título.
- código.
- operación.
- estado.
- ubicación.
- precio.
- calidad.
- última actualización.
- acciones.

Filtros rápidos:

```text
Todas | Venta | Arriendo | Destacadas | No disponibles | Incompletas
```

Acciones:

- Editar.
- Vista previa.
- Duplicar.
- Marcar destacada.
- Cambiar estado.
- Archivar.

## Formulario de propiedad

No se debe mostrar terminología técnica innecesaria.

Secciones sugeridas:

### 1. Información principal

- Título.
- Código.
- Estado.
- Operación.
- Tipo.

### 2. Precio

- Precio CLP.
- Precio UF.
- Precio USD.
- Precio a consultar.
- Precio principal.

### 3. Ubicación

- Región.
- Comuna.
- Sector.
- Dirección.
- Mapa/coordenadas.

### 4. Características

- Superficies.
- Dormitorios.
- Baños.
- Estacionamientos.
- Extras.

### 5. Fotografías y videos

- Imagen principal.
- Galería drag & drop.
- Alt text recomendado.
- Videos.

### 6. Descripción

- Resumen.
- Descripción completa.
- Observaciones.

### 7. Publicación

- Destacada.
- Mostrar en portada.
- Orden.
- Indexación.

### 8. SEO

- Vista previa de título SEO.
- Meta description.
- URL.
- Estado del Schema.
- Recomendaciones simples.

## Guardado

Debe ofrecer:

- Guardar borrador.
- Publicar/Actualizar.
- Vista previa.
- Guardar y continuar.

Al guardar se deben mostrar mensajes claros, por ejemplo:

```text
Propiedad actualizada correctamente.
2 recomendaciones pendientes: falta texto alternativo en 3 imágenes y meta description.
```

## Edición rápida

Desde el listado debe poder modificarse sin abrir la ficha completa:

- precio.
- estado.
- operación.
- destacada.
- código.

## Acciones masivas

- cambiar estado.
- cambiar operación.
- destacar/quitar destacada.
- archivar.
- exportar.

## Roles

Roles sugeridos:

### Administrador inmobiliario

Puede administrar todo WLA Inmo sin acceder necesariamente a configuración crítica de WordPress.

### Editor de propiedades

Puede crear y actualizar propiedades, imágenes y contenido, pero no modificar ajustes globales, importadores avanzados o seguridad.

## Prevención de errores

- Confirmaciones solo para acciones destructivas.
- Autosave donde sea razonable.
- Validación inline antes de publicar.
- Advertencias, no bloqueos, para recomendaciones SEO.
- No permitir códigos duplicados sin confirmación/resolución.
- Estado visual claro de propiedad publicada/borrador/no disponible.

## Diseño

- Interfaz nativa WordPress cuando ayude a consistencia.
- Componentes propios solo donde mejoren claramente la experiencia.
- Responsive para tablets.
- Accesibilidad WCAG como requisito de diseño.
- No esconder funciones esenciales detrás de iconos sin etiqueta.