# PR 3.12 — Phase 3 Quality Gate

Estado: `QA_PASSED / READY_TO_MERGE`  
Issue: #69  
PR: #70  
Rama: `feat/phase3-quality-gate`  
Producción: `NO AFECTADA`

## Objetivo

Cerrar la validación transversal de **Fase 3 — Import/Export** sin agregar nuevas capacidades de producto. El gate debe demostrar que CSV, JSON WLA, XLSX, media remota, rollback y las regresiones de Core/Administración funcionan como conjunto sobre un mismo head auditable.

## Arquitectura del quality gate

PR #70 convierte 16 workflows existentes en reusable workflows agregando `workflow_call`, pero conserva sus triggers normales. El nuevo `.github/workflows/phase3-quality-gate.yml` los invoca en entornos aislados y termina con `Phase 3 Quality Gate / Summary`.

El summary:

1. corre con `always()`;
2. registra `pr_head_sha`, `pr_base_sha`, `checkout_sha`, run y resultados;
3. sube `wla-inmo-phase3-quality-gate-summary`;
4. falla si uno solo de los 16 resultados es distinto de `success`.

No existe un segundo pipeline de negocio ni se replica lógica de importación para QA.

## Suites incluidas

1. Phase 1 CI / Core
2. Administration Quality Gate
3. Bootstrap Smoke
4. Catalogue Quality
5. Dashboard
6. Help Center
7. Settings
8. Activity
9. Import Persistence
10. Import Row Executor
11. Import Batch Runner
12. Import UI
13. JSON WLA
14. XLSX
15. Remote Media
16. Safe Rollback

## Run de referencia pre-documentación

- Workflow: `Phase 3 Quality Gate`
- Run ID: `34276667461`
- Run number: `2`
- Evento: `pull_request`
- Head PR real: `aec897f658cbe382cd3667108e3d0026721bd36c`
- Base: `91918787d3c10c56c0b156569749a5e544a9d894`
- Resultado: `SUCCESS`
- 16/16 child gates: `SUCCESS`

El manifest del summary confirmó explícitamente `pr_head_sha=aec897f658cbe382cd3667108e3d0026721bd36c`; el `checkout_sha` corresponde al merge ref sintético de GitHub Actions y no se usa como sustituto del head real del PR.

## Matriz de aceptación

| Área | Resultado | Evidencia |
|---|---|---|
| Phase 1 / WPCS / PHPStan / PHPUnit | PASS | run 34276667461 |
| Bootstrap / release smoke | PASS | run 34276667461 |
| WP 6.6.2 / PHP 8.1 / MySQL 8 | PASS | child gates compatibles |
| WP latest / PHP 8.3 / MySQL 8 | PASS | child gates compatibles; WP 7.1 observado |
| CSV mapping/dry-run/confirm/run/resume | PASS | Import UI + Persistence + Runner |
| Identidad / idempotencia / checkpoints | PASS | Persistence + Row Executor + Batch Runner |
| JSON v1 / round-trip / privacy / tampering | PASS | JSON WLA |
| XLSX preflight / bounded / build | PASS | XLSX |
| Media remota / SSRF / MIME / retry | PASS | Remote Media + Row Executor |
| Rollback safe/blocked/stale/crash/media/lock | PASS | Safe Rollback |
| Capabilities / nonces / IDOR | PASS | Admin + JSON + Rollback integration |
| Admin performance 100/1k/5k | PASS | Administration E2E |
| JSON performance 100/1k/5k | PASS | JSON WLA artifacts |
| Responsive | PASS | 1440 / 1024 / 768 / 390 / 360 |
| Accessibility | PASS | axe WCAG 2.2 AA; serious/critical = 0 en superficies cubiertas |
| Review final | PASS | comments 0, reviews 0, inline threads 0 |
| P0/P1 abiertos conocidos | PASS | 0 |

## Performance observada

