# Seguridad

## Principio

WLA Inmo debe seguir el principio de menor privilegio y tratar toda entrada externa como no confiable.

## Autorización

Todas las acciones administrativas deben verificar capabilities específicas.

Capabilities sugeridas:

```text
wla_inmo_manage_properties
wla_inmo_publish_properties
wla_inmo_import_properties
wla_inmo_manage_settings
wla_inmo_manage_leads
wla_inmo_manage_seo
```

Evitar depender exclusivamente de `manage_options`.

## CSRF

Toda acción que modifique datos debe usar nonce y validar origen/capability.

Esto aplica a:

- formularios admin;
- AJAX;
- REST writes;
- importaciones;
- acciones masivas;
- ajustes;
- eliminación/archivo.

## Entrada y salida

- Sanitizar al guardar según tipo de dato.
- Validar, no solo sanitizar.
- Escapar al renderizar según contexto.
- Usar consultas preparadas para SQL propio.
- Nunca confiar en nombres de archivo, MIME enviados por cliente o URLs remotas.

## Archivos y multimedia

- Validar extensión y MIME real.
- Limitar tamaños.
- Permitir solo tipos requeridos.
- Usar APIs nativas de media de WordPress.
- No ejecutar contenido subido.
- URLs remotas solo mediante HTTP/HTTPS y validación SSRF apropiada.

## Importador

El importador es una superficie crítica.

Requisitos:

- capability dedicada;
- nonce;
- límites de filas/tamaño configurables;
- procesamiento por lotes;
- validación de columnas;
- rechazo de datos que intenten introducir HTML/script donde no corresponde;
- logs de errores;
- no exponer rutas internas;
- protección contra Spreadsheet Formula Injection en exportaciones.

## Formularios públicos

Consultas y solicitudes de visita:

- nonce o mecanismo equivalente cuando sea viable;
- honeypot;
- rate limiting;
- validación estricta de email/teléfono;
- protección antispam opcional (por ejemplo Turnstile) sin convertirla en dependencia obligatoria;
- no revelar si un correo administrativo existe;
- mensajes de error genéricos donde corresponda.

## Roles

### Administrador inmobiliario

Acceso al catálogo, importación, leads y configuración funcional.

### Editor de propiedades

Acceso a crear/editar propiedades y multimedia, sin acceso a ajustes críticos o importaciones destructivas.

## Auditoría

Registrar eventos importantes:

- propiedad creada/actualizada/eliminada;
- cambios de precio/estado;
- importación iniciada/finalizada;
- ajustes globales;
- errores de jobs.

No registrar secretos, contraseñas, tokens ni contenido sensible innecesario.

## Configuración WordPress

WLA Inmo no debe editar `wp-config.php` como comportamiento normal.

Las recomendaciones de seguridad de plataforma se mostrarán como diagnóstico/documentación, no como modificaciones automáticas de archivos críticos salvo una función futura explícita, separada y aprobada.

## REST API

Si se expone API:

- lecturas públicas solo para datos publicados;
- campos privados excluidos;
- escrituras autenticadas y autorizadas;
- rate limits donde tenga sentido;
- schema de argumentos estricto.

## Privacidad

Distinguir ubicación pública de dirección privada.

No exponer en HTML, JSON-LD o API una dirección que el administrador haya marcado como privada.

Los leads deben almacenarse con retención configurable y cumplir la política de privacidad del sitio.

## Dependencias

- Evitar librerías innecesarias.
- Versionar dependencias.
- No cargar código remoto ejecutable.
- Mantener una lista explícita de librerías de terceros.

## Actualizaciones

Antes de una versión estable:

- revisión de permisos;
- revisión de XSS;
- revisión de CSRF;
- revisión de SQL injection;
- revisión de uploads;
- revisión de SSRF en descarga de imágenes;
- pruebas de roles;
- pruebas de importación malformada.

## Diagnóstico

La pantalla de diagnóstico debe poder copiar/exportar información técnica segura para soporte, excluyendo:

- claves API;
- credenciales;
- cookies;
- nonces;
- tokens;
- contraseñas;
- datos personales innecesarios.