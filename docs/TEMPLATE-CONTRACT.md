# Contrato de plantillas — WLA Inmo

## Objetivo

Permitir que cualquier tema personalice la presentación inmobiliaria sin registrar CPT, taxonomías, precios ni lógica de negocio.

## Resolver

La clase base es:

```text
Frontend\\TemplateResolver
```

Para una plantilla lógica como:

```text
single-property.php
```

el resolver busca:

1. `wla-inmo/single-property.php` mediante el sistema de templates de WordPress, respetando tema hijo/tema padre;
2. `plugin/wla-inmo/templates/single-property.php` como fallback cuando esa plantilla exista.

La implementación visual de las plantillas fallback completas corresponde a Fase 4. Fase 1.7 establece el contrato y el resolver, no adelanta el frontend final.

## Seguridad de paths

El nombre de template:

- debe ser relativo;
- debe terminar en `.php`;
- no puede contener `..`;
- no puede contener null bytes;
- cada segmento se limita a caracteres de nombre de archivo seguros.

El usuario administrativo nunca introduce directamente paths arbitrarios para incluir archivos.

## Overrides de temas

Ejemplo:

```text
mi-tema/
└── wla-inmo/
    ├── single-property.php
    └── parts/
        └── property-card.php
```

WLA Inmo Light utilizará exactamente este contrato; no recibe una vía privilegiada distinta a otros temas.

## Hooks públicos mínimos

### `wla_inmo_template_candidates`

Permite a código de integración modificar candidatos antes de resolver el template.

### `wla_inmo_template_path`

Permite reemplazar el path final una vez localizado. Es un hook para desarrolladores con código confiable, no una entrada de usuario.

## Regla de estabilidad

Estos hooks son la superficie pública mínima de Fase 1.7. Nuevos hooks se agregan cuando exista un caso real de extensión y se documentan antes de considerarlos parte de la API estable 1.0.
