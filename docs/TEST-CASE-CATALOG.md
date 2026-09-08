# Catálogo base de casos de prueba

Este catálogo define los casos mínimos que deben existir a medida que cada módulo sea implementado. `PLANNED` significa documentado pero aún no automatizado/ejecutado. `DONE` requiere automatización o evidencia reproducible enlazada desde el PR/fase correspondiente.

## Core

| ID | Caso | Tipo | Estado |
|---|---|---|---|
| CORE-T001 | Activar plugin en instalación limpia | Integration | PLANNED |
| CORE-T002 | Desactivar plugin sin pérdida de datos | Integration | PLANNED |
| CORE-T003 | Registrar CPT propiedad | Integration | PLANNED |
| CORE-T004 | Registrar taxonomías | Integration | PLANNED |
| CORE-T005 | Flush de rewrites solo cuando corresponde | Integration | PLANNED |
| CORE-T006 | Plugin funciona sin WooCommerce | Integration | PLANNED |
| CORE-T007 | Plugin funciona sin Elementor | Integration | PLANNED |
| CORE-T008 | Plugin funciona sin ACF | Integration | PLANNED |
| CORE-T009 | Namespace/prefijos sin colisiones conocidas | Static/Integration | PLANNED |
| CORE-T010 | Compatibilidad PHP mínimo | CI | PLANNED |
| CORE-T011 | Compatibilidad WP mínimo | CI | PLANNED |
| CORE-T012 | Upgrade conserva datos | Integration | PLANNED |

## Roles y permisos

| ID | Caso | Tipo | Estado |
|---|---|---|---|
| AUTH-T001 | Admin puede gestionar todo WLA Inmo | Integration/E2E | PLANNED |
| AUTH-T002 | Administrador inmobiliario puede gestionar propiedades | E2E | PLANNED |
| AUTH-T003 | Editor no puede cambiar ajustes sensibles | Security/E2E | PLANNED |
| AUTH-T004 | Usuario sin capability no puede editar propiedad | Security | PLANNED |
| AUTH-T005 | REST respeta permission_callback | Security | PLANNED |
| AUTH-T006 | Nonce inválido bloquea acción admin | Security | PLANNED |
| AUTH-T007 | Gestor de leads no accede a configuración | Security/E2E | PLANNED |

## Propiedades / Admin

| ID | Caso | Tipo | Estado |
|---|---|---|---|
| ADMIN-T001 | Crear propiedad mínima válida | E2E | PLANNED |
| ADMIN-T002 | Publicar propiedad completa | E2E | PLANNED |
| ADMIN-T003 | Editar precio y reflejar una sola fuente de verdad | Integration/E2E | PLANNED |
| ADMIN-T004 | Cambiar estado disponibilidad | E2E | PLANNED |
| ADMIN-T005 | Duplicar propiedad genera nuevo código requerido | E2E | PLANNED |
| ADMIN-T006 | Archivar propiedad | E2E | PLANNED |
| ADMIN-T007 | Edición rápida | E2E | PLANNED |
| ADMIN-T008 | Acción masiva de estado | E2E | PLANNED |
| ADMIN-T009 | Código duplicado produce error claro | Unit/Integration | PLANNED |
| ADMIN-T010 | Campo numérico rechaza formato inválido | Unit/E2E | PLANNED |
| ADMIN-T011 | XSS en título/descripción queda sanitizado/escapado | Security | PLANNED |
| ADMIN-T012 | Notas internas nunca aparecen públicamente | Security/E2E | PLANNED |
| ADMIN-T013 | Completitud detecta campos faltantes | Unit | PLANNED |
| ADMIN-T014 | Ayuda contextual abre artículo correcto | E2E | PLANNED |
| ADMIN-T015 | Flujo de creación navegable por teclado | Accessibility | PLANNED |
| ADMIN-T016 | Mensajes de validación asociados a campos | Accessibility | PLANNED |

## Multimedia

| ID | Caso | Tipo | Estado |
|---|---|---|---|
| MEDIA-T001 | Definir imagen principal | E2E | PLANNED |
| MEDIA-T002 | Agregar/reordenar galería | E2E | PLANNED |
| MEDIA-T003 | Eliminar imagen de galería sin afectar otra propiedad | Integration | PLANNED |
| MEDIA-T004 | Rechazar MIME no permitido | Security | PLANNED |
| MEDIA-T005 | Imagen con extensión falsa se rechaza | Security | PLANNED |
| MEDIA-T006 | Alt text se guarda y renderiza | Accessibility/SEO | PLANNED |
| MEDIA-T007 | Video válido renderiza | E2E | PLANNED |
| MEDIA-T008 | Video inválido no rompe ficha | E2E | PLANNED |
| MEDIA-T009 | Galería responsive | Visual | PLANNED |

