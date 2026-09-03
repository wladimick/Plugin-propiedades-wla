# Evidencias de QA y auditoría

Esta carpeta se usa cuando conviene versionar evidencia textual o pequeños artefactos de una PR, fase, migración o auditoría.

## Estructura

```text
docs/evidence/
└── YYYY-MM/
    └── PR-0000-descripcion/
        ├── README.md
        ├── qa.md
        ├── performance.md
        ├── security.md
        └── migration.md
```

## Qué guardar

- resultados manuales relevantes;
- tablas de comparación;
- benchmarks resumidos;
- decisiones QA;
- matrices de migración;
- evidencia que no quede ya preservada de forma suficiente por CI.

## Qué NO guardar

- passwords;
- tokens/API keys;
- bases productivas;
- correos/teléfonos reales de leads;
- información personal innecesaria;
- archivos binarios enormes;
- logs con secretos.

## README por evidencia

Debe indicar:

- PR/issue;
- fecha;
- entorno;
- versión/commit;
- objetivo;
- pruebas;
- resultado;
- hallazgos;
- enlaces a GitHub Actions u otros artefactos.
