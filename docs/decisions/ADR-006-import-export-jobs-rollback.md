# ADR-006 — Importación, XLSX, jobs y rollback

Estado: accepted  
Fecha: 2026-09-03  
Decisiones: D30–D38  
Issue: #2

## Contexto

La carga masiva es una función central y debe crear/actualizar cientos o miles de propiedades sin duplicados, timeouts o escrituras opacas.

## Decisión

- Formatos de v1: XLSX, CSV y JSON.
- XLSX mediante **PhpSpreadsheet**, aislada en el módulo Import/Export y fuera del frontend.
- Upsert: `external_id` cuando el perfil lo declare; luego `property_code`; nunca título/dirección.
- Celdas vacías conservan el valor existente por defecto; borrar requiere modo explícito.
- Dry-run obligatorio antes de importaciones masivas.
- Ejecución por lotes reanudables con progreso persistido.
- Backend inicial de jobs: WP-Cron/infraestructura propia ligera; interfaz desacoplada para poder adoptar Action Scheduler si se justifica.
- Descargas remotas protegidas contra SSRF y validadas por protocolo, host, MIME, tamaño, timeout y límites.
- Rollback best-effort; no se promete reversión absoluta si existen cambios manuales posteriores.

## Alternativas consideradas

- Importar en un único request: simple, no escala.
- Action Scheduler obligatorio: robusto pero introduce dependencia antes de necesitarla.
- Identificar por título: alto riesgo de duplicados/colisiones.

## Consecuencias

### Positivas
- Importaciones trazables e idempotentes.
- Menor riesgo operativo.
- Escala progresiva.

### Trade-offs
- PhpSpreadsheet aumenta tamaño del paquete.
- Jobs reanudables requieren estado e idempotencia cuidadosos.

## Impacto

- Seguridad: importador se trata como superficie crítica.
- Performance: lotes y sincronización incremental del índice.
- Datos: mismas reglas de validación para formulario manual e importador.

## Revisión futura

Benchmark de PhpSpreadsheet y tamaño del ZIP antes de 1.0; sustituir solo mediante ADR si existe una alternativa claramente superior.