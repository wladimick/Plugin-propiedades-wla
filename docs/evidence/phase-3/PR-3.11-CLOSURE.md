# Cierre post-merge — Fase 3.11 Rollback seguro best-effort

Estado: `DONE / MERGED`.

## Referencias

- Issue: #66 — `closed / completed`
- PR: #67 — `merged`
- Rama de implementación: `feat/phase3-safe-rollback`
- Head final del PR: `ec8b7b5cb30667af36eab28aad1241df2c0d6fbe`
- Squash merge en `main`: `4e53a89af1f3fd1a20d139ed5894552b223bf0f5`
- Fecha de merge: 2026-09-08
- Evidencia pre-merge: `docs/evidence/phase-3/PR-3.11-ROLLBACK.md`

## Resultado

D38 quedó implementada como rollback **best-effort y fail-closed**.

- updates solo restauran el scope tocado si el estado actual coincide con `after`;
- creates solo se eliminan si su fingerprint conservador sigue coincidiendo;
- cambios posteriores ambiguos bloquean en lugar de forzar;
- preview es read-only;
- confirmación revalida revision + preview hash;
- ejecución usa `rollback_processing`, slices bounded, lock TTL y revalidación por fila;
- media restaura referencias y no elimina attachments por presunción;
- capability destructiva `rollback_wla_imports` queda separada y Administrator-only por defecto;
- Activity no registra snapshots ni payload privado;
- crash create-before-checkpoint mantiene la semántica original de create.

## QA final

Head final `ec8b7b5cb30667af36eab28aad1241df2c0d6fbe`:

- 16/16 workflows SUCCESS;
- Import Rollback Integration SUCCESS;
- WordPress 6.6.2 / PHP 8.1 / MySQL 8 SUCCESS;
- WordPress latest / PHP 8.3 / MySQL 8 SUCCESS;
- Phase 1 CI SUCCESS;
- Administration Quality Gate SUCCESS;
- CSV/JSON/XLSX/Remote Media/runner/executor/persistence/UI regresiones SUCCESS;
- review comments: 0;
- reviews: 0;
- inline threads: 0;
- P0/P1 abiertos conocidos: 0.

Artifact pre-merge:

- nombre: `wla-inmo-0.1.0-alpha-quality`;
- artifact ID: `10054036129`;
- digest: `sha256:67a33a0584146041e3ecc777cc77240e61259eebea9e2ca5d5449719d38c6347`.

## Findings corregidos

1. MySQL 8 / `row_number` → `source_row`, schema v2.
2. WPCS exception chaining → supresiones quirúrgicas, estándar global intacto.
3. Role capability migration → `RoleManager::VERSION = 2` + test de upgrade.
4. PHPStan lock/snapshot narrowing → seam impuro documentado + tipos corregidos, sin desactivar reglas.

## Estado de fase

- 3.11: `DONE`
- 3.12: `NEXT`

El siguiente trabajo es el **Quality Gate final de Fase 3**.

## Producción

`propiedadesmartinez.cl` no fue modificada durante 3.11.