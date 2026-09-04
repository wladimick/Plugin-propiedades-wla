# Evidencia — Entrada Fase 3 / Plan Import-Export

Estado: `READY_FOR_REVIEW`.

Issue: #43  
Rama: `docs/phase3-import-export-plan`

## Alcance

Este cambio no implementa importación ni exportación funcional. Congela el backlog ejecutable y la regla de evidencia antes de comenzar PR 3.1.

## Decisiones de entrada registradas

- pipeline dividido en PR 3.1–3.10;
- CSV como primera base implementable;
- dry-run obligatorio antes de persistencia masiva;
- identidad externa aislada por `(source_key, external_id)`;
- fallback a `property_code`;
- nunca inferir identidad desde título/dirección;
- vacíos preservan datos existentes por defecto;
- taxonomías desconocidas no se crean automáticamente;
- batches reanudables y con checkpoint;
- Search/Quality/Activity se sincronizan incrementalmente;
- XLSX requiere ADR y benchmark de dependencia antes de incorporarse;
- media remota se separa de la creación de propiedades y se protege contra SSRF;
- exportaciones neutralizan formula injection;
- rollback únicamente cuando pueda demostrarse seguridad.

## QA documental

- el backlog mantiene fuera de alcance frontend, tema, SEO completo, leads, hardening final y migración productiva;
- no introduce nuevas dependencias runtime;
- no modifica código PHP del plugin;
- no modifica datos ni configuración de WordPress;
- no toca `propiedadesmartinez.cl`.

## Siguiente paso

Una vez mergeado este plan, abrir PR 3.1 con dominio de importación, estados de batch, CSV incremental y resolver de identidad read-only, manteniendo toda escritura de propiedades fuera de ese primer PR.
