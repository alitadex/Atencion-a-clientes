# Atención Clientes PHP — versión reparada

## Instalación en XAMPP
1. Copia la carpeta `atencion_clientes_php` a `C:\xampp\htdocs\`.
2. Inicia Apache y MySQL.
3. Abre `http://localhost/phpmyadmin`.
4. Ve a **SQL** y ejecuta el archivo `database/instalar.sql` completo.
5. Abre `http://localhost/atencion_clientes_php/crear_admin.php`.
6. Crea el primer administrador y luego entra en `http://localhost/atencion_clientes_php/`.

## Si ya tenías un administrador y no puedes entrar
Abre `http://localhost/atencion_clientes_php/crear_admin.php` y usa **Restablecer contraseña**. Escribe el username o correo del administrador y define una contraseña nueva de mínimo 8 caracteres.

## Login
El login acepta tanto el **username** como el **correo**. Las contraseñas nuevas usan `password_hash()`/`password_verify()`. Si una cuenta antigua tenía la contraseña guardada en texto plano, al iniciar sesión correctamente se convierte automáticamente a un hash seguro.

## Conexión MySQL
Por defecto usa:
- host: `127.0.0.1`
- puerto: `3306`
- base: `atencion_clientes`
- usuario: `root`
- contraseña: vacía

Si tu MySQL tiene otra contraseña, edita `config/database.php` o usa las variables `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_PORT`.

## Seguridad incorporada

- Consultas SQL con PDO y prepared statements.
- Protección CSRF en formularios POST.
- Sesiones endurecidas (`HttpOnly`, `SameSite=Lax`, modo estricto y regeneración al iniciar sesión).
- Limitación de intentos de inicio de sesión (5 intentos por 10 minutos por usuario/IP).
- Cabeceras HTTP de seguridad y política CSP.
- Escape HTML centralizado mediante `e()` para reducir XSS.
- Rutas administrativas protegidas del lado del servidor; un usuario no administrador que intente entrar manualmente a una ruta administrativa pierde la sesión y vuelve al login.
- Carpetas `config`, `database` y `storage` bloqueadas desde Apache.
- Archivos `.sql`, `.env`, logs y documentación interna no son descargables por HTTP.

### Sobre “ocultar consultas en el navegador”

Las consultas SQL ya se ejecutan exclusivamente en PHP/servidor y no se envían al navegador. Un usuario puede ver las peticiones HTTP y sus parámetros mediante DevTools, pero no puede ver el SQL interno. Esta separación es la forma correcta de proteger las consultas; no se intenta ocultar la red con técnicas del frontend.