## Importación

| ID | Caso | Tipo | Estado |
|---|---|---|---|
| IMPORT-T001 | CSV válido dry-run | Integration | PLANNED |
| IMPORT-T002 | XLSX válido dry-run | Integration | PLANNED |
| IMPORT-T003 | JSON válido dry-run | Integration | DONE — PR #60 |
| IMPORT-T004 | Archivo vacío | Negative | PLANNED |
| IMPORT-T005 | Archivo corrupto | Negative | PLANNED |
| IMPORT-T006 | Mapeo automático de columnas conocidas | Unit/Integration | PLANNED |
| IMPORT-T007 | Mapeo manual | E2E | PLANNED |
| IMPORT-T008 | Código nuevo crea propiedad | Integration | PLANNED |
| IMPORT-T009 | Código existente actualiza propiedad | Integration | PLANNED |
| IMPORT-T010 | Reimportar mismo archivo no duplica | Integration | PLANNED |
| IMPORT-T011 | Código duplicado dentro del mismo archivo | Negative | PLANNED |
| IMPORT-T012 | Columna desconocida no rompe | Integration | PLANNED |
| IMPORT-T013 | Campo obligatorio faltante se reporta | Negative | PLANNED |
| IMPORT-T014 | Precio inválido se reporta | Negative | PLANNED |
| IMPORT-T015 | HTML/script se neutraliza | Security | PLANNED |
| IMPORT-T016 | URL de imagen 404 se reporta | Integration | PLANNED |
| IMPORT-T017 | URL de imagen timeout no aborta todo lote | Resilience | PLANNED |
| IMPORT-T018 | SSRF a localhost/red privada se bloquea | Security | PLANNED |
| IMPORT-T019 | MIME remoto inválido se bloquea | Security | PLANNED |
| IMPORT-T020 | Importación interrumpida puede reanudar/recuperar | Resilience | PLANNED |
| IMPORT-T021 | Lote grande no excede estrategia de memoria | Performance | PLANNED |
| IMPORT-T022 | Historial registra nuevas/actualizadas/errores | Integration | PLANNED |
| IMPORT-T023 | Usuario sin permiso no importa | Security | DONE — JSON PR #60 |
| IMPORT-T024 | Exportación evita CSV formula injection | Security | PLANNED |
| IMPORT-T025 | Exportación respeta filtros seleccionados | Integration | PLANNED |

### Fase 3.7 — JSON WLA versionado

Evidencia canónica: `docs/evidence/phase-3/PR-3.7-JSON-WLA.md`.

| ID | Caso | Tipo | Estado |
|---|---|---|---|
| JSON-T001 | Fixture WLA v1 UTF-8 normaliza a filas canónicas | Unit/Integration | DONE |
| JSON-T002 | `format_version` ausente se rechaza | Unit/Negative | DONE |
| JSON-T003 | Versión futura/no soportada se rechaza | Unit/Negative | DONE |
| JSON-T004 | Root/shape inválido se rechaza | Unit/Negative | DONE |
| JSON-T005 | Colección vacía inválida se reporta de forma controlada | Unit/Negative | DONE |
| JSON-T006 | JSON malformado no deja source normalizado | Unit/Negative | DONE |
| JSON-T007 | Límite máximo de bytes se aplica antes de procesar | Unit/Security | DONE |
| JSON-T008 | Límite de profundidad se aplica | Unit/Security | DONE |
| JSON-T009 | Límite de cantidad de propiedades se aplica | Unit/Performance | DONE |
| JSON-T010 | Target desconocido no crea meta arbitraria | Unit/Security | DONE |
| JSON-T011 | Arrays solo se aceptan para targets múltiples | Unit | DONE |
| JSON-T012 | Source normalizado se crea `0600` | Unit/Security | DONE |
| JSON-T013 | Fila NDJSON sobre límite se rechaza y elimina | Unit/Security | DONE |
| JSON-T014 | `exported_at` inválido no deja archivo huérfano | Unit/Security | DONE |
| JSON-T015 | SHA-256 detecta tampering | Unit/Security | DONE |
| JSON-T016 | Resume por `cursor_offset` físico | Unit/Resilience | DONE |
| JSON-T017 | Dry-run JSON no muta catálogo | Integration | DONE |
| JSON-T018 | Batch JSON reutiliza `BatchRunner` / `RowExecutor` | Integration | DONE |
| JSON-T019 | `source_format=json` persiste y schema v3 conserva CSV default | Unit/Integration | DONE |
| JSON-T020 | Export JSON excluye campos privados por defecto | Unit/Integration/Security | DONE |
| JSON-T021 | Round-trip export → import → dry-run | Integration | DONE |
| JSON-T022 | Usuario sin capability recibe 403 | Security/Integration | DONE |
| JSON-T023 | Nonce ausente/inválido bloquea mutaciones JSON | Security/Integration | DONE |
| JSON-T024 | Dataset 100 propiedades medido | Performance | DONE |
| JSON-T025 | Dataset 1.000 propiedades medido | Performance | DONE |
| JSON-T026 | Dataset 5.000 propiedades medido | Performance | DONE |
| JSON-T027 | WP 6.6.2 / PHP 8.1 | CI/Compatibility | DONE |
| JSON-T028 | WP latest / PHP 8.3 | CI/Compatibility | DONE |
| JSON-T029 | Regresión CSV/Fase 1/2/3.1–3.6 | CI | DONE |
| JSON-T030 | Review sin P0/P1 abiertos y threads resueltos | Review/Security | DONE |

