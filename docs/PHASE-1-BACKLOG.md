# Fase 1 — Backlog inicial del Core

Estado: `PLANNED`  
Entrada aprobada: 2026-09-03  
Dependencia: Fase 0 `DONE`

## Objetivo

Construir el primer WLA Inmo instalable, desacoplado y auditable, sin migrar todavía Propiedades Martínez ni reemplazar producción.

## Orden propuesto de PR

### PR 1.1 — Bootstrap y build
- `plugin/wla-inmo/wla-inmo.php`.
- constantes/versionado.
- Composer PSR-4.
- estructura de namespaces.
- activación/desactivación segura.
- requisitos PHP/WP.
- text domain.
- build ZIP.
- smoke tests de instalación.

### PR 1.2 — Entidad Property
- CPT `wla_property`.
- labels/capabilities base.
- soporte de título/editor/excerpt/thumbnail/revisions según contrato.
- URLs/rewrite iniciales.
- tests de registro y permisos.

### PR 1.3 — Taxonomías base
- operación.
- tipo de propiedad.
- región.
- comuna.
- sector.
- reglas de jerarquía/normalización.
- tests.

### PR 1.4 — Meta schema y validación
- registro de campos canónicos.
- tipos, sanitización y validadores.
- precios/moneda principal/price_on_request.
- superficies/características.
- privacidad de ubicación.
- tests unit/integration.

### PR 1.5 — Índice `wp_wla_property_index`
- esquema físico inicial.
- migración/versionado DB.
- repository/projection service.
- sincronización create/update/delete/status.
- rebuild seguro.
- índices SQL iniciales basados en consultas previstas.
- tests de integridad e idempotencia.

### PR 1.6 — Roles y capabilities
- Administrador inmobiliario.
- Editor de propiedades.
- Gestor de leads (capabilities reservadas aunque el módulo llegue después).
- matriz de acceso.
- tests positivos y negativos.

### PR 1.7 — Settings y contratos públicos mínimos
- settings generales sin branding obligatorio.
- país/preset Chile desacoplado.
- hooks públicos iniciales estrictamente necesarios.
- sistema base de template resolver.
- no congelar API innecesaria antes de uso real.

### PR 1.8 — CI Fase 1 y release 0.1.0-alpha
- PHP syntax.
- WPCS.
- PHPStan baseline/objetivo inicial.
- PHPUnit/unit.
- WordPress integration tests.
- build artifact.
- matriz PHP/WP aprobada.
- instalación limpia y desinstalación/conservación de datos.

## Tests mínimos para cerrar Fase 1

- activar/desactivar sin warnings/fatals;
- rechazar PHP/WP no soportado con mensaje seguro;
- CPT y taxonomías registrados correctamente;
- capabilities respetadas;
- datos canónicos validados;
- tabla índice creada idempotentemente;
- índice sincronizado y reconstruible;
- ninguna dependencia WooCommerce/Elementor/ACF/WPCode;
- plugin funciona con un tema WordPress estándar;
- uninstall no elimina datos por defecto;
- ZIP instalable sin Composer/Node en servidor.

## Fuera de alcance de Fase 1

- UI administrativa completa;
- importador XLSX;
- frontend final;
- WLA Inmo Light final;
- leads;
- SEO/GEO/AEO completo;
- migración real de Propiedades Martínez.

Esas funciones se implementan en fases posteriores siguiendo `DEVELOPMENT-PHASES.md`.

## Quality Gate de salida

Fase 1 pasa a `DONE` solamente si:

1. todas las PR están mergeadas con evidencia;
2. CI relevante está verde;
3. tests obligatorios están documentados;
4. no hay findings críticos/altos abiertos del alcance;
5. `PROJECT-STATUS.md` está actualizado;
6. existe artefacto instalable de prueba.
