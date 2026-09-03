# Contribuir a WLA Inmo

Antes de desarrollar, leer:

1. `docs/DEVELOPMENT-METHODOLOGY.md`
2. `docs/STACK.md`
3. `docs/TESTING.md`
4. `docs/QUALITY-GATES.md`
5. `docs/PR-WORKFLOW.md`
6. `docs/DEFINITION-OF-DONE.md`

## Flujo básico

1. Definir issue/tarea y criterios de aceptación.
2. Crear rama con prefijo correcto.
3. Implementar cambio y tests.
4. Actualizar documentación en la misma PR.
5. Ejecutar auto-review.
6. Abrir PR usando el template.
7. Adjuntar evidencia.
8. Resolver review y checks.
9. Squash merge cuando esté aprobado.

## Convenciones de ramas

- `feat/`
- `fix/`
- `perf/`
- `security/`
- `seo/`
- `refactor/`
- `docs/`
- `test/`
- `chore/`

## Commits

Mensajes claros, por ejemplo:

- `feat(admin): add property status controls`
- `test(import): cover duplicate property codes`
- `docs(seo): document canonical policy`

## Seguridad

Nunca subir secretos o datos personales reales. Si detectas una vulnerabilidad, evitar publicar detalles explotables innecesarios antes de contar con mitigación.

## Dependencias

No agregar nuevas dependencias sin justificar necesidad, licencia, mantenimiento, tamaño e impacto de seguridad/performance. Las decisiones estructurales deben documentarse como ADR.

## Documentación

Una funcionalidad no está terminada si el repositorio no permite entender y probar su comportamiento.
