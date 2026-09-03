# Centro de ayuda de WLA Inmo

## Objetivo

WLA Inmo debe incluir ayuda contextual para que una persona con conocimientos básicos pueda crear, actualizar y administrar propiedades sin depender de soporte técnico permanente.

La ayuda no debe existir solo en documentación externa: debe estar integrada al administrador de WordPress.

## Menú Ayuda

```text
WLA Inmo > Ayuda
```

Contenido inicial:

1. Primeros pasos.
2. Crear una propiedad.
3. Actualizar una propiedad.
4. Cambiar precio y estado.
5. Agregar fotografías.
6. Agregar videos.
7. Publicar y revisar una propiedad.
8. Destacar propiedades en el inicio.
9. Importar propiedades masivamente.
10. Resolver errores de importación.
11. SEO básico de una propiedad.
12. Consultas y solicitudes de visita.
13. Preguntas frecuentes.

## Asistente de primeros pasos

La primera vez que un administrador inmobiliario entra al plugin se puede mostrar un checklist no invasivo:

```text
Bienvenido a WLA Inmo

□ Revisar datos de la inmobiliaria
□ Crear primera propiedad
□ Configurar contacto y WhatsApp
□ Revisar página de propiedades
□ Configurar indicadores económicos
□ Revisar SEO general
```

El usuario puede cerrar el asistente y volver a abrirlo desde Ayuda.

## Guía: crear una nueva propiedad

La ayuda debe explicar el flujo con lenguaje simple:

### Paso 1 — Crear

Ir a:

```text
WLA Inmo > Nueva propiedad
```

### Paso 2 — Datos básicos

Completar primero:

- título;
- código;
- operación;
- tipo;
- estado;
- precio.

Explicación visible:

> El código identifica de forma única la propiedad. No utilices el mismo código en dos propiedades distintas.

### Paso 3 — Ubicación

Completar región, comuna y sector. La dirección exacta puede mantenerse privada si la inmobiliaria no desea publicarla.

### Paso 4 — Características

Agregar superficies, dormitorios, baños, estacionamientos y atributos relevantes.

### Paso 5 — Fotografías

Seleccionar una imagen principal y ordenar la galería.

Recomendación mostrada al usuario:

> Usa fotografías claras y agrega una descripción breve a las imágenes importantes. Esto mejora accesibilidad y comprensión del contenido.

### Paso 6 — Descripción

Escribir información útil, concreta y verificable.

### Paso 7 — Revisar

Antes de publicar, WLA Inmo mostrará una lista de elementos completos y pendientes.

### Paso 8 — Publicar

Presionar `Publicar` o `Actualizar`.

## Guía: actualizar una propiedad

Flujo recomendado:

```text
WLA Inmo > Propiedades > Buscar código o nombre > Editar
```

Cambios comunes:

- precio;
- estado;
- fotografías;
- descripción;
- disponibilidad.

Al guardar, todas las vistas que utilicen los datos de WLA Inmo deben actualizarse desde la misma fuente de verdad.

## Ayuda contextual

Cada sección del formulario debe incluir enlaces o tooltips breves:

```text
Código                [?]
Precio principal      [?]
Dirección pública     [?]
Texto alternativo     [?]
Indexar en Google     [?]
```

Los tooltips deben explicar el concepto en una o dos frases; los detalles completos apuntan al Centro de ayuda.

## Ayuda en importaciones

El importador debe acompañar al usuario durante todo el flujo:

```text
1. Subir archivo
2. Relacionar columnas
3. Revisar datos
4. Corregir problemas
5. Importar
```

Cada advertencia debe incluir una acción sugerida.

Ejemplo:

```text
Fila 34 — Código vacío
La propiedad necesita un código único para poder actualizarse en futuras importaciones.
[Editar fila]
```

## Glosario

La ayuda debe incluir definiciones simples:

- Propiedad destacada.
- Operación.
- Código de propiedad.
- Precio principal.
- Meta description.
- URL canónica.
- Texto alternativo.
- Indexar/no indexar.
- Importación y actualización masiva.

## Soporte técnico

El plugin podrá incluir una pantalla de diagnóstico exportable para soporte, sin exponer secretos.

Debe poder informar:

- versión de WordPress;
- versión de PHP;
- versión de WLA Inmo;
- tema activo;
- estado de tablas del plugin;
- jobs pendientes;
- última importación;
- errores recientes sanitizados.

Nunca debe incluir contraseñas, tokens, claves API o datos sensibles de usuarios.