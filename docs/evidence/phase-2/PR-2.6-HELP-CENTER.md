# Evidencia — Fase 2 / PR 2.6 Centro de Ayuda

Estado documental: `DONE`.

Issue: #33 — `CLOSED`  
PR: #34 — `MERGED`  
Rama: `feat/phase2-help-center`  
Squash merge: `56717aa3af97c74407d794f334b295216fe067f8`

## Objetivo

Integrar ayuda práctica dentro del administrador de WordPress para que una persona con conocimientos básicos pueda operar WLA Inmo sin depender de soporte técnico permanente.

## Implementación

### Centro de Ayuda

`Admin\HelpCenter` reemplaza el placeholder de `WLA Inmo → Ayuda` y entrega:

- accesos rápidos según capability;
- 13+ temas iniciales;
- categorías simples;
- búsqueda local instantánea sobre contenido empaquetado;
- guías de creación/actualización, precio/estado, fotografías, videos y Calidad;
- contenidos de importación, SEO y leads marcados explícitamente como `Próximamente` mientras sus fases no estén implementadas;
- FAQ y glosario;
- cero llamadas remotas.

La ayuda no simula funcionalidades futuras ni presenta botones que ejecuten módulos inexistentes.

### Onboarding por usuario

`Admin\Onboarding` agrega un checklist inicial con 6 pasos recomendados.

- progreso en user meta;
- estado de ocultar también por usuario;
- guardar, ocultar y reiniciar protegidos por nonce;
- capability `view_wla_inmo_dashboard`;
- enlaces de cada paso visibles solo cuando el usuario posee el permiso correspondiente;
- el checklist se puede reabrir desde Ayuda;
- el Resumen muestra una tarjeta no invasiva solo mientras el checklist esté incompleto y no haya sido ocultado.

No se utiliza `update_option()` para progreso personal.

### Ayuda contextual

`Admin\ContextHelp` conserva una única pestaña nativa de ayuda para evitar ruido, pero la expande en el editor de `wla_property` con orientación sobre:

- datos principales y código único;
- Multimedia e imagen destacada;
- galería y URLs de video;
- Calidad del catálogo.

### Assets

- `help-center.css` y `help-center.js` solo se cargan en la pantalla Ayuda;
- JS vanilla;
- búsqueda sin `fetch`, XHR, AJAX ni proveedor externo;
- admin base continúa cargándose en contextos WLA;
- Multimedia mantiene sus assets exclusivos del editor de propiedades.

## Seguridad y privacidad

- nonce obligatorio para mutaciones del onboarding;
- verificación de capability;
- estado guardado por user ID;
- allowlist de steps válidos;
- escaping tardío en HTML;
- enlaces restringidos por capability;
- sin datos privados de propiedades dentro del contenido de ayuda;
- sin secretos, tokens o claves;
- sin llamadas remotas.

## Tests

`tests/smoke/help-center.php` comprueba:

- existencia del Centro de Ayuda;
- catálogo mínimo de temas;
- módulos futuros marcados como planned;
- FAQ sin dependencias legacy;
- ausencia de red en contenido/JS;
- onboarding por user meta;
- nonce y capability;
- ausencia de estado global;
- ayuda contextual para datos/media/calidad;
- assets empaquetados.

`tests/integration/assert-help.php` valida en WordPress real:

- clases disponibles dentro del release activo;
- temas y estados planned;
- render de administrador con acciones completas;
- progreso de onboarding aislado por usuario;
- editor de propiedades ve Crear propiedad y Calidad;
- editor no recibe enlace a Ajustes;
- dismissed state aislado por usuario;
- shape estable del checklist.

El workflow `Help Center Integration` prueba WordPress 6.6.2/PHP 8.1 y WordPress latest/PHP 8.3.

El release smoke exige `HelpCenter.php`, `Onboarding.php`, `help-center.css` y `help-center.js`, y verifica que Ayuda permanezca local y onboarding no use estado global.

## QA final

Head de código validado: `a40246b3cf0e9503b584a0536cdf6bfe104b0521`.

- Phase 1 CI `33858810497`: `SUCCESS`;
- Quality Gate / PHP 8.1: `SUCCESS`;
- PHP syntax: `SUCCESS`;
- WordPress Coding Standards security profile: `SUCCESS`;
- PHPStan: `SUCCESS`;
- PHPUnit: `3 tests / 40 assertions`;
- todos los smoke tests, incluido Help Center: `SUCCESS`;
- release ZIP smoke: `SUCCESS`;
- WordPress 6.6.2 + PHP 8.1: `SUCCESS`;
- WordPress latest + PHP 8.3: `SUCCESS`;
- deactivate/uninstall preservan datos: `SUCCESS`;
- Help Center Integration `33858810499`: `SUCCESS` en ambas matrices;
- Catalogue Quality Integration `33858810510`: `SUCCESS` en ambas matrices;
- Bootstrap Smoke `33858810523`: `SUCCESS`;
- Artifact `9931277294`;
- Artifact digest: `sha256:0fb49b519200f371512c74b2d4aa1f55b6d5d5ece13b31b48ba8b5fe69d0c31f`;
- ZIP SHA-256: `cc7b2f3b325fb187eb11d0501e21ea70db86edb607149a66dd43690efaf3fb66`.

## Findings corregidos

1. El primer Quality Gate detectó que el array de pasos del onboarding se deserializaba antes de que WPCS pudiera reconocer la sanitización por elemento. Se dejó explícito el límite de confianza y cada step sigue pasando por `sanitize_key()` y una allowlist cerrada antes de persistir.
2. Un release smoke posterior detectó que textos educativos del Centro de Ayuda contenían nombres que el guard histórico interpreta como marcadores de dependencia legacy. Se reescribió la copia para hablar de “plugins o constructores usados por el sitio anterior”, manteniendo el guard estricto sobre el runtime.

No quedaron findings críticos ni altos abiertos dentro del alcance de PR 2.6. Los warnings de Node observados en GitHub Actions pertenecen a actions de terceros y no al runtime de WLA Inmo.

## Producción

`propiedadesmartinez.cl` no ha sido modificado.

## Cierre

PR 2.6 cumple criterios funcionales, de seguridad, accesibilidad básica, permisos, integración WordPress y release smoke. El hito queda `DONE` con squash merge `56717aa3af97c74407d794f334b295216fe067f8`.
