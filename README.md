# 🧩 Homecenter Sync

> Publica y sincroniza el catalogo de WooCommerce con el marketplace de Homecenter (Seller Center Falabella/Sodimac): credenciales, seleccion de productos, mapeo de SKU y actualizaciones automaticas.

**Versión:** 1.0.0 · **WordPress:** 5.8+ · **PHP:** 7.4+ · **WooCommerce:** requerido · **Licencia:** GPLv2+

📋 [Registro de cambios](CHANGELOG.md) · 🤖 [Reglas para IAs](AGENTS.md) · 📦 [Releases](https://github.com/franciscoblancojn/homecenter-sync/releases)

---

## 💡 Cómo funciona

Conecta el catálogo de **WooCommerce** con el marketplace de **Homecenter** (Seller Center de Falabella/Sodimac, API oficial):

1. Configuras las credenciales de Seller Center (`UserID`, `API Key`, `Seller ID`) y la URL de la API.
2. Descargas el catálogo de marcas y categorías hoja de Homecenter.
3. Seleccionas qué productos (simples o variables) publicar y completas su mapeo (categoría, marca, atributos, variación, etc.) desde la lista o desde la metabox del producto.
4. El plugin encola los cambios y los envía como feeds XML firmados (HMAC-SHA256), respetando el límite de envíos de Seller Center. El estado de cada feed y de cada producto se consulta y se registra.

> Nota: para probar la conexión real se necesitan credenciales válidas de Seller Center.

## ⚙️ Configuración

En el menú **Homecenter** del admin:

| Pestaña | Ajustes |
|---|---|
| Conexión | URL de API, credenciales, operador/país, prueba de conexión, actualización de marcas/categorías, valores por defecto (precio/impuesto, categoría, marca, paquete, stock, SKU) y parámetros de sincronización/cola. |
| Productos | Filtros y listado de productos WooCommerce; acciones masivas para publicar/actualizar, despublicar y consultar estado remoto (selección o todos los resultados del filtro). |
| Feeds | Historial de feeds enviados, estado (`FeedStatus`), errores y acciones para consultar/procesar. |
| Registro | Log técnico de operaciones y errores del plugin. |

## 📋 Requisitos

- WordPress 5.8+
- PHP 7.4+
- WooCommerce: requerido
- Permiso `manage_woocommerce`

## ⚙️ Instalación

1. Descarga el zip de la rama `master`: https://github.com/franciscoblancojn/homecenter-sync/archive/refs/heads/master.zip
2. En WordPress: **Plugins → Añadir nuevo → Subir plugin** y actívalo.
3. Ve a **Homecenter**, revisa los ajustes y guarda.

## 🔄 Actualizaciones

- Usa `FWUUpdate` (librería `wordpress_utils`), configurado en `index.php` (repositorio, rama `master` y token), como en los demás plugins.
- En **Plugins** aparece «Actualizar» cuando hay una release más nueva en GitHub. Para eso, cada versión necesita su release: la crea el workflow al subir el tag.
- `FWUUpdate` instala el zip de la rama `master`, que se descomprime en la carpeta `homecenter-sync-master`. Conviene hacer la **primera instalación también desde ese zip**, para que la carpeta no cambie con la primera actualización: si cambia, WordPress desactiva el plugin.

## 🧑‍💻 Desarrollo

```bash
npm run install          # composer install → libs/ (franciscoblancojn/wordpress_utils)
npm run check            # validaciones: php -l, JS ES5, require_once, secretos y dependencias
npm run push-v -- patch  # o minor | major → publica una versión
```

`npm run push-v`:

1. exige una sección `## [Sin publicar]` en [`CHANGELOG.md`](CHANGELOG.md) y que `npm run check` pase;
2. sube la versión en `index.php`, `package.json` y este README;
3. cambia `## [Sin publicar]` por `## [X.Y.Z] - fecha`;
4. hace commit «Release X.Y.Z», crea el tag `X.Y.Z` y hace push.

Después, GitHub Actions crea la release con `homecenter-sync.zip`.

## 🗂️ Estructura

```
homecenter-sync/
├── index.php                 # Cabecera del plugin, constantes HCS_*, FWUUpdate, carga
├── composer.json             # wordpress_utils, autoloader-suffix HCS
├── libs/                     # Composer → libs (se versiona; no editar)
├── bin/harness.sh            # Validaciones (npm run check)
├── scripts/bump-version.sh   # npm run push-v
├── .github/workflows/        # Release con el zip al subir un tag
└── src/
    ├── _.php                 # Cargador maestro
    ├── data/                 # HCS_USE_DATA_BASE / HCS_USE_DATA_CONFIG (wp_options)
    ├── services/             # Lógica; HCS_LOG_SERVICE
    ├── hooks/                # Integración con WordPress / WooCommerce
    ├── api/                  # Guardado de ajustes (admin-post) y AJAX
    ├── page/                 # Menú «Homecenter» y secciones (FWUPage)
    ├── js/admin.js           # ES5 + jQuery
    └── css/admin.css
```
