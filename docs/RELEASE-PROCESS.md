# Proceso de releases

## Objetivo

Publicar versiones reproducibles, testeadas, documentadas y recuperables.

## Versionado

SemVer. Mientras el contrato público no sea estable: `0.x.y`.

## Tipos

- Alpha: desarrollo interno, incompleto.
- Beta: funcional, puede cambiar.
- RC: candidata a producción, solo correcciones.
- Stable: aprobada para producción.

## Checklist de release

### Código
- [ ] `main` estable.
- [ ] versión actualizada.
- [ ] build limpio.
- [ ] dependencias fijadas/auditadas.
- [ ] sin archivos de desarrollo innecesarios en artefacto.

### Tests
- [ ] unit.
- [ ] integration.
- [ ] E2E críticos.
- [ ] regression.
- [ ] instalación limpia.
- [ ] actualización desde versión soportada.
- [ ] tema de referencia + tema de terceros.

### Seguridad
- [ ] hallazgos P0/P1 = 0.
- [ ] permisos revisados.
- [ ] dependencias revisadas.
- [ ] importador/uploads revisados si cambiaron.

### Performance
- [ ] budgets revisados.
- [ ] no regresión crítica.
- [ ] páginas clave medidas.

### SEO/GEO/AEO
- [ ] archive/single.
- [ ] canonical.
- [ ] sitemap.
- [ ] schema.
- [ ] robots/indexación.

### Documentación
- [ ] README.
- [ ] changelog.
- [ ] ayuda de usuario.
- [ ] migraciones.
- [ ] breaking changes.
- [ ] rollback.

## Changelog

Categorías sugeridas:

- Added
- Changed
- Fixed
- Performance
- Security
- SEO/GEO/AEO
- Deprecated
- Removed
- Migration

## Rollback

Antes de release productiva debe conocerse:

- cómo volver al plugin anterior;
- si el schema permite rollback;
- qué datos nuevos podrían perderse;
- backup requerido;
- procedimiento de mantenimiento.

## Artefacto

El ZIP distribuible debe incluir solo archivos necesarios de runtime, assets, traducciones y licencias aplicables. No debe exigir Composer/npm en el servidor productivo.

## Post-release

- smoke test en producción;
- revisar logs;
- revisar sitemap/schema;
- probar una propiedad y un lead;
- revisar importador si fue afectado;
- registrar incidentes/regresiones como issues.
