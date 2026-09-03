# Quality Gates

## Propósito

Definir condiciones objetivas para avanzar entre desarrollo, QA, merge y release.

## Gate A — Diseño listo

Debe existir:

- alcance;
- criterios de aceptación;
- impacto en arquitectura/datos;
- permisos;
- estrategia de test;
- documentación afectada;
- riesgos conocidos.

## Gate B — Implementación lista para revisión

Requisitos:

- código compila/carga;
- lint sin errores bloqueantes;
- coding standards aplicables;
- tests unitarios/integración afectados agregados o actualizados;
- no existen secretos;
- documentación actualizada;
- migraciones versionadas si existen;
- changelog/nota cuando corresponda.

## Gate C — QA

Requisitos:

- criterios de aceptación validados;
- smoke test PASS;
- E2E crítico PASS o justificación N/A;
- accesibilidad revisada para UI nueva;
- responsive revisado para frontend/admin afectado;
- evidencia adjunta;
- hallazgos bloqueantes cerrados.

## Gate D — Seguridad

Aplicable obligatoriamente a:

- formularios;
- REST;
- importaciones;
- uploads;
- roles/capabilities;
- SQL;
- datos personales;
- migración.

Debe validar:

- authorization antes de acción;
- nonces/CSRF donde corresponda;
- sanitización;
- escaping;
- consultas seguras;
- validación de archivos;
- manejo de errores sin fuga sensible.

## Gate E — Performance

Aplicable a cambios de frontend, consultas, filtros, importación o datos.

Debe confirmar:

- no N+1 nuevo;
- assets cargados solo cuando corresponda;
- sin dependencia innecesaria;
- medición comparativa cuando el impacto pueda ser material;
- consulta explicable/indexable para datasets grandes.

## Gate F — SEO/GEO/AEO

Aplicable a frontend público, URLs, propiedades, taxonomías, filtros, templates o datos estructurados.

Debe confirmar:

- no duplicación de canonical/meta/schema;
- URL estable;
- indexación coherente;
- schema consistente con contenido visible;
- sitemap actualizado si aplica;
- filtros no generan indexación accidental.

## Gate G — Merge

No merge si:

- hay tests obligatorios fallando;
- documentación quedó obsoleta;
- existen conversaciones de review críticas sin resolver;
- hay riesgo de pérdida de datos sin estrategia;
- falta evidencia mínima;
- PR mezcla cambios no relacionados que impiden revisión razonable.

## Gate H — Release

Requiere:

- regression suite;
- instalación limpia;
- upgrade desde versión soportada anterior;
- rollback documentado;
- changelog;
- artefacto reproducible;
- versión coherente;
- docs de usuario actualizadas;
- auditoría de issues bloqueantes = 0.

## Umbrales de severidad

- **P0 / Critical:** pérdida de datos, RCE, auth bypass, sitio inutilizable. Bloquea todo.
- **P1 / High:** función crítica rota, XSS/CSRF relevante, importación corrupta. Bloquea merge/release.
- **P2 / Medium:** degradación relevante con workaround. Puede mergear solo con aceptación explícita y seguimiento.
- **P3 / Low:** defecto menor/cosmético. No necesariamente bloqueante.

## Regla de excepción

Toda excepción a un gate debe quedar escrita en la PR con:

- qué se omite;
- por qué;
- riesgo;
- responsable de aceptación;
- issue de seguimiento si corresponde.
