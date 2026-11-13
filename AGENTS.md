# Homecenter Sync — Reglas para IAs

## Código
- **PHP 7.4+**, estándares de WordPress. Clases y funciones con prefijo `HCS_`.
- **JavaScript ES5 + jQuery** (sin arrow functions, `let`/`const` ni template literals). Funciones con prefijo `hcs_`.
- **CSS** con prefijo `hcs-` (las clases `fwue-` son de `wordpress_utils`).
- Escapar toda salida (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`) y sanitizar toda entrada.

## Seguridad
- Guardar ajustes: `check_admin_referer(HCS_API_SETTINGS::ACTION)` + `current_user_can(HCS_CAPABILITY)`.
- AJAX: `check_ajax_referer()` + `current_user_can(HCS_CAPABILITY)` en un `guard()` de cada clase `HCS_API_*` (`HCS_API_SETTINGS::NONCE`, `HCS_API_PRODUCTS::NONCE`).
- La API Key de Seller Center **nunca se muestra** en el formulario: si el campo llega vacío se conserva la guardada (`HCS_USE_DATA_CONFIG::sanitize`).
- El token de GitHub de `FWUUpdate` está en `index.php` (`token_array_split`), como en los demás plugins del autor. No agregar otras claves o secretos al código: `npm run check` falla si encuentra un token escrito de corrido.

## Arquitectura
- `index.php`: cabecera y constantes `HCS_*`. Sin lógica. WooCommerce es **obligatorio**: sin él solo se muestra un aviso en el admin.
- `src/_.php`: cargador maestro (data → services → hooks → api → page). Cada carpeta tiene su `_.php`; un archivo nuevo se agrega a ese `_.php` con `require_once HCS_DIR . 'src/…'`.
- `src/data/`: opciones de `wp_options` (`HCS_USE_DATA_BASE` y sus hijas). `HCS_USE_DATA_CONFIG::DEFAULTS` es la única lista de ajustes; `sanitize()` la normaliza.
- `src/services/`: lógica (clases `HCS_*` estáticas). Una sola fuente por regla de negocio.
  - `HCS_API`: cliente Seller Center (HMAC-SHA256, GET/POST XML, `FeedStatus`, límite entre feeds).
  - `HCS_PRODUCT`: mapeo WooCommerce ↔ Homecenter, meta `_hcs_*`, `sellerSku`, XML por producto/lote/imágenes.
  - `HCS_QUEUE`: cola (`HCS_QUEUE` option) + envío por lotes; `HCS_FEEDS::poll()` concilia estado remoto.
  - `HCS_CATALOG`: caché de marcas/categorías hoja.
- `src/hooks/`: integración con WordPress / WooCommerce (`add_action` / `add_filter`): cron, metabox de producto, encolado al guardar/borrar.
- `src/api/`: entradas `admin-post` y AJAX (ajustes, prueba, catálogo, feeds, cola, acciones masivas/remotas de productos).
- `src/page/`: menú (`add.php`), página con pestañas `FWUPage` (`page.php`) y una sección por pestaña (`sections/<key>.php`: conexión, productos, feeds, registro).
- Sincronización: v1 soporta **simples y variables + imágenes**. Cada variación se envía con `ParentSku`; despublicar = `ProductUpdate` con `Status inactive`; eliminar cola `ProductRemove`.
- Plugin genérico: **no** usar nombres ni textos de un sitio concreto.
- Rendimiento: CSS/JS del admin solo en la página del plugin y en la edición de producto.
- Toda operación relevante queda en el log: `HCS_LOG_SERVICE::add('tipo', [...])`.
- `libs/` es Composer (`npm run install`) y se versiona: el zip de `master` que instala `FWUUpdate` lo necesita. No editar a mano.

## Publicar
- Anotar los cambios en `CHANGELOG.md` bajo `## [Sin publicar]`.
- `npm run push-v -- patch|minor|major`.

