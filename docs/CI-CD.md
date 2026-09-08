# CI/CD

## Objetivo

Automatizar controles repetibles para que una PR no dependa solo de revisión manual y conservar evidencia auditable asociada al head realmente validado.

## Workflows activos

### `ci.yml` — Phase 1 CI

Controla el núcleo con:

1. PHP syntax;
2. PHPCS / WordPress Coding Standards;
3. PHPStan sobre dominios gated;
4. PHPUnit;
5. source/release smoke;
6. build del ZIP instalable;
7. SHA-256 y artifact de calidad;
8. integración WordPress mínimo/latest donde corresponde.

### Gates de integración

El repositorio mantiene workflows específicos para:

- Bootstrap;
- Administration E2E;
- Catalogue Quality;
- Dashboard;
- Help Center;
- Settings;
- Activity;
- Import Persistence;
- Import Row Executor;
- Import Batch Runner;
- Import UI;
- JSON WLA;
- XLSX;
- Remote Media;
- Safe Rollback.

Cada suite conserva su trigger normal y, desde PR 3.12, expone `workflow_call` para composición.

### `phase3-quality-gate.yml`

Señal transversal de cierre de Fase 3.

- invoca las 16 suites reales sobre el mismo PR head;
- mantiene cada suite en su entorno aislado;
- no duplica lógica funcional ni scripts de importación;
- el summary usa `always()` para observar todos los resultados;
- registra `pr_head_sha`, `pr_base_sha`, `checkout_sha`, run ID y resultado por suite;
- sube `wla-inmo-phase3-quality-gate-summary`;
- falla si una sola suite no termina en `success`.

El `pr_head_sha` es la referencia canónica para auditoría del código probado. El merge SHA sintético generado por GitHub Actions se conserva como `checkout_sha`, pero no sustituye al head del PR.

### E2E

Administration Quality Gate instala WordPress limpio, el ZIP real del plugin y Chromium. Ejecuta Playwright sobre flujos de administración/importador, permisos, responsive y axe.

Viewports prioritarios cubiertos:

- 1440;
- 1024;
- 768;
- 390;
- 360.

Axe bloquea findings `serious`/`critical` en las superficies propias cubiertas.

### Performance

Las suites de CI registran regresión sintética, no SLA de producción.

Para Fase 3 se conservan datasets 100/1.000/5.000 en JSON y administración, incluyendo tiempos, peak memory y conteo de consultas cuando aplica.

## Matriz mínima actual de cierre

- WordPress 6.6.2 / PHP 8.1 / MySQL 8;
- WordPress latest / PHP 8.3 / MySQL 8.

Las dependencias pueden tener matrices adicionales. Antes de Beta/1.0 debe revisarse nuevamente el soporte mínimo, especialmente PHP 8.1.

## Required checks

Mientras no exista branch protection obligatoria, el criterio documental de merge exige al menos:

- Phase 1 CI verde;
- suites funcionales relevantes verdes;
- Phase 3 Quality Gate verde para cerrar Fase 3;
- artifact/checksum válido;
- review sin P0/P1 abiertos.

No se debe marcar una PR `READY_TO_MERGE` basándose únicamente en resultados históricos de otro head.

## Artifacts

Conservar cuando aporten auditoría:

- summary de quality gate;
- reportes de tests;
- screenshots/reportes E2E;
- logs de performance;
- ZIP instalable;
- ZIP de quality/release;
- checksum SHA-256;
- build evidence de dependencias especializadas como XLSX.

Para PR 3.12, run de referencia pre-documentación `34276667461` produjo, entre otros:

- summary artifact `10076114220`;
- quality ZIP `10076055577`;
- installable ZIP `10076011626`;
- Admin E2E `10076060248`;
- JSON performance PHP 8.1 `10076055535`;
- JSON performance PHP 8.3 `10076086432`.

La evidencia definitiva se registra en `docs/evidence/phase-3/PR-3.12-PHASE-3-QUALITY-GATE.md`.

## Fallos

No desactivar checks para “hacer pasar” una PR. Si un check es incorrecto o inestable:

1. documentar la causa;
2. abrir corrección;
3. registrar excepción temporal en la PR;
4. restaurar el gate cuanto antes.

Un warning de tooling no equivale automáticamente a un fallo funcional, pero debe clasificarse. En PR 3.12 se registró como `LOW` la transición de runtime Node 20 → Node 24 anunciada por GitHub Actions; no produjo fallos del gate.

## Entornos

- desarrollo local;
- CI efímero;
- staging;
- producción.

Nunca usar producción como entorno primario de QA.

## Datos de test

Usar fixtures sintéticos. No copiar bases productivas con datos personales a CI.
