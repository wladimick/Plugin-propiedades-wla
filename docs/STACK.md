# Stack técnico

## Principio general

WLA Inmo debe usar el stack más cercano posible al core de WordPress para reducir dependencias, superficie de ataque, peso y complejidad operativa.

## Backend

- **PHP:** 8.1+ como objetivo inicial. Validar compatibilidad antes de fijar mínimo definitivo.
- **WordPress:** versión estable soportada según matriz de compatibilidad del proyecto.
- **Arquitectura PHP:** namespaces `WLA\Inmo\...`, clases por dominio y servicios pequeños.
- **Autoload:** Composer PSR-4 para desarrollo/build; el paquete distribuible debe incluir lo necesario para funcionar sin ejecutar Composer en producción.
- **Base de datos:** APIs WordPress + `$wpdb` con consultas preparadas donde exista tabla propia.
- **Contenido:** CPT nativo para propiedades.
- **Taxonomías:** nativas para clasificación navegable e indexable.
- **Índice de búsqueda:** tabla propia optimizada cuando el modelo final lo justifique.
- **REST:** WordPress REST API, endpoints propios versionados cuando sean necesarios.
- **Cron/background:** WP-Cron inicialmente; jobs por lotes y reanudables para importaciones.

## Frontend

- HTML semántico renderizado en servidor.
- CSS nativo, modular y con variables CSS.
- JavaScript vanilla ES moderno.
- Sin jQuery como dependencia frontend.
- Sin framework SPA obligatorio.
- Progressive enhancement: listado y ficha deben seguir siendo utilizables sin JS salvo funciones realmente interactivas.
- SVG inline o sprite controlado para iconografía.

## WLA Inmo Light

- Tema WordPress clásico/híbrido ultraligero con `theme.json` cuando aporte valor.
- Sin page builder obligatorio.
- Sin lógica inmobiliaria propia.
- Templates que consumen contratos públicos del plugin.

## Administración

- UI basada en WordPress Admin con componentes propios ligeros.
- JavaScript vanilla o componentes WordPress solo cuando reduzcan complejidad real.
- No introducir React como requisito global del plugin.
- Accesibilidad WCAG 2.2 AA como objetivo de interfaz.

## Importación

Formatos objetivo:

- CSV nativo;
- JSON nativo;
- XLSX mediante librería madura y aislada, evaluada por seguridad, tamaño y mantenimiento.

Toda librería externa debe:

1. quedar inventariada;
2. tener licencia compatible;
3. tener motivo documentado;
4. evitar ejecutarse en frontend si no es necesario;
5. poder actualizarse de forma controlada.

## Calidad

Herramientas previstas:

- Composer
- PHPUnit
- WordPress PHPUnit test suite
- PHP_CodeSniffer
- WordPress Coding Standards
- PHPStan o equivalente, nivel progresivo
- ESLint para JS si existe suficiente JS para justificarlo
- Stylelint para CSS cuando el volumen lo justifique
- Playwright para flujos E2E críticos
- Lighthouse CI para performance/accesibilidad/SEO
- WP-CLI para pruebas, migración y herramientas operativas cuando sea útil

## CI/CD

GitHub Actions debe validar en PR:

1. syntax/lint PHP;
2. coding standards;
3. análisis estático;
4. unit tests;
5. integration tests;
6. build/distribución;
7. tests E2E seleccionados;
8. security checks configurados;
9. documentación requerida.

La matriz exacta se implementará incrementalmente según `DEVELOPMENT-PHASES.md`.

## Seguridad

- Nonces WordPress.
- Capabilities específicas.
- Sanitización en entrada.
- Escaping contextual en salida.
- `$wpdb->prepare()` para SQL parametrizado.
- REST `permission_callback` obligatorio.
- Validación MIME/extensión para uploads.
- Restricciones SSRF para descargas remotas.
- Sin edición automática de `wp-config.php` como comportamiento normal.
- Sin secretos en repo.

## Performance budget inicial

Objetivos a validar y ajustar con mediciones reales:

- evitar assets globales innecesarios;
- cero requests a APIs externas durante render crítico si existe caché;
- imágenes responsive;
- lazy-load bajo el fold;
- consultas de archivo acotadas e indexadas;
- evitar N+1 queries;
- JS propio frontend lo más cercano posible a cero en páginas estáticas;
- ninguna dependencia de WooCommerce/Elementor para operar.

## Compatibilidad

Matriz que deberá automatizarse antes de 1.0:

- PHP mínimo soportado → última estable;
- WordPress mínimo soportado → última estable;
- instalación limpia;
- actualización desde versión anterior;
- WLA Inmo Light;
- al menos un tema core de WordPress;
- tema de terceros razonablemente estándar;
- coexistencia con plugin SEO popular sin duplicar metadatos.

## Dependencias prohibidas como requisito del core

- WooCommerce
- Elementor
- ACF
- WPCode
- plugins de filtros de productos
- jQuery frontend

Pueden existir adaptadores opcionales de migración o compatibilidad, pero nunca convertirse en requisito de ejecución del core.
