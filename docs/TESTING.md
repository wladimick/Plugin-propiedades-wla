# Estrategia de testing

## Objetivo

Todo comportamiento crítico de WLA Inmo debe poder verificarse de forma repetible. Los tests son parte del producto y su evidencia debe quedar vinculada a la PR o release correspondiente.

## Pirámide de pruebas

### 1. Unitarias

Aplican a lógica pura:

- formato de precios;
- normalización de códigos;
- validadores;
- mappers de importación;
- generación de slugs;
- transformaciones SEO;
- cálculo de completitud;
- sanitización propia;
- selección de templates;
- conversión/normalización de datos.

Objetivo: rápidas, aisladas y determinísticas.

### 2. Integración WordPress

Cubren interacción con:

- CPT;
- taxonomías;
- post meta;
- tablas propias;
- capabilities;
- REST;
- cron;
- media library;
- rewrites;
- sitemap;
- hooks/filters.

### 3. E2E

Flujos críticos con navegador:

- instalar/activar;
- crear propiedad;
- editar precio/estado;
- subir galería;
- publicar;
- encontrar en listado;
- filtrar;
- abrir ficha;
- solicitar visita;
- importar archivo;
- revisar informe de importación;
- administrar destacados;
- uso por rol inmobiliario no administrador.

### 4. Visuales y responsive

Revisar al menos:

- 360 px;
- 390 px;
- 768 px;
- 1024 px;
- 1440 px.

Pantallas críticas:

- home de referencia;
- archivo;
- ficha;
- filtros;
- formulario de lead;
- listado admin;
- editar propiedad;
- importador;
- centro de ayuda.

### 5. Accesibilidad

Verificar:

- navegación por teclado;
- foco visible;
- labels;
- errores asociados a campos;
- contraste;
- estructura de headings;
- alt de imágenes;
- botones vs enlaces;
- modales accesibles;
- landmarks;
- zoom;
- mensajes no dependientes solo del color.

Objetivo: WCAG 2.2 AA.

Para gates automatizados de administración/importador, axe bloquea findings `serious`/`critical` en las superficies propias cubiertas. La ausencia de findings automatizados no sustituye QA manual de accesibilidad cuando una fase introduce nuevas superficies UI.

### 6. Performance

Medir:

- consultas SQL;
- N+1;
- tamaño CSS/JS;
- imágenes;
- TTFB;
- LCP;
- CLS;
- INP;
- cache hit/miss cuando aplique;
- listado con volúmenes crecientes.

Datasets mínimos sugeridos:

- 10 propiedades;
- 100;
- 1.000;
- 5.000 para pruebas de búsqueda/importación cuando el entorno lo permita.

No aceptar regresiones significativas sin justificación documentada. Las mediciones sintéticas de CI son evidencia comparativa, no un SLA productivo.

### 7. Seguridad

Casos mínimos:

- usuario sin capability intentando acciones admin;
- nonce ausente/inválido;
- XSS almacenado/reflejado;
- SQL injection;
- CSRF;
- REST sin autorización;
- subida de archivo no permitido;
- MIME falso;
- path traversal;
- fórmula peligrosa en CSV exportado cuando exista ese formato;
- SSRF mediante URL de imagen;
- importación con payload malicioso;
- abuso de formulario público;
- rate limiting;
- enumeración de datos privados;
- exposición de logs/errores.

### 8. SEO/GEO/AEO

Validar:

- title único;
- meta description;
- canonical;
- robots;
- sitemap;
- breadcrumbs;
- JSON-LD parseable;
- URLs persistentes;
- redirects migratorios;
- noindex de filtros no indexables;
- propiedades archivadas/no disponibles según política;
- contenido semántico coherente con los datos reales;
- ausencia de precio/ubicación contradictorios.

### 9. Compatibilidad de temas

El core debe probarse con:

- WLA Inmo Light;
- al menos un tema oficial de WordPress;
- un tema de terceros estándar.

Validar:

- archive;
- single;
- CSS isolation;
- template override;
- no dependencia de funciones del tema WLA.

### 10. Importador

