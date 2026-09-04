# Evidencia — Fase 3 / Import-Export

Esta carpeta concentra la evidencia auditable de **Fase 3 — Import/Export**.

Estado de entrada: `PLANNING / ENTRY APPROVED`.

Issue de planificación: #43  
Backlog: `docs/PHASE-3-BACKLOG.md`

## Regla de evidencia

Cada PR funcional de Fase 3 debe crear o actualizar un archivo de evidencia propio antes de pasar a `DONE`.

Formato sugerido:

```text
PR-3.1-IMPORT-DOMAIN-CSV.md
PR-3.2-MAPPING-DRY-RUN.md
PR-3.3-BATCH-PERSISTENCE.md
PR-3.4-IMPORT-UI.md
PR-3.5-JSON.md
PR-3.6-XLSX.md
PR-3.7-REMOTE-MEDIA.md
PR-3.8-CSV-XLSX-EXPORT.md
PR-3.9-ROLLBACK.md
PR-3.10-QUALITY-GATE.md
```

## Contenido mínimo por evidencia

Cada documento debe registrar, cuando aplique:

- Issue y PR;
- rama y SHA validado;
- requisitos cubiertos;
- archivos/formatos y fixtures usados;
- casos positivos y negativos;
- conteos de filas y mutaciones esperadas;
- resultados de unit/integration/E2E;
- performance y memoria cuando sean relevantes;
- seguridad: capabilities, nonce, validación, SSRF, formula injection u otros vectores del alcance;
- findings detectados y su resolución;
- riesgos/deuda diferida;
- artifact/checksum;
- estado de producción.

## Datos de prueba

No se deben subir exportaciones reales de clientes ni datos personales de producción para demostrar el importador.

Usar fixtures sintéticos y sanitizados. Los reportes de error tampoco deben conservar payloads completos si contienen datos innecesarios.

## Quality Gate final

`PR-3.10-QUALITY-GATE.md` será la evidencia de salida de Fase 3 y deberá demostrar regresión de Fase 1/2 además de seguridad, performance, resume/idempotencia, round-trip, archivos malformados y controles específicos de importación/exportación.

## Producción

La Fase 3 se desarrolla y valida fuera de `propiedadesmartinez.cl`. El sitio productivo no debe modificarse como parte de estos PR salvo una solicitud explícita posterior y un plan de despliegue separado.
