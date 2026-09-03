# Metodología de desarrollo

## Objetivo

WLA Inmo se desarrollará con un proceso trazable, auditable y repetible. Ningún cambio funcional relevante debe quedar solamente en una conversación, commit o memoria de una persona: debe existir evidencia en el repositorio.

## Principios

1. **Documentación antes o junto al código.** Todo cambio funcional debe actualizar la documentación correspondiente.
2. **Cambios pequeños y revisables.** Evitar PR gigantes. Una PR debe resolver un objetivo claramente delimitado.
3. **Trazabilidad completa.** Requisito → issue/tarea → PR → tests → evidencia → release.
4. **Una sola fuente de verdad.** Los documentos de `/docs` definen el comportamiento esperado.
5. **Seguridad y rendimiento por diseño.** No son etapas finales.
6. **Compatibilidad desacoplada.** El plugin debe funcionar sin WLA Inmo Light y con temas de terceros.
7. **Backward compatibility cuando corresponda.** Las migraciones deben ser explícitas y reversibles cuando sea posible.
8. **No merge sin criterios de aceptación verificables.**

## Flujo de trabajo

### 1. Definición

Cada iniciativa debe indicar:

- problema a resolver;
- alcance;
- fuera de alcance;
- criterios de aceptación;
- riesgos;
- impacto en datos;
- impacto en seguridad;
- impacto en SEO/GEO/AEO;
- impacto en rendimiento;
- documentación afectada;
- estrategia de test.

### 2. Diseño

Antes de implementar cambios estructurales se debe revisar:

- arquitectura;
- modelo de datos;
- experiencia de administración;
- contratos públicos (hooks, REST, shortcodes, templates);
- migración y compatibilidad;
- permisos/capabilities.

Las decisiones que cambien arquitectura se registran como ADR en `docs/decisions/`.

### 3. Implementación

Convenciones:

- una rama por objetivo;
- commits coherentes;
- sin credenciales ni datos sensibles;
- sin código muerto intencional;
- funciones y clases con responsabilidad clara;
- sanitizar entrada y escapar salida;
- cargar assets solamente donde se usan;
- no introducir dependencias obligatorias sin ADR.

### 4. Verificación

Cada PR debe ejecutar los tests aplicables definidos en `TESTING.md` y cumplir `QUALITY-GATES.md`.

### 5. QA

El QA debe validar los criterios de aceptación desde el punto de vista del usuario y registrar evidencia. Los hallazgos se clasifican:

- Bloqueante
- Alto
- Medio
- Bajo
- Mejora

### 6. Merge

La rama principal es `main`. El método recomendado es **Squash merge** para mantener historial legible. El título final debe explicar el cambio funcional.

### 7. Release

Una release debe incluir:

- versión;
- changelog;
- migraciones necesarias;
- tests ejecutados;
- riesgos conocidos;
- procedimiento de rollback;
- documentación de usuario actualizada.

## Tipos de ramas

- `feat/...` nueva funcionalidad
- `fix/...` corrección
- `perf/...` rendimiento
- `security/...` seguridad
- `seo/...` SEO/GEO/AEO
- `refactor/...` refactor sin cambio funcional
- `docs/...` documentación
- `test/...` pruebas
- `chore/...` mantenimiento

## Versionado

SemVer:

- `MAJOR`: cambio incompatible.
- `MINOR`: funcionalidad compatible.
- `PATCH`: corrección compatible.

Durante desarrollo inicial se utilizará `0.x.y` hasta estabilizar el contrato público del plugin.

## Definition of Ready

Una tarea está lista para desarrollo cuando:

- objetivo claro;
- criterios de aceptación definidos;
- dependencias conocidas;
- diseño aprobado cuando corresponda;
- estrategia de test definida;
- no existen decisiones críticas pendientes.

## Definition of Done

Se aplica lo definido en `DEFINITION-OF-DONE.md`.