Resultados de performance del head funcional `0eaeda0f02da44ae25018b6ba167b8b1dddda5a4`: 5.000 filas <1 s en ambas matrices y peak delta observado de 8 MiB. Artifacts/checksums quedan registrados en la evidencia 3.7.

### Fase 3.11 — Rollback seguro best-effort

Evidencia canónica: `docs/evidence/phase-3/PR-3.11-ROLLBACK.md`.

| ID | Caso | Tipo | Estado |
|---|---|---|---|
| ROLLBACK-T001 | Snapshot determinista y hash estable | Unit | DONE |
| ROLLBACK-T002 | Meta inexistente se distingue de meta existente vacía | Unit | DONE |
| ROLLBACK-T003 | Journal único por batch + source row | Unit/Integration | DONE |
| ROLLBACK-T004 | Intent se registra antes de `RowExecutor` | Static/Integration | DONE |
| ROLLBACK-T005 | `after` se finaliza antes del checkpoint | Static/Integration | DONE |
| ROLLBACK-T006 | Create seguro se elimina y limpia identidad/proyecciones | WP/MySQL Integration | DONE |
| ROLLBACK-T007 | Update seguro restaura solo targets tocados | WP/MySQL Integration | DONE |
| ROLLBACK-T008 | Cambio posterior en target tocado bloquea y conserva dato humano | WP/MySQL Integration | DONE |
| ROLLBACK-T009 | Cambio posterior fuera de scope no bloquea ni se pisa | WP/MySQL Integration | DONE |
| ROLLBACK-T010 | Create modificada después bloquea eliminación | WP/MySQL Integration | DONE |
| ROLLBACK-T011 | Preview es read-only | Integration/Security | DONE |
| ROLLBACK-T012 | Preview stale no puede iniciar rollback | WP/MySQL Integration | DONE |
| ROLLBACK-T013 | Rollback repetido no vuelve a mutar | WP/MySQL Integration | DONE |
| ROLLBACK-T014 | Batch no `completed` no puede iniciar rollback | Unit/Integration | DONE |
| ROLLBACK-T015 | Journal incompleto/inconsistente falla cerrado | Unit/Integration | DONE |
| ROLLBACK-T016 | Crash después de create y antes de checkpoint conserva semántica `created` | WP/MySQL Integration | DONE |
| ROLLBACK-T017 | Galería anterior se restaura por IDs canónicos | WP/MySQL Integration | DONE |
| ROLLBACK-T018 | Featured image anterior se restaura por attachment ID | WP/MySQL Integration | DONE |
| ROLLBACK-T019 | Attachments ambiguos/nuevos no se eliminan | WP/MySQL Integration | DONE |
| ROLLBACK-T020 | Lock impide dos runners simultáneos del mismo batch | WP/MySQL Integration | DONE |
| ROLLBACK-T021 | Capability `rollback_wla_imports` separada y admin-only por defecto | Security/Integration | DONE |
| ROLLBACK-T022 | Nonces preview/confirm/run son independientes | Security/Integration | DONE |
| ROLLBACK-T023 | IDOR respeta ownership o `manage_tools` | Security/Integration | DONE |
| ROLLBACK-T024 | Activity no expone snapshots, UUID crudo ni payload privado | Security/Integration | DONE |
| ROLLBACK-T025 | Matriz WP 6.6.2/PHP 8.1 + latest/PHP 8.3 sobre MySQL 8 | CI/Compatibility | DONE |

### Fase 3.12 — Quality Gate final

Evidencia canónica: `docs/evidence/phase-3/PR-3.12-PHASE-3-QUALITY-GATE.md`.

| ID | Caso | Tipo | Estado |
|---|---|---|---|
| PHASE3-T001 | Gate maestro invoca las 16 suites reales mediante `workflow_call` | CI/Architecture | DONE |
| PHASE3-T002 | Summary falla si un child gate no termina en `success` | CI/Negative | DONE |
| PHASE3-T003 | Manifest registra PR head real, base, checkout SHA y run | CI/Audit | DONE |
| PHASE3-T004 | Phase 1 WPCS/PHPStan/PHPUnit/smoke/build sin regresión | CI/Regression | DONE |
| PHASE3-T005 | Administración/Core Fase 1–2 sin regresión | CI/E2E | DONE |
| PHASE3-T006 | CSV mapping/dry-run/confirm/run/resume cubierto | Integration/E2E | DONE |
| PHASE3-T007 | Identity/idempotencia/checkpoints cubiertos | Integration/Resilience | DONE |
| PHASE3-T008 | JSON WLA v1 round-trip/privacy/tampering cubierto | Integration/Security | DONE |
| PHASE3-T009 | XLSX preflight/normalización bounded/build cubierto | Integration/Security | DONE |
| PHASE3-T010 | Media remota SSRF/MIME/retry/persistencia cubierta | Security/Integration | DONE |
| PHASE3-T011 | Rollback safe/blocked/stale/crash/media/lock cubierto | Security/Resilience | DONE |
| PHASE3-T012 | Capabilities/nonces/IDOR cubiertos | Security | DONE |
| PHASE3-T013 | WP 6.6.2/PHP 8.1/MySQL 8 | CI/Compatibility | DONE |
| PHASE3-T014 | WP latest/PHP 8.3/MySQL 8 | CI/Compatibility | DONE |
| PHASE3-T015 | JSON 100/1k/5k con peak memory registrado | Performance | DONE |
| PHASE3-T016 | Administración 100/1k/5k con queries/timing registrado | Performance | DONE |
| PHASE3-T017 | Playwright Admin/Import UI 14/14 | E2E | DONE |
| PHASE3-T018 | Responsive 1440/1024/768/390/360 | Visual/E2E | DONE |
| PHASE3-T019 | axe WCAG 2.2 AA sin serious/critical en superficies cubiertas | Accessibility | DONE |
| PHASE3-T020 | Artifact summary + ZIPs + SHA-256 asociados al mismo run | CI/Audit | DONE |
| PHASE3-T021 | Review comments/reviews/threads = 0 y P0/P1 = 0 | Review/Security | DONE |
| PHASE3-T022 | Producción no se usa como entorno de QA | Operational | DONE |
| PHASE3-T023 | Fase 3 no se marca DONE antes del merge | Governance | DONE |

Run pre-documentación auditado: `34276667461`, head `aec897f658cbe382cd3667108e3d0026721bd36c`, 16/16 child gates `SUCCESS`. El head documental final debe repetir el gate completo antes del merge.

## Frontend / Templates

| ID | Caso | Tipo | Estado |
|---|---|---|---|
| FRONT-T001 | Archive fallback funciona con tema core | E2E | PLANNED |
| FRONT-T002 | Single fallback funciona con tema core | E2E | PLANNED |
| FRONT-T003 | Override desde tema funciona | Integration | PLANNED |
| FRONT-T004 | WLA Inmo Light funciona | E2E | PLANNED |
| FRONT-T005 | Tema tercero no rompe estilos críticos | Visual | PLANNED |
| FRONT-T006 | Listado pagina correctamente | E2E | PLANNED |
| FRONT-T007 | Filtro operación | E2E | PLANNED |
| FRONT-T008 | Filtro tipo | E2E | PLANNED |
| FRONT-T009 | Filtro comuna | E2E | PLANNED |
| FRONT-T010 | Filtro precio | E2E | PLANNED |
| FRONT-T011 | Combinación filtros conserva URL estable | E2E/SEO | PLANNED |
| FRONT-T012 | Sin resultados muestra estado vacío | E2E | PLANNED |
| FRONT-T013 | Ficha muestra precio correcto | E2E | PLANNED |
| FRONT-T014 | Ficha muestra características correctas | E2E | PLANNED |
| FRONT-T015 | Galería por teclado | Accessibility | PLANNED |
| FRONT-T016 | Mobile 360px | Visual | PLANNED |
| FRONT-T017 | Tablet 768px | Visual | PLANNED |
| FRONT-T018 | Desktop 1440px | Visual | PLANNED |