Las mediciones son **regresión sintética de CI**, no SLA productivo.

### JSON WLA

WP 6.6.2 / PHP 8.1.34:

| Dataset | Tiempo | Peak delta |
|---:|---:|---:|
| 100 | 20.96 ms | 2 MiB |
| 1.000 | 207.26 ms | 2 MiB |
| 5.000 | 1079.68 ms | 8 MiB |

WP 7.1 / PHP 8.3.33:

| Dataset | Tiempo | Peak delta |
|---:|---:|---:|
| 100 | 20.98 ms | 2 MiB |
| 1.000 | 214.32 ms | 2 MiB |
| 5.000 | 975.29 ms | 8 MiB |

### Administración

- Dashboard 100: 5 queries / 0.0030 s
- Dashboard 1.000: 5 queries / 0.0035 s
- Dashboard 5.000: 5 queries / 0.0080 s
- Property list 5k: 2 queries / 0.0039 s
- Activity: 2 queries / 0.0010 s
- Playwright: 14/14 PASS en 38.6 s

No se observa crecimiento N+1 en los benchmarks administrativos cubiertos.

## Artifacts del run 34276667461

| Artifact | ID | Digest SHA-256 |
|---|---:|---|
| `wla-inmo-phase3-quality-gate-summary` | 10076114220 | `e2ac168091fe7a9732db6d6e70fbbc998f12416f9e550b815cee63a3a064da7d` |
| `wla-inmo-0.1.0-alpha-quality` | 10076055577 | `51001c3b3653a0aa70eac5c7945c639dace20b77dc6cb7f7157afa26127cbb6a` |
| `wla-inmo-0.1.0-alpha` | 10076011626 | `e737694a4de9bcd7f1d4852f74f0c47896d99427a697919aad1d73994a6e2c3e` |
| `wla-inmo-admin-e2e-evidence` | 10076060248 | `9d765fb30330ee73d17239d006fb7ad2c86a35bfd2cc32b7a90bb60f5f3fc267` |
| `json-wla-performance-6.6.2-php-8.1` | 10076055535 | `f2c41e67b585cc528b36edbbb9aab5a0be1313de1e1f6256231237a3ea1c04ff` |
| `json-wla-performance-latest-php-8.3` | 10076086432 | `39cca5d63f60fd67f078df6e2559cb4400bb2479ccf452a29e3ce4f4c31f3c9d` |
| `xlsx-build-php-8.1` | 10076070544 | `e0046411a00b7e5d02422db3bc99a3ee65e08d4fef2e9c4ad778f03a596b8639` |
| `xlsx-build-php-8.3` | 10076067487 | `c824b85d8e0b4964976bb46bce53034cad2c9993955e79e5defeedd08d5baf21` |

## Revisión

Antes del commit documental:

- PR comments: 0
- reviews: 0
- inline review threads: 0
- P0 abiertos conocidos: 0
- P1 abiertos conocidos: 0

El commit documental final debe volver a ejecutar el gate completo. Solo el run asociado al head documental final habilita el merge definitivo.

## Finding no bloqueante

`LOW-CI-NODE-RUNTIME`: Actions oficiales (`checkout`, `setup-node`, `upload-artifact` según job) emitieron warnings de deprecación de Node 20 y ejecución forzada sobre Node 24. No produjo errores ni alteró conclusiones. Se registra como mantenimiento futuro del CI, no como blocker de Fase 3.

## Criterio de salida

Pre-merge: `QA_PASSED / READY_TO_MERGE` cuando el **head documental final** repita el quality gate con todos los child gates en `success`, summary artifact válido y revisión sin P0/P1.

Post-merge: Fase 3 pasa a `DONE` únicamente después de integrar PR #70 en `main`, confirmar Issue #69 cerrado y registrar el squash merge en un cierre post-merge.

## Producción

`propiedadesmartinez.cl` no fue modificada durante 3.12.
