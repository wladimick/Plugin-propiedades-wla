# Importación, exportación y carga masiva

## Objetivo

Permitir crear o actualizar cientos o miles de propiedades de manera segura, repetible y comprensible.

## Formatos iniciales

- XLSX.
- CSV UTF-8.
- JSON.

## Flujo del importador

```text
Subir archivo
   ↓
Detectar columnas
   ↓
Mapear campos
   ↓
Validar
   ↓
Simular
   ↓
Confirmar
   ↓
Procesar por lotes
   ↓
Informe final
```

## Mapeo

El usuario debe poder relacionar columnas externas con campos WLA Inmo.

Ejemplo:

```text
Archivo               WLA Inmo
codigo              → Código
nombre              → Título
precio              → Precio CLP
uf                  → Precio UF
comuna              → Comuna
m2_terreno          → Superficie terreno
foto_1              → Galería
foto_2              → Galería
```

El plugin podrá guardar perfiles de mapeo para proveedores recurrentes.

## Identificación y upsert

Prioridad sugerida:

1. `external_id` cuando el origen lo provea y el perfil lo defina.
2. `property_code`.
3. nunca deducir identidad únicamente desde título/dirección.

La simulación debe indicar:

```text
298 nuevas
21 actualizarán existentes
4 con advertencias
2 con errores
```

## Modo simulación

Antes de escribir datos se debe validar:

- códigos duplicados dentro del archivo;
- coincidencias con propiedades existentes;
- campos obligatorios;
- números inválidos;
- taxonomías desconocidas;
- URLs de imágenes inválidas;
- formatos de coordenadas;
- estados no soportados.

La simulación no crea posts ni descarga imágenes.

## Procesamiento por lotes

Nunca procesar un archivo grande en un único request.

El trabajo debe dividirse en lotes y registrar progreso.

```text
Procesando 120 / 500
```

Debe ser posible retomar una importación interrumpida cuando el estado almacenado sea consistente.

## Imágenes

Opciones de origen:

- URLs remotas autorizadas.
- referencias a archivos previamente cargados.
- ZIP de imágenes en una etapa posterior.

Reglas:

- descargar una sola vez cuando ya exista una coincidencia confiable;
- validar MIME y tamaño;
- generar tamaños WordPress;
- asociar attachment a propiedad;
- registrar errores sin abortar todo el lote cuando sean recuperables;
- no permitir esquemas de URL peligrosos.

## Actualizaciones parciales

El usuario debe elegir el comportamiento de columnas vacías:

```text
( ) Vacío significa borrar dato actual
(*) Vacío significa conservar dato actual
```

La opción segura por defecto debe ser conservar el valor existente.

## Historial

Cada importación debe registrar:

- ID.
- archivo original o hash/referencia.
- usuario.
- fecha.
- perfil de mapeo.
- cantidad total.
- creadas.
- actualizadas.
- omitidas.
- errores.
- advertencias.
- duración.

## Reversión

Cuando sea técnicamente seguro, una importación debe poder revertirse con límites claros.

La reversión debe distinguir:

- propiedades creadas por esa importación;
- campos modificados en propiedades existentes;
- media descargada exclusivamente para esa importación.

No se debe prometer rollback completo si hubo cambios posteriores de usuarios. En esos casos se debe mostrar que la reversión podría sobrescribir trabajo reciente y requerir confirmación avanzada.

## Exportación

Exportar a:

- XLSX.
- CSV.
- JSON.

Filtros de exportación:

- todas.
- disponibles.
- venta/arriendo.
- tipo.
- ubicación.
- rango de fechas.
- selección manual.

El JSON puede servir además como formato de respaldo/intercambio de WLA Inmo.

## Seguridad

- Solo usuarios con capability de importación.
- Nonce.
- Límite de tamaño configurable.
- Validación de extensión y MIME.
- CSV tratado como datos, nunca como código.
- Escape de celdas para minimizar CSV/Spreadsheet Formula Injection al exportar.
- Logs sin datos secretos.

## Rendimiento

- jobs por lotes;
- timeouts controlados;
- reintentos limitados en media remota;
- no recalcular índices completos por cada fila;
- sincronización incremental de la tabla de búsqueda.