Matriz específica:

#### Archivo
- CSV UTF-8;
- separadores soportados;
- XLSX;
- JSON;
- archivo vacío;
- corrupto;
- demasiado grande;
- encoding inesperado.

#### Datos
- código nuevo;
- código existente;
- código duplicado dentro del archivo;
- precio cero;
- precio vacío;
- texto en campo numérico;
- comuna inexistente;
- estado desconocido;
- columnas extra;
- columnas faltantes;
- HTML/script en texto;
- fechas inválidas.

#### Multimedia
- imagen válida;
- 404;
- timeout;
- archivo enorme;
- MIME incorrecto;
- dominio no permitido si se implementa allowlist;
- repetición de URL;
- galería parcial.

#### Ejecución
- dry-run;
- importación completa;
- interrupción a mitad;
- reanudación;
- repetición idempotente;
- actualización sin duplicar;
- rollback donde corresponda;
- informe final exacto.

## Smoke test por PR

Toda PR funcional debe verificar como mínimo:

1. WordPress carga sin fatal error.
2. Plugin activa.
3. Admin accesible.
4. Crear/leer propiedad si el área fue afectada.
5. Frontend no rompe.
6. No aparecen warnings/notices relevantes con debug de desarrollo activo.

## Regression suite

Antes de una release candidata se ejecutará la suite de regresión completa de todos los módulos terminados.

### Phase 3 Quality Gate

Desde PR 3.12 existe `.github/workflows/phase3-quality-gate.yml` como señal transversal de cierre de Import/Export.

El gate maestro reutiliza 16 suites mediante `workflow_call` y exige `success` en todas:

- Phase 1 CI;
- Administration Quality Gate;
- Bootstrap Smoke;
- Catalogue Quality;
- Dashboard;
- Help Center;
- Settings;
- Activity;
- Import Persistence;
- Row Executor;
- Batch Runner;
- Import UI;
- JSON WLA;
- XLSX;
- Remote Media;
- Safe Rollback.

El job final genera un manifest con el **PR head real**, base, checkout SHA, run ID y resultados. La ejecución debe cubrir las matrices mínimas compatibles WordPress 6.6.2/PHP 8.1/MySQL 8 y WordPress latest/PHP 8.3/MySQL 8.

Criterio de cierre de Fase 3:

1. gate maestro verde sobre el head final;
2. 16/16 child gates `success`;
3. artifact/checksum del mismo run;
4. performance 100/1k/5k registrada;
5. responsive 360/390/768/1024/1440 y axe en superficies cubiertas;
6. review sin P0/P1 abiertos;
7. evidencia `docs/evidence/phase-3/PR-3.12-PHASE-3-QUALITY-GATE.md` actualizada;
8. Fase 3 solo pasa a `DONE` después del merge.

## Evidencia

Cada PR debe incluir una tabla como:

| Test | Resultado | Evidencia |
|---|---|---|
| Unit | PASS | workflow/link |
| Integration | PASS | workflow/link |
| E2E | PASS | workflow/link |
| Manual QA | PASS/N/A | captura/notas |
| Security | PASS/N/A | reporte |
| Performance | PASS/N/A | medición |

Nunca marcar PASS sin haber ejecutado la prueba correspondiente.

## Identificación de casos

Formato recomendado:

- `CORE-T001`
- `ADMIN-T001`
- `IMPORT-T001`
- `PHASE3-T001`
- `FRONT-T001`
- `SEO-T001`
- `SEC-T001`
- `LEAD-T001`
- `MIG-T001`

Los casos formales podrán almacenarse posteriormente en `docs/test-cases/`.

## Criterios de bloqueo

No se puede hacer merge cuando:

- falla un test obligatorio;
- existe fatal error;
- hay vulnerabilidad crítica/alta introducida;
- se rompe migración de datos;
- el cambio contradice documentación sin actualizarla;
- no existen criterios de aceptación verificables;
- existen regresiones críticas conocidas sin aprobación explícita;
- el Phase 3 Quality Gate requerido para el cierre de Fase 3 no termina completamente verde.
