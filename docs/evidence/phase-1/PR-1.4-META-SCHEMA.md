# Evidencia — Fase 1 / PR 1.4 Meta schema canónico y validación

Estado documental: `DONE`.

Issue: #11  
PR: #12  
Rama: `feat/phase1-meta-schema`  
Squash merge: `344a681653970c3c9a3237c15aef99fbb281bb4b`

## Objetivo

Definir una fuente única, tipada, sanitizada y validable para los datos de propiedad que no pertenecen al contenido nativo de WordPress ni a las taxonomías WLA Inmo.

## Componentes

### `Properties\\MetaSchema`

- registra postmeta únicamente sobre `wla_property`;
- storage keys `_wla_inmo_*`;
- `single = true`;
- raw meta `show_in_rest = false` en esta fase;
- sanitizer por campo;
- auth callback basada en `edit_post`;
- clasificación de campos elegibles para presentación pública vs internos.

### `Properties\\Sanitizer`

Normaliza texto, booleanos, números, moneda CLP/UF/USD, fechas, coordenadas, attachment IDs y URLs HTTP/HTTPS.

### `Properties\\Validator`

Valida antes de persistir y devuelve códigos de error estables. Los campos opcionales vacíos son válidos para permitir borradores; la completitud se evaluará en Calidad del catálogo.

## Fuente única

No existen en meta schema `operation`, `property_type`, `region`, `commune` ni `sector`: permanecen en taxonomías. Título, descripción, extracto, imagen destacada y revisiones permanecen en WordPress nativo.

## Privacidad

Campos internos/no públicos iniciales:

- `external_id`;
- `private_address`;
- `home_order`;
- `indexable`;
- `internal_notes`.

`private_address` e `internal_notes` nunca deben salir automáticamente al frontend, Schema o API pública.

## Tests ejecutados

Workflow run: `33818911232`  
Job: `PHP 8.1 / Build Smoke`  
Resultado: `SUCCESS`

`tests/smoke/meta-schema.php` cubrió 37 campos canónicos, meta keys, ausencia de duplicados con taxonomías, separación public/private, registro sobre `wla_property`, raw REST deshabilitado, autorización, sanitización y validación positiva/negativa.

## Artefacto CI

- Artifact ID: `9917533090`
- Nombre: `wla-inmo-0.1.0-alpha.1`
- Tamaño: `24277` bytes
- Digest: `sha256:5c95cca62ad5c80836b341abfe3abe9c5f701b54ad4c83e9c81f179386eb73f2`
- Expiración informada: 2026-12-02

El ZIP fue construido en CI y pasó el smoke de release antes de publicarse como artifact.

## Producción

No afectada. La PR definió contrato/registro de metadatos, pero no migró propiedades ni escribió valores de negocio existentes.

## Cierre

PR #12 mergeada con CI verde y evidencia reproducible. El siguiente alcance es PR 1.5 — índice de búsqueda reconstruible.