## SEO / GEO / AEO

| ID | Caso | Tipo | Estado |
|---|---|---|---|
| SEO-T001 | Title de propiedad | Integration | PLANNED |
| SEO-T002 | Meta description | Integration | PLANNED |
| SEO-T003 | Canonical correcto | Integration | PLANNED |
| SEO-T004 | Open Graph | Integration | PLANNED |
| SEO-T005 | Propiedad aparece en sitemap cuando indexable | Integration | PLANNED |
| SEO-T006 | Propiedad noindex no aparece donde no corresponde | Integration | PLANNED |
| SEO-T007 | JSON-LD parseable | Unit/Integration | PLANNED |
| SEO-T008 | Precio en schema coincide con precio visible | Integration | PLANNED |
| SEO-T009 | Ubicación en schema coincide con datos públicos | Integration | PLANNED |
| SEO-T010 | Breadcrumbs correctos | E2E | PLANNED |
| SEO-T011 | Filtros no indexables usan política definida | Integration | PLANNED |
| SEO-T012 | No duplica meta con plugin SEO compatible | Compatibility | PLANNED |
| SEO-T013 | Página local vacía no se indexa/genera según política | Integration | PLANNED |
| SEO-T014 | Propiedad archivada aplica política SEO definida | Integration | PLANNED |

## Leads

| ID | Caso | Tipo | Estado |
|---|---|---|---|
| LEAD-T001 | Enviar solicitud válida | E2E | PLANNED |
| LEAD-T002 | Campos inválidos muestran error | E2E | PLANNED |
| LEAD-T003 | CSRF bloqueado | Security | PLANNED |
| LEAD-T004 | Honeypot/rate limit reduce abuso | Security | PLANNED |
| LEAD-T005 | Lead queda asociado a propiedad | Integration | PLANNED |
| LEAD-T006 | UTM/origen se registra según política | Integration | PLANNED |
| LEAD-T007 | Email falla sin perder registro local | Resilience | PLANNED |
| LEAD-T008 | Usuario no autorizado no ve leads | Security | PLANNED |
| LEAD-T009 | Retención elimina/anonymiza según configuración | Privacy | PLANNED |

## Indicadores

| ID | Caso | Tipo | Estado |
|---|---|---|---|
| IND-T001 | API válida actualiza valores | Integration | PLANNED |
| IND-T002 | Caché evita llamada por request | Performance | PLANNED |
| IND-T003 | API caída usa fallback/cache | Resilience | PLANNED |
| IND-T004 | API lenta no bloquea render crítico | Performance | PLANNED |
| IND-T005 | Actualización manual autorizada | Security/E2E | PLANNED |

## Performance

| ID | Caso | Tipo | Estado |
|---|---|---|---|
| PERF-T001 | Archive 100 propiedades dataset | Performance | PLANNED |
| PERF-T002 | Archive 1.000 propiedades dataset | Performance | PLANNED |
| PERF-T003 | Filtro 5.000 propiedades | Performance | PLANNED |
| PERF-T004 | Sin N+1 en cards | Performance | PLANNED |
| PERF-T005 | Assets no cargan en páginas ajenas | Performance | PLANNED |
| PERF-T006 | LCP/CLS/INP dentro de budget definido | Lighthouse | PLANNED |
| PERF-T007 | Importación grande por lotes | Performance | PLANNED |

## Migración

| ID | Caso | Tipo | Estado |
|---|---|---|---|
| MIG-T001 | Inventario detecta productos origen | Integration | PLANNED |
| MIG-T002 | Dry-run no escribe | Integration | PLANNED |
| MIG-T003 | Precio WooCommerce migra correctamente | Integration | PLANNED |
| MIG-T004 | Campos ACF mapean correctamente | Integration | PLANNED |
| MIG-T005 | Galería conserva orden | Integration | PLANNED |
| MIG-T006 | Categorías mapean a taxonomías nuevas | Integration | PLANNED |
| MIG-T007 | URLs preservadas/redirigidas | SEO/E2E | PLANNED |
| MIG-T008 | Segunda ejecución no duplica | Integration | PLANNED |
| MIG-T009 | Comparación de muestra origen/destino | Manual/Integration | PLANNED |
| MIG-T010 | Rollback probado antes de producción | Operational | PLANNED |

## Auditoría del catálogo

Este archivo debe actualizarse cada vez que se agregue un flujo crítico. Un módulo no pasa a `DONE` sin que sus casos aplicables estén implementados, ejecutados o explícitamente justificados como N/A.
