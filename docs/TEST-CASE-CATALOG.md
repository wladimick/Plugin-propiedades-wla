# Catálogo base de casos de prueba

Este catálogo define los casos mínimos que deben existir a medida que cada módulo sea implementado. `PLANNED` significa documentado pero aún no automatizado/ejecutado.

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
| IMPORT-T003 | JSON válido dry-run | Integration | PLANNED |
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
| IMPORT-T023 | Usuario sin permiso no importa | Security | PLANNED |
| IMPORT-T024 | Exportación evita CSV formula injection | Security | PLANNED |
| IMPORT-T025 | Exportación respeta filtros seleccionados | Integration | PLANNED |

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
