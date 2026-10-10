# LabKeep · ITMA 2

Aplicación web adaptable a celular y escritorio para consultar y administrar inventarios de laboratorios del ITMA 2. La interfaz usa una identidad visual institucional y puede instalarse en Android como aplicación web progresiva (PWA). El servidor está desarrollado en PHP con Laravel; el navegador consume una API JSON de la misma aplicación.

## Qué hace

- Administra tres espacios de inventario: Laboratorio, Oficina y Sistemas. Los usuarios ven solo los espacios que tienen autorizados; el administrador puede cambiar entre ellos sin perder la sección que estaba consultando.
- Organiza sustancias, materiales de laboratorio, material didáctico y equipos. Permite buscar, filtrar, editar, exportar a CSV e identificar registros mediante QR.
- Registra solicitudes de préstamo y cambios pendientes. Los perfiles de administración pueden aprobar o rechazar las operaciones; los cambios relevantes quedan en el historial.
- Conserva archivos adjuntos privados, protege las rutas por cuenta e inventario y valida los archivos permitidos.
- Mantiene una consulta del archivo histórico de los datos importados.

## Requisitos

- PHP 8.3 o posterior con extensiones PDO SQLite para desarrollo; para producción puede usarse MySQL.
- Composer.
- PowerShell en Windows si se utilizará el iniciador incluido.
- Node.js solo hace falta si se modifican y reconstruyen los recursos de Vite. Los archivos web compilados ya están en `public/assets`.

## Iniciar en Windows

1. Abre PowerShell en la carpeta del proyecto.
2. Ejecuta:

   ```powershell
   .\iniciar-local.ps1
   ```

   El iniciador comprueba PHP y Composer, prepara `.env`, crea la clave local, prepara SQLite y las tablas, comprueba que haya un administrador e inicia el servidor.
