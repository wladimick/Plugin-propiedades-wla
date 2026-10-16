# PR 4.1 — Frontend foundation / template resolver / assets

Estado: `FINAL_GATE_PENDING`

- Issue: #74
- PR: #75
- Branch: `feat/phase4-frontend-foundation`
- Base: `main`
- Producción: `NO AFECTADA`

## Objetivo

Crear la infraestructura frontend reusable y agnóstica al tema sin adelantar el archive, cards, filtros, single completo, galería o mapa de los PR posteriores.

## Decisiones implementadas

Este PR no reemplaza ninguna decisión aceptada. Implementa principalmente:

- D20: plugin = lógica/datos/API; tema = presentación;
- D21: fallbacks neutrales dentro del plugin;
- D22: precedencia child theme → parent theme → plugin;
- D25: Vanilla JS/progressive enhancement, sin JS vacío;
- D62: base compatible con accesibilidad futura;
- D63: assets acotados/condicionales;
- D74: WLA Inmo Light opcional;
- D75: independencia de tema como requisito de QA.

## Scope implementado

### Resolver seguro

`Frontend\TemplateResolver`:

- allowlist explícita de `archive-property.php` y `single-property.php`;
- búsqueda `child → parent → plugin`;
- overrides bajo `wla-inmo/`;
- rechazo de traversal, NUL, segmentos inválidos y templates desconocidos;
- `realpath()` + confinamiento a roots aprobados;
- filtros `wla_inmo_template_candidates` y `wla_inmo_template_path` sin bypass de confinamiento;
- fail-closed cuando el path filtrado/resuelto no es válido.

### Routing

`Frontend\Bootstrap`:

- `template_include` únicamente para archive/single del CPT Property;
- no intercepta requests ajenos;
- bloquea admin, AJAX, REST y feeds;
- conecta assets frontend.

### Renderer y hooks

`Frontend\Renderer`:

- el caller entrega nombre de template, nunca path;
- reutiliza `TemplateResolver`;
- argumentos explícitos únicamente mediante `$wla_args`;
- no usa `extract()`;
- filtro `wla_inmo_template_args`;
- hooks `wla_inmo_before_template` y `wla_inmo_after_template`;
- output buffering con cleanup ante excepción.

### Assets y fallbacks

- `assets/css/frontend.css` namespaced;
- CSS solo en superficies WLA;
- sin dependencias CSS;
- sin jQuery/framework;
- no se distribuye JS placeholder;
- fallbacks mínimos `archive-property.php` y `single-property.php`;
- sin lectura arbitraria de meta privada.

### Core

`Core\Plugin` registra `Frontend\Bootstrap` solo fuera de administración.

## Findings corregidos durante QA

### QA-4.1-001 — smoke heredado incompatible con resolver fail-closed

El smoke de settings simulaba un path de tema inexistente y esperaba que fuera aceptado. También esperaba `parts/card.php` antes de que ese partial formara parte de la allowlist.

Corrección:

- el test ahora espera rechazo del path inexistente;
- valida fallback solo para templates soportados;
- mantiene el resolver endurecido, sin relajación de seguridad.

Severidad: `TEST_FIX / NO_RUNTIME_REGRESSION`.

### QA-4.1-002 — WP-CLI presenta contexto admin durante integration harness

La primera integración construía manualmente `$wp_query`, pero WP-CLI podía seguir haciendo que `is_admin()` devolviera true. El runtime correctamente bloqueaba ese contexto y el test interpretaba el bloqueo como fallo de routing.

Corrección:

- el harness configura temporalmente `current_screen` como frontend;
- restaura el estado al finalizar;
- el runtime conserva el bloqueo de administración.

Severidad: `TEST_HARNESS_FIX / NO_RUNTIME_RELAXATION`.

### QA-4.1-003 — alcance documental exigía renderer + hooks

La primera entrega cubría resolver/routing/assets pero aún no materializaba el renderer de partials con argumentos explícitos y hooks base definido en `PHASE-4-BACKLOG.md`.

Corrección:

- agregado `Frontend\Renderer`;
- scope explícito `$wla_args`;
- hooks before/after;
- smoke/release/PHPStan/integration incorporados.

Severidad: `SCOPE_COMPLETION`.

## Cobertura automatizada

### Unit

`tests/unit/FrontendTemplateResolverTest.php`:

- allowlist de foundation;
- inputs inseguros;
- fallback determinista/fail-closed.

### Source smoke

`tests/smoke/frontend-foundation.php`:

- fallback plugin;
- child theme precedence;
- parent theme precedence;
- traversal/unknown rejection;
- path filter confinado;
- candidates filter confinado;
- renderer con `$wla_args` sin variable leakage;
- hooks before/after;
- renderer rechaza traversal y template no allowlisted;
- routing solo WLA;
- feed no interceptado;
- CSS condicional;
- sin JS placeholder;
- templates sin meta privada/arbitraria.

### Release smoke

`bin/smoke-frontend.sh` exige dentro del ZIP:

- `TemplateResolver.php`;
- `Renderer.php`;
- `Bootstrap.php`;
- `Assets.php`;
- archive/single fallback;
- CSS frontend;
- autoload correcto;
- sintaxis PHP;
- ausencia de dependencias legacy;
- ausencia de meta privada/arbitraria;
- ausencia de selector global prohibido.

