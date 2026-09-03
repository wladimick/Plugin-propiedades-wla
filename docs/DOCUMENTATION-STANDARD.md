# Estándar de documentación continua

## Regla

Cada cambio debe dejar el repositorio en un estado donde otra persona pueda entender qué hace, cómo probarlo y cómo operarlo sin depender del chat que originó el trabajo.

## Qué documento actualizar

- Arquitectura → `ARCHITECTURE.md`
- Datos → `DATA-MODEL.md`
- Admin/UX → `ADMIN-UX.md` y `ADMIN-SECTIONS.md`
- Importación → `IMPORT-EXPORT.md`
- Seguridad → `SECURITY.md`
- SEO/GEO/AEO → `SEO-GEO-AEO.md`
- Temas/templates → `THEME-INTEGRATION.md`
- Migración → `MIGRATION.md`
- Tests → `TESTING.md` / futuros `docs/test-cases/`
- Decisión estructural → `docs/decisions/ADR-xxxx-*.md`
- Evidencia/auditoría → `AUDIT-TRACEABILITY.md`
- Roadmap/fases → `ROADMAP.md` y `DEVELOPMENT-PHASES.md`
- Ayuda usuario → `HELP-CENTER.md`

## Encabezado recomendado para documentos operativos

```markdown
# Título

Estado: Draft | Active | Deprecated
Última revisión: YYYY-MM-DD
Relacionado: issue/PR/ADR
```

No es obligatorio retroalimentar documentos ya existentes inmediatamente, pero debe aplicarse progresivamente.

## Documentación de funcionalidades

Debe cubrir:

1. objetivo;
2. comportamiento;
3. permisos;
4. datos usados;
5. estados y errores;
6. hooks/API si existen;
7. tests;
8. riesgos/limitaciones;
9. instrucciones para usuario si aplica.

## Documentación de tests

Los test cases formales deben incluir:

- ID;
- precondición;
- datos;
- pasos;
- resultado esperado;
- automatizado/manual;
- módulo;
- severidad si falla.

## Registro de cambios

No usar comentarios de código como sustituto de documentación de producto. Los comentarios explican por qué una decisión local no es obvia; la documentación explica el sistema.

## Versionado de docs

Las docs viajan en el mismo PR que el comportamiento. No crear una PR posterior de documentación salvo excepción justificada.

## Estado de documentos

- `Draft`: propuesta aún no obligatoria.
- `Active`: fuente de verdad actual.
- `Deprecated`: se conserva por historial, pero enlaza al reemplazo.

## Auditoría de desactualización

Una auditoría debe buscar señales como:

- nombre de función/clase inexistente;
- pantalla documentada no implementada;
- capability diferente;
- campo renombrado;
- fase marcada DONE sin evidencia;
- tests descritos que no existen;
- dependencia eliminada aún listada como obligatoria.
