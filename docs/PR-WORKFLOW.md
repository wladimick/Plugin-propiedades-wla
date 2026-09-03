# Flujo de Pull Requests

## Regla principal

Toda modificación de código o comportamiento del producto debe entrar por PR. Excepciones: documentación inicial/administrativa de emergencia; aun así se recomienda PR.

## Tamaño

Una PR debe ser suficientemente pequeña para revisarse de forma razonable. Si mezcla migración, UI, refactor, SEO y seguridad sin necesidad, debe dividirse.

## Títulos

Formato recomendado:

- `feat(admin): add property completeness panel`
- `fix(import): prevent duplicate external_id`
- `perf(search): add indexed location query`
- `security(rest): enforce property edit capability`
- `seo(schema): add listing structured data`
- `docs(audit): define traceability process`

## Contenido obligatorio

Usar `.github/PULL_REQUEST_TEMPLATE.md`.

Toda PR debe indicar:

- problema;
- solución;
- alcance;
- criterios de aceptación;
- tests;
- evidencia;
- impacto seguridad;
- impacto performance;
- impacto SEO/GEO/AEO;
- migración/rollback;
- docs actualizadas.

## Revisión

Orden recomendado:

1. requisitos/alcance;
2. arquitectura/datos;
3. seguridad;
4. funcionalidad;
5. tests;
6. performance;
7. SEO/GEO/AEO;
8. UX/accesibilidad;
9. documentación.

## Estados

- Draft: aún no cumple todos los gates.
- Ready for review: implementación y auto-review completados.
- Changes requested: existen bloqueantes.
- Approved: cumple criterios de revisión.
- Mergeable: checks obligatorios PASS.

## Auto-review antes de pedir revisión

El autor debe comprobar:

- diff completo;
- archivos accidentales;
- debug/logs;
- secretos;
- nombres inconsistentes;
- código duplicado;
- tests faltantes;
- documentación;
- compatibilidad;
- rollback.

## Política de merge

Preferencia: `Squash and merge`.

Motivos:

- historial limpio;
- una unidad funcional por PR;
- revert simple.

## Cambios de datos

Una PR con migración debe incluir:

- versión schema;
- forward migration;
- comportamiento si falla;
- idempotencia;
- rollback o explicación de por qué no es reversible;
- test con datos representativos no sensibles.

## PR de refactor

Debe declarar explícitamente que no cambia comportamiento. Si cambia comportamiento, deja de ser refactor puro.

## PR de seguridad

No incluir detalles explotables innecesarios en repositorio público antes de tener mitigación disponible. Seguir una política responsable cuando el producto sea distribuido públicamente.

## PR de rendimiento

Debe incluir medición antes/después cuando se afirme una mejora.

## PR SEO/GEO/AEO

Debe indicar qué salida cambia:

- URL;
- title;
- canonical;
- meta;
- schema;
- sitemap;
- robots;
- contenido semántico.

## PR de UI

Debe incluir evidencia de desktop y mobile, además de navegación por teclado cuando corresponda.

## Dependencias

Agregar una dependencia requiere:

- necesidad;
- alternativas evaluadas;
- tamaño;
- licencia;
- mantenimiento;
- superficie de seguridad;
- impacto en build/distribución.

Cambios importantes deben tener ADR.
