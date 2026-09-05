# Evidencia — PR 3.6 UI de importación e historial

Estado: `IN_PROGRESS / QA_PENDING`.

Issue: #56  
Rama: `feat/phase3-import-ui`

## Objetivo

Exponer el pipeline canónico de Fase 3.1–3.5 mediante una UI administrativa usable por una persona no técnica, manteniendo dry-run obligatorio, identidad explícita, checkpoints, reanudación e idempotencia.

## Primera implementación

- `WLA Inmo → Importar / Exportar` deja de depender del placeholder y enruta a `ImportExportPage`;
- wizard server-rendered: Subir → Mapear → Validar → Simular → Confirmar → Procesar → Informe;
- capability exacta `import_wla_properties`;
- mutaciones por `admin-post.php` con nonce específico;
- CSV como único formato de entrada de este PR;
- `Workspace` temporal fuera del webroot habitual mediante `get_temp_dir()`;
- nombre de ruta generado por UUID de servidor; el nombre original nunca forma parte del path;
- límite de 10 MiB y 10.000 filas para esta etapa;
- extensión + MIME + parser CSV UTF-8;
- preview de máximo 5 filas, renderizado de forma acotada y sin persistir payload de filas en transients;
- mapping solo contra `TargetRegistry`;
- `DryRunEngine` obligatorio antes de confirmar;
- snapshot de mapping mediante `MappingProfileCodec`;
- SHA-256 revisado nuevamente antes de confirmar;
- batch confirmado a través del state machine ya persistido;
- procesamiento únicamente mediante `BatchRunner` en slices de 25 filas / ~4 segundos;
- reanudación desde `cursor_offset` ya implementado en PR 3.5;
- cancelación solo desde `confirmed`, `paused` o `failed`;
- historial bounded/paginado mediante `BatchHistoryRepository`;
- usuario importador ve sus batches; usuarios con `manage_wla_inmo_tools` pueden ver todos;
- CSS propio cargado solo en la pantalla de importación;
- ayuda contextual específica.

## Privacidad / seguridad

La UI no recibe paths de filesystem desde request. El request solo transporta tokens/UUIDs generados por servidor y el servidor reconstruye las rutas temporales tras validarlos.

El historial no consulta ni renderiza:

- `profile_json`;
- `source_hash`;
- payload de filas;
- direcciones privadas;
- notas internas;
- tokens o credenciales.

El dry-run persistido en transient conserva únicamente conteos y códigos/targets de hallazgos; no conserva valores de las filas.

## Tests iniciales

`tests/smoke/import-ui.php` congela contratos de:

- capability;
- nonces;
- dry-run obligatorio;
- snapshot/hash antes de confirmar;
- uso de `BatchRunner`;
- paths controlados por servidor;
- límites de archivo/filas;
- MIME/extensión;
- historial paginado;
- ausencia de requests remotos;
- responsive base.

## QA pendiente

- PHP syntax;
- WPCS;
- PHPStan;
- PHPUnit;
- smoke completo;
- release ZIP smoke;
- integración WordPress/MySQL del flujo de UI;
- E2E browser de wizard, permisos, responsive y accesibilidad;
- review y corrección de findings;
- actualización final de artefacto/checksum;
- estado `QA_PASSED / READY_TO_MERGE`.

## Fuera de alcance

- JSON WLA;
- XLSX;
- media remota;
- exportación final;
- rollback completo;
- cola/background definitiva;
- migración WooCommerce/ACF.

## Producción

`propiedadesmartinez.cl` permanece sin cambios.
