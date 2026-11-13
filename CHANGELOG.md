# 📋 Registro de cambios

Todos los cambios importantes del plugin Homecenter Sync. Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/).

> ✍️ Antes de publicar, agrega arriba una sección `## [Sin publicar]` con los cambios. `npm run push-v -- patch|minor|major` la renombra a `## [X.Y.Z] - fecha`, y no publica si esa sección no existe.

## [Sin publicar]

### ✨ Añadido
- 🎉 Primera versión: integración WooCommerce ↔ Homecenter (Seller Center Falabella/Sodimac) con URL y credenciales configurables.
- ⚙️ Pestaña **Conexión**: endpoint, User ID/API Key/Seller ID, operador, prueba de conexión, descarga de catálogo (marcas y categorías hoja), valores por defecto y parámetros de sincronización.
- 📦 Pestaña **Productos**: listado/filtrado de productos simples y variables, selección masiva para publicar/actualizar, despublicar y consultar estado remoto en Homecenter.
- 📝 Metabox **Homecenter** en la edición de producto: habilitar publicación, SellerSku, categoría/marca/GTIN/condición/IVA, atributos, mapeo de variaciones y estado por variación.
- 🔁 Cola asíncrona con WP-Cron (cada 5 min) respetando el límite de feeds de Seller Center, consulta de **FeedStatus**, registro de feeds y log técnico.
- 🖼️ Sincronización de imágenes (JPG/PNG, máx. 8) y soporte para productos simples y variables; despublicar envía `Status inactive` y al borrar se encola `ProductRemove`.

### 🛠️ Corregido
- 🔄 Al habilitar por primera vez un producto desde su ficha, o al deshabilitar uno ya publicado, se encola el envío (antes solo quedaba guardado).
- 🧩 Las variaciones de un mismo padre variable viajan juntas en el mismo feed, con el `ParentSku` persistente aunque se reordenen o borren variaciones.
- 🖼️ El feed de imágenes solo se envía si las imágenes cambiaron desde el último envío (huella `images_hash`).
- 🔁 Los errores temporales de Seller Center (red, HTTP 429/5xx, `E003`/`E006`/`E429`) dejan el producto en cola para reintentar en vez de descartarlo.
- 📋 `GetProducts` consulta en bloques de 100 SKU y un lote de productos nunca supera los 1000.
- ✅ Un feed con `FailedRecords > 0` se marca como error aunque `FeedErrors` venga vacío, y los productos sin acción pendiente se descartan de la cola.
- 🏷️ Las variaciones ya no heredan el GTIN del padre (cada una puede tener su propio EAN).

