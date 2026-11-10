# Mindsoft WP Migration

Plugin de WordPress para exportar, importar y restaurar sitios completos (base de datos, media, plugins y temas) en archivos `.wpress`, **sin límite de tamaño**.

Es un fork de [All-in-One WP Migration](https://wordpress.org/plugins/all-in-one-wp-migration/) 7.113 de ServMask (GPLv3). Respecto al original, incluye las funciones que allí son de pago:

| Función | All-in-One WP Migration (gratis) | Mindsoft WP Migration |
|---|---|---|
| Importar desde el navegador | Limitado por `upload_max_filesize` de PHP | **Sin límite** (subida por trozos) |
| Exportar | Sí | Sí |
| Restaurar desde la lista de Backups | De pago (Unlimited Extension) | **Sí** |
| WP-CLI (`wp ai1wm ...`) | De pago | **Sí** |
| Avisos de compra, botones de proveedores de pago, formulario de feedback | Sí | Eliminados |
| Formato `.wpress` | — | **Compatible** en ambos sentidos |

Los backups hechos con el plugin original se importan en este y viceversa.

---

## Instalación

1. Descarga o genera `mindsoft-wp-migration.zip` (ver [Generar el zip](#generar-el-zip)).
2. En WordPress: **Plugins → Añadir nuevo → Subir plugin** y activa **Mindsoft WP Migration**.
3. El menú aparece como **Mindsoft WP Migration** en el escritorio.

> ⚠️ **No tengas activos a la vez este plugin y el All-in-One WP Migration original.** Comparten nombres de clases internas y WordPress daría un error fatal. Desactiva y borra el original antes de instalar este. Los backups que ya tenías en `wp-content/ai1wm-backups` se conservan y aparecen en **Backups**.

Requisitos: los mismos que el original (WordPress reciente y PHP de 64 bits). Los sitios **multisite no están soportados**.

---

## Uso desde el escritorio

### Exportar
**Mindsoft WP Migration → Export → Export Site To → File.** El backup se guarda en la carpeta de backups y se puede descargar desde la ventana de progreso o desde **Backups**.

Las opciones avanzadas permiten excluir media, temas, plugins, base de datos, comentarios spam, revisiones, o cifrar el backup con contraseña.

### Importar
**Mindsoft WP Migration → Import →** arrastra el `.wpress` o selecciónalo.

El archivo se sube en trozos (unos 4,5 MB cada uno por defecto). Si un trozo falla por un corte de red o un error del servidor, se reintenta hasta 10 veces antes de dar error. No importa el `upload_max_filesize` del hosting.

> **Nginx:** si `client_max_body_size` es menor que el tamaño de trozo (el valor por defecto de Nginx es 1 MB), cada trozo devolverá error 413. Sube ese valor a 8M o más, o reduce el tamaño de trozo con el filtro `ai1wm_max_chunk_size` (ver [Ajustes](#ajustes)).

### Restaurar un backup del servidor
**Mindsoft WP Migration → Backups →** menú del backup **→ Restore.** Usa el archivo directamente desde la carpeta de backups, sin descargarlo ni subirlo otra vez. El backup original no se borra.

### Después de importar o restaurar: Divi
El plugin limpia automáticamente la caché de CSS de Elementor, pero **no la de Divi**. Si tras importar el diseño se ve roto o incompleto:

**Divi → Opciones del tema → Constructor → Avanzado → Generación de archivos CSS estáticos → Borrar** y recarga con Ctrl+F5. Si hay CDN (Cloudflare, etc.), purga también su caché.

---

## WP-CLI

Ejecuta los comandos desde la raíz de la instalación de WordPress, con el usuario del sitio (no root).

```bash
wp ai1wm export                                    # crea un backup en la carpeta de backups
wp ai1wm export --exclude=spam-comments,post-revisions
wp ai1wm export --password='secreto'               # backup cifrado
wp ai1wm backup                                    # alias de export

wp ai1wm restore                                   # lista los backups disponibles
wp ai1wm restore <archivo>.wpress                  # restaura uno (pide confirmación)
wp ai1wm restore <archivo>.wpress --yes            # sin confirmación

wp ai1wm import /ruta/al/sitio.wpress --yes        # importa desde cualquier ruta del servidor
```

| Opción | Comandos | Descripción |
|---|---|---|
| `--exclude=<lista>` | export, backup | Separado por comas: `media`, `themes`, `plugins`, `muplugins`, `database`, `spam-comments`, `post-revisions`, `email-replace` |
| `--password=<clave>` | export, backup | Cifra el backup. Al importarlo se pedirá la contraseña |
| `--yes` | import, restore | Responde "sí" a la confirmación; útil en scripts |

Notas:
- El progreso se muestra en la terminal. No depende del navegador ni de los timeouts del servidor web, así que es la forma recomendada para sitios grandes.
- `import` copia primero el archivo a la carpeta temporal del plugin. Hace falta espacio libre para una copia extra del backup, que se borra al terminar.
- Si el backup está cifrado, el comando pide la contraseña de forma interactiva.

### Backups automáticos con cron
No hace falta una función de "programación": basta con cron del sistema. Por ejemplo, cada noche a las 3:00:

```cron
0 3 * * * cd /home/USUARIO/htdocs/DOMINIO && wp ai1wm export --exclude=spam-comments >> ~/ai1wm-cron.log 2>&1
```

Los backups se acumulan en la carpeta de backups: bórralos de vez en cuando desde **Backups**.

---

## Ajustes

**Tamaño de trozo de subida.** Por defecto es el menor de `post_max_size`, `upload_max_filesize` y 5 MB, menos un 10% de margen. Para cambiarlo, por ejemplo a 1 MB en servidores con Nginx a 1M, pon esto en un mu-plugin o en el `functions.php` del tema hijo:

```php
add_filter( 'ai1wm_max_chunk_size', function () {
	return 1 * 1024 * 1024;
}, 20 );
```

---

## Desarrollo

### Estructura del repositorio
```
src/mindsoft-wp-migration/   el plugin (lo que se instala en WordPress)
tests/                       pruebas que no se distribuyen con el plugin
```

Se mantiene el prefijo interno `ai1wm_` (clases, opciones, hooks) para no romper la compatibilidad con los backups `.wpress` ni con instalaciones existentes.

### Historial
El primer commit (`6c532d7`) es el plugin original **sin modificar**. Para ver todo lo que ha cambiado Mindsoft:

```bash
git diff 6c532d7 -- src/
```

Los cambios propios llevan un comentario `// Mindsoft:` en el código.

### Dónde está cada cambio
| Cambio | Archivos |
|---|---|
| Nombre, cabecera y aviso de licencia | `mindsoft-wp-migration.php` |
| Menú, avisos eliminados, `chunk_size`, Restore en Backups, hooks de página | `lib/controller/class-ai1wm-main-controller.php` |
| Subida por trozos (cliente) | `lib/view/assets/javascript/import.min.js` (función `upload`) |
| Subida por trozos (servidor) | `lib/model/import/class-ai1wm-import-upload.php` (parámetro `chunk_offset`) |
| WP-CLI | `lib/vendor/servmask/command/class-ai1wm-wp-cli-command.php` |
| Progreso en la terminal | `lib/model/class-ai1wm-status.php` |
| Botones de proveedores de pago eliminados | `lib/controller/class-ai1wm-{export,import}-controller.php` (`buttons()`) |

> ⚠️ **No cambies el título del menú** ("Mindsoft WP Migration") sin actualizar las comprobaciones `mindsoft-wp-migration_page_ai1wm_*` del main controller. WordPress deriva el identificador de cada página de ese título y, si no coinciden, las páginas Import y Backups se quedan sin JavaScript.

### Pruebas
```bash
php -d zend.assertions=1 -d assert.exception=1 tests/chunk-upload-test.php src/mindsoft-wp-migration "$(mktemp -d)"
```
Comprueba que un archivo subido en trozos desordenados, con un trozo reintentado, se reensambla idéntico.

Comprobar la sintaxis de todo el plugin:
```bash
find src -name '*.php' -print0 | xargs -0 -n1 php -l | grep -v 'No syntax errors'
node --check src/mindsoft-wp-migration/lib/view/assets/javascript/import.min.js
```

### Generar el zip
Desde lo que está commiteado (recomendado para publicar):
```bash
git archive --format=zip --prefix=mindsoft-wp-migration/ \
  -o mindsoft-wp-migration.zip HEAD:src/mindsoft-wp-migration
```
Los `.zip` y `.wpress` están en `.gitignore`.

### Actualizar a una versión nueva del original
1. Descarga la nueva versión de All-in-One WP Migration.
2. En una rama, sustituye `src/mindsoft-wp-migration` por el original y haz commit ("Import upstream X.Y").
3. Aplica encima los cambios de Mindsoft: `git diff 6c532d7 <último-commit-mindsoft> -- src/ | git apply -3`, y resuelve los conflictos.
4. Revisa en especial `import.min.js`. Es código minificado, así que un cambio de ServMask puede mover la función `upload` y el parche no aplicará limpio.

---

## Licencia

GPLv3, la misma que el original. Copyright © 2014-2025 ServMask Inc.; modificaciones © 2026 Mindsoft.

- Uso interno (sitios propios o de clientes): sin obligaciones adicionales.
- Si se distribuye a terceros, debe seguir siendo GPLv3, con su código fuente y conservando los avisos de copyright.
- No usar la marca "All-in-One WP Migration" ni "ServMask" para presentar este plugin. Este fork no está afiliado ni respaldado por ServMask.
