# Auditoría y trazabilidad

## Objetivo

Permitir que en cualquier momento se pueda responder:

- qué se decidió;
- por qué;
- qué se implementó;
- en qué PR;
- qué requisitos cubre;
- qué tests se ejecutaron;
- qué evidencia existe;
- qué riesgos quedaron abiertos;
- qué versión contiene el cambio.

## Cadena de trazabilidad

```text
Requisito / Hallazgo
        ↓
Issue / Tarea
        ↓
Diseño / ADR si aplica
        ↓
Rama
        ↓
Pull Request
        ↓
Tests automáticos + QA manual
        ↓
Evidencia
        ↓
Merge
        ↓
Release / Changelog
```

## Identificadores

Convención sugerida:

- `REQ-xxx`: requisito
- `ARCH-xxx`: decisión arquitectura
- `ADMIN-xxx`: administración/UX
- `DATA-xxx`: datos
- `IMPORT-xxx`: importación
- `FRONT-xxx`: frontend
- `SEO-xxx`: SEO/GEO/AEO
- `SEC-xxx`: seguridad
- `PERF-xxx`: performance
- `MIG-xxx`: migración
- `TEST-xxx`: test transversal

Cuando el proyecto crezca, los IDs deberán aparecer en issue/PR/test cuando aplique.

## Registro de auditoría por PR

Toda PR debe documentar:

1. **Motivo**
2. **Alcance**
3. **Fuera de alcance**
4. **Documentos/requisitos relacionados**
5. **Cambios de datos**
6. **Cambios de permisos**
7. **Impacto frontend**
8. **Impacto SEO/GEO/AEO**
9. **Impacto seguridad**
10. **Impacto performance**
11. **Tests ejecutados**
12. **Evidencia**
13. **Riesgos pendientes**
14. **Rollback**

El template oficial está en `.github/PULL_REQUEST_TEMPLATE.md`.

## Evidencia

La evidencia puede ser:

- ejecución GitHub Actions;
- captura antes/después;
- video corto de flujo;
- salida de test;
- benchmark;
- Lighthouse;
- reporte de seguridad;
- archivo de importación de prueba no sensible;
- log sanitizado;
- comparación de datos;
- checklist QA.

No subir datos personales o credenciales como evidencia.

## Estructura de evidencias versionadas

Cuando convenga mantener evidencia textual dentro del repositorio:

```text
docs/evidence/
└── YYYY-MM/
    └── PR-0000-descripcion/
        ├── README.md
        ├── qa.md
        ├── performance.md
        └── migration.md
```

No es obligatorio duplicar artefactos que GitHub Actions ya conserva; en ese caso se enlaza el run.

## Auditoría de estado del proyecto

Cuando se solicite una auditoría, revisar como mínimo:

### Gobierno
- roadmap vs estado real;
- PR abiertas;
- issues bloqueantes;
- documentación desactualizada;
- ADR pendientes.

### Código
- arquitectura vs implementación;
- deuda técnica;
- dependencias;
- duplicación;
- contratos públicos.

### Tests
- cobertura por módulo;
- tests fallando/skipped;
- flujos sin automatizar;
- evidencia de última regresión.

### Seguridad
- hallazgos abiertos;
- superficies públicas;
- permisos;
- importador;
- uploads;
- dependencias.

### Performance
- budgets;
- regresiones;
- consultas;
- assets;
- datasets grandes.

### SEO/GEO/AEO
- URLs;
- canonical;
- sitemap;
- schema;
- indexación;
- contenido contradictorio.

### Datos y migración
- schema actual;
- migraciones ejecutadas;
- backups/rollback;
- consistencia;
- idempotencia.

### UX administrador
- tareas críticas;
- errores frecuentes;
- ayuda contextual;
- accesibilidad.

## Informe de auditoría

Formato recomendado:

```text
# Auditoría WLA Inmo — YYYY-MM-DD

## Resumen ejecutivo
## Estado por fase
## Hallazgos críticos
## Hallazgos altos
## Hallazgos medios
## Mejoras
## Tests y cobertura
## Seguridad
## Performance
## SEO/GEO/AEO
## Datos/migración
## UX/admin
## Deuda técnica
## Recomendaciones priorizadas
## Evidencias revisadas
```

## Registro de decisiones

Toda decisión estructural debe usar ADR en `docs/decisions/` con:

- contexto;
- decisión;
- alternativas;
- consecuencias;
- estado (`proposed`, `accepted`, `superseded`, `deprecated`);
- fecha.

## Regla de documentación continua

Una funcionalidad no se considera terminada si el repositorio no permite entenderla sin depender de la conversación donde fue creada.