### Integration WordPress/MySQL

`tests/integration/assert-frontend-foundation.php` valida sobre release ZIP instalado:

- clases disponibles;
- hooks registrados;
- fallback plugin;
- path security;
- child/parent override con temas fixture reales;
- renderer y hooks sobre tema hijo real;
- scope explícito sin `extract()`;
- routing archive/single;
- assets conditional;
- templates públicos sin private meta.

Matriz dedicada:

- WP 6.6.2 / PHP 8.1 / MySQL 8;
- WP latest / PHP 8.3 / MySQL 8.

### Static analysis

`phpstan.neon.dist` incluye `plugin/wla-inmo/src/Frontend` y `tests/phpstan/wordpress-frontend-stubs.php`.

## Historial de CI relevante

Primer intento funcional, head `71f8ccaffb6ce92516e08caab0e264f17d33ec42`:

- Frontend Foundation Integration `34298099493`: FAILURE por harness frontend bajo WP-CLI;
- Bootstrap Smoke `34298099670`: FAILURE por smoke heredado de settings;
- Phase 1 CI `34298099406`: FAILURE por el mismo smoke heredado;
- Phase 3 Quality Gate `34298099784`: FAILURE por propagación.

En ese mismo head, múltiples regresiones independientes sí quedaron SUCCESS (Administration, Dashboard, Catalogue, Settings, Activity, Import UI/Persistence/Row Executor/Batch Runner/Rollback/Help).

Las causas fueron corregidas sin relajar runtime o seguridad.

Head funcional previo a documentación: `e59126c52916f3263fb5b5b37c114a6facbba16f`.

El **head documental final** debe ejecutar nuevamente los gates antes de declarar `QA_PASSED / READY_TO_MERGE`. Los run IDs finales se registrarán en el cuerpo del PR y/o cierre post-merge para evitar modificar el SHA ya validado.

## Casos PR 4.1

| ID | Caso | Tipo | Estado pre-gate final |
|---|---|---|---|
| FRONT41-T001 | Plugin fallback archive | Smoke/Integration | IMPLEMENTED |
| FRONT41-T002 | Plugin fallback single | Smoke/Integration | IMPLEMENTED |
| FRONT41-T003 | Child override vence parent/plugin | Smoke/Integration | IMPLEMENTED |
| FRONT41-T004 | Parent override vence plugin | Smoke/Integration | IMPLEMENTED |
| FRONT41-T005 | Traversal/template desconocido rechazado | Security | IMPLEMENTED |
| FRONT41-T006 | Path filtrado fuera de roots rechazado | Security | IMPLEMENTED |
| FRONT41-T007 | Request ajeno no interceptado | Integration | IMPLEMENTED |
| FRONT41-T008 | Admin/AJAX/REST/feed bloqueados por routing | Smoke/Static | IMPLEMENTED |
| FRONT41-T009 | CSS no carga fuera de WLA | Smoke/Integration | IMPLEMENTED |
| FRONT41-T010 | CSS carga en archive/single WLA | Smoke/Integration | IMPLEMENTED |
| FRONT41-T011 | CSS sin selectores globales prohibidos | Static/Release | IMPLEMENTED |
| FRONT41-T012 | Sin jQuery/Woo/Elementor/ACF/WPCode | Static/Release | IMPLEMENTED |
| FRONT41-T013 | Release ZIP contiene foundation completa | Release Smoke | IMPLEMENTED |
| FRONT41-T014 | Renderer usa `$wla_args` sin extract | Smoke/Integration | IMPLEMENTED |
| FRONT41-T015 | Hooks before/after ejecutan alrededor del template | Smoke/Integration | IMPLEMENTED |
| FRONT41-T016 | Renderer no acepta path/template no allowlisted | Security | IMPLEMENTED |
| FRONT41-T017 | Templates no acceden a private/arbitrary meta | Security/Static | IMPLEMENTED |
| FRONT41-T018 | WP 6.6.2 / PHP 8.1 / MySQL 8 | Compatibility | FINAL_GATE_PENDING |
| FRONT41-T019 | WP latest / PHP 8.3 / MySQL 8 | Compatibility | FINAL_GATE_PENDING |
| FRONT41-T020 | Regresión Fases 1–3 | CI | FINAL_GATE_PENDING |
| FRONT41-T021 | Review sin P0/P1 abiertos | Review | FINAL_GATE_PENDING |

## Fuera de alcance

- archive/card funcional completo;
- query pública optimizada/paginada;
- search/filtros;
- single final;
- galería;
- mapa;
- CTA/leads;
- SEO final;
- WLA Inmo Light;
- migración productiva.

## Documentación relacionada

- `docs/PHASE-4-BACKLOG.md`
- `docs/FRONTEND.md`
- `docs/TESTING.md`
- `docs/TEST-CASE-CATALOG.md`

## Criterio de merge

No marcar PR #75 ready ni mergear hasta confirmar:

1. Frontend Foundation Integration verde en ambas matrices;
2. Phase 1 CI / Bootstrap sin regresión;
3. regresiones transversales verdes;
4. PHPStan Frontend verde;
5. release smoke verde;
6. comments/reviews/threads sin P0/P1 abiertos;
7. PR head coincidente con el head probado.

## Producción

`propiedadesmartinez.cl` permanece sin cambios. No se utiliza producción como entorno de QA para PR 4.1.