3. Abre [http://127.0.0.1:8000](http://127.0.0.1:8000). Deja abierta la ventana de PowerShell mientras uses la aplicación; detén el servidor con `Ctrl+C`.

La base de datos local actual está preparada para entrar con la cuenta configurada en este equipo. Después puedes cambiar su contraseña desde **Mi cuenta**, en el menú de usuario. La contraseña nueva debe tener al menos 12 caracteres, mayúsculas, minúsculas, un número y un símbolo. El archivo de base local está excluido de Git; al instalar el proyecto en otro equipo se crea un administrador nuevo.

En una instalación nueva, el iniciador pedirá usuario y correo si todavía no existe un administrador. Después de arrancar el proyecto por primera vez, puedes iniciar el servidor manualmente con `php artisan serve --host=127.0.0.1 --port=8000`.

## Cómo funciona por dentro

1. Laravel entrega la página desde `routes/web.php` y `resources/views/app.blade.php`.
2. El navegador carga la interfaz móvil desde `public/assets/app.js` y `public/assets/app.css`. La app envía solicitudes JSON a `/api/v1`; mantiene la sesión del mismo sitio y el token CSRF de Laravel.
3. Los controladores de `app/Http/Controllers` validan las solicitudes. Los servicios de `app/Services` reúnen operaciones de inventario y préstamos; Eloquent lee y guarda los modelos en `app/Models`.
4. El middleware verifica que la sesión esté activa y que el usuario tenga permiso para el inventario seleccionado. El servidor aplica ese permiso en cada solicitud, no solo en la interfaz.
5. Las migraciones en `database/migrations` describen las tablas. SQLite es la base local inicial; el destino puede usar MySQL cambiando la conexión de Laravel.

Las rutas principales de la API están en `routes/web.php`. La interfaz usa sesión de Laravel, no necesita una URL externa de API ni una conexión directa desde el navegador a la base de datos. La base debe permanecer en el servidor y solo Laravel debe acceder a ella.

## Orden de carpetas y archivos

El proyecto sigue la estructura de Laravel: el código de la aplicación vive en `app`, las rutas declaran cómo se entra a cada función, `database` mantiene la estructura de datos y `public` contiene lo que el navegador puede descargar. Este mapa muestra las carpetas principales; `vendor`, cachés y archivos temporales se omiten porque son dependencias o datos generados.

```text
labkeep-laravel/
├── app/                         Código de la aplicación
│   ├── Console/Commands/        Comandos Artisan (administrador, importación y transferencia)
│   ├── Http/
│   │   ├── Controllers/         Acciones de API y archivos
│   │   ├── Middleware/          Sesión, permisos y cabeceras de seguridad
│   │   ├── Requests/            Reglas de validación reutilizables
│   │   └── Resources/           Formato de respuestas JSON
│   ├── Models/                  Entidades que representan tablas
│   ├── Providers/               Registro y configuración de servicios Laravel
│   └── Services/                Operaciones de inventario y préstamos
├── bootstrap/                   Inicio de Laravel y registro de proveedores
├── config/                      Configuración de Laravel y de LabKeep
├── database/
│   ├── factories/               Datos de ejemplo usados por pruebas
│   ├── migrations/              Historial versionado de cambios a las tablas
│   ├── seeders/                 Carga opcional de datos iniciales
│   └── database.sqlite          Base local (archivo ignorado por Git)
├── public/
│   ├── assets/                  Interfaz web activa: JavaScript, CSS, fuentes y QR
│   ├── index.php                Entrada pública de Laravel
│   ├── manifest.webmanifest     Datos para instalar la PWA
│   ├── sw.js                    Caché de recursos de la PWA
│   └── icon.svg                 Icono de la aplicación
├── resources/
│   ├── views/                   Plantillas Blade, incluida la página de la app
│   ├── css/                     Entrada de estilos de Vite
│   └── js/                      Entrada JavaScript de Vite
├── routes/
│   ├── web.php                  Página web y endpoints /api/v1
│   └── console.php              Rutas de comandos de consola
├── storage/
│   ├── app/private/attachments/ Adjuntos privados servidos con autorización
│   ├── framework/               Caché, sesiones y vistas compiladas
│   └── logs/                    Registros de errores y actividad técnica
├── tests/
│   ├── Feature/                  Pruebas de flujos HTTP y reglas de negocio
│   └── Unit/                     Pruebas de unidades pequeñas de código
├── .env                         Configuración local y secretos; no se comparte
├── .env.example                 Plantilla de configuración sin secretos
├── artisan                      CLI para ejecutar comandos Laravel
├── composer.json / composer.lock Dependencias PHP y sus versiones fijadas
├── package.json / vite.config.js Herramientas de compilación frontend
└── iniciar-local.ps1            Preparación y arranque en Windows
```

### Recorrido de una operación

Por ejemplo, al consultar el inventario, el navegador ejecuta la interfaz de `public/assets/app.js`, que llama a `/api/v1/items`. La ruta de `routes/web.php` envía la solicitud a `InventoryController`; el middleware revisa sesión e inventario autorizado, y `InventoryItemRequest` valida los datos cuando la operación los modifica. El controlador usa `InventoryService` y el modelo `InventoryItem` para leer o guardar la información. Laravel devuelve JSON —con el formato de `InventoryItemResource` cuando aplica— y JavaScript actualiza la pantalla.

Para préstamos, el flujo equivalente usa `LoanController`, `LoanService` y el modelo `Loan`. Las migraciones describen las tablas y Laravel las ejecuta al preparar la base; no se deben editar las tablas manualmente para cambiar la estructura.

### Qué carpetas editar y cuáles no

- Para modificar reglas del negocio, trabaja en `app/Http`, `app/Services` y `app/Models`; agrega cambios de esquema en una migración nueva dentro de `database/migrations`.
- Para modificar la pantalla que actualmente ve el usuario, edita `public/assets/app.js` y `public/assets/app.css`. Esos son los recursos web activos que referencia la plantilla.
- `resources/js/app.js` y `resources/css/app.css` son entradas base de Vite y actualmente no contienen la interfaz LabKeep. Ejecutar `npm run build` no compila los archivos activos de `public/assets`; no los reemplaza.
- No edites `vendor`: Composer lo genera desde las dependencias. No publiques `.env`, la base SQLite, los archivos de `storage` ni respaldos.
- `.agents/`, `.claude/` y `.codex/` guardan configuración para herramientas de desarrollo asistido; no ejecutan la aplicación en el hosting.

### Perfiles

- `admin`: administra usuarios y puede acceder a los tres inventarios.
- `jefe`: revisa y aprueba cambios y préstamos; su alcance de inventarios depende de los permisos asignados.
- `responsable`: consulta y registra operaciones dentro de los inventarios asignados; ciertas operaciones quedan pendientes de revisión.

## Datos y respaldos

Para ver los comandos disponibles, ejecuta:

```powershell
php artisan list --raw
```

Si todavía tienes los archivos SQLite del proyecto anterior, colócalos juntos con los nombres `inventario.db`, `oficina.db` y/o `sistemas.db`. Primero inspecciónalos sin importar:

```powershell
php artisan labkeep:import-legacy "C:\ruta\a\los\archivos"
```

Para importar, usa una base destino recién creada y vacía; el comando se detiene si el inventario de destino ya tiene información:

```powershell
php artisan labkeep:import-legacy "C:\ruta\a\los\archivos" --apply
```

Para trasladar los datos existentes entre conexiones de Laravel, exporta a una ubicación privada fuera de `public/`, prepara las tablas vacías en el destino y luego importa el archivo:

```powershell
php artisan labkeep:transfer export "C:\respaldo-privado\labkeep.json"
php artisan labkeep:transfer import "C:\respaldo-privado\labkeep.json"
```

El archivo de transferencia incluye datos privados y hashes de contraseñas; no lo subas a Git ni lo guardes dentro de la carpeta pública del servidor. Copia por separado los adjuntos de `storage/app/private/attachments` y conserva respaldos antes de migrar o importar.

## Publicar en un subdominio

Cloudflare puede administrar el DNS y el proxy del subdominio, pero **no ejecuta PHP ni aloja por sí solo esta aplicación o su base de datos**. Hace falta un hosting o servidor que soporte PHP 8.3+, Composer, Laravel y una base MySQL o SQLite persistente.

1. Crea el subdominio en DNS y apunta su registro al servidor contratado. Configura el hosting para que el directorio público del sitio sea únicamente la carpeta `public` del proyecto.
2. Configura en el servidor un archivo `.env` de producción: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://tu-subdominio`, credenciales de la base y `SESSION_SECURE_COOKIE=true`. Mantén `.env`, respaldos y almacenamiento privado fuera del directorio público.
3. Instala dependencias de producción, genera una `APP_KEY` exclusiva para ese servidor, ejecuta `php artisan migrate --force` y crea el administrador con `php artisan labkeep:admin nombre --email=correo@dominio`. El comando solicita una contraseña segura. No reutilices credenciales locales en el sitio publicado.
4. Si trasladarás datos locales, prepara y respalda la base del servidor antes de usar los comandos de transferencia descritos arriba. No importes sobre tablas que ya contengan datos.
5. Activa HTTPS en el hosting y configura Cloudflare para usar HTTPS de extremo a extremo. Verifica que el subdominio, sesiones, subida/descarga de adjuntos y exportación funcionen antes de compartir el sitio.

Los pasos exactos de DNS y certificado dependen del proveedor del dominio y del hosting. No se ha realizado una publicación desde este proyecto; se necesitan el acceso al servidor, el subdominio y las credenciales de la base para completar esa configuración.

## Desarrollo y verificaciones

```powershell
php artisan test --compact
vendor/bin/pint
```

Si editas la interfaz compilada directamente, trabaja sobre `public/assets/app.js` o `public/assets/app.css`. El proyecto también incluye Vite para cambios futuros que requieran un proceso de compilación.
