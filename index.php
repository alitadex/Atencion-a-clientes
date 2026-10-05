<?php
/**
 * index.php — LOGIN DEL SISTEMA
 * ------------------------------------------------------------
 * Entrada principal. Comprueba username y contraseña y envía
 * al usuario al panel correspondiente según su rol.
 */
require_once __DIR__ . '/config/app.php';

if (estaLogueado()) {
    redirigirSegunRol();
}

$error = '';
if (isset($_GET['acceso']) && $_GET['acceso'] === 'denegado') {
    $error = 'Tu cuenta no tiene permisos de administrador. Inicia sesión con una cuenta autorizada.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Escribe tu usuario y contraseña.';
    } elseif (loginRateLimited($username)) {
        $error = 'Demasiados intentos. Espera unos minutos e inténtalo nuevamente.';
    } else {
        // Permite iniciar sesión por username o correo. Las contraseñas nuevas
        // se guardan con password_hash(). Si una cuenta antigua tiene la contraseña
        // en texto plano, se migra automáticamente al hash seguro después de validar.
        $stmt = $pdo->prepare('SELECT * FROM usuarios_sistema WHERE (username = :username OR correo = :correo) AND activo = 1 LIMIT 1');
        $stmt->execute(['username' => $username, 'correo' => $username]);
        $usuario = $stmt->fetch();

        $passwordValida = false;
        if ($usuario) {
            $hash = (string) ($usuario['password'] ?? '');
            if ($hash !== '' && password_verify($password, $hash)) {
                $passwordValida = true;
                if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
                    $nuevoHash = password_hash($password, PASSWORD_DEFAULT);
                    $up = $pdo->prepare('UPDATE usuarios_sistema SET password = ? WHERE id_usuario = ?');
                    $up->execute([$nuevoHash, $usuario['id_usuario']]);
                }
            } elseif ($hash !== '' && hash_equals($hash, $password)) {
                // Compatibilidad con cuentas creadas manualmente antes de esta versión.
                $passwordValida = true;
                $nuevoHash = password_hash($password, PASSWORD_DEFAULT);
                $up = $pdo->prepare('UPDATE usuarios_sistema SET password = ? WHERE id_usuario = ?');
                $up->execute([$nuevoHash, $usuario['id_usuario']]);
            }
        }

        if ($usuario && $passwordValida) {
            clearLoginFailures($username);
            session_regenerate_id(true);
            $_SESSION['usuario_sistema'] = [
                'id_usuario' => (int) $usuario['id_usuario'],
                'nombre'     => $usuario['nombre'],
                'correo'     => $usuario['correo'],
                'username'   => $usuario['username'],
                'telefono'   => $usuario['telefono'],
                'rol'        => $usuario['rol'],
            ];
            // El loader CAPASHH se muestra una sola vez al entrar al perfil.
            $_SESSION['mostrar_loader_perfil'] = true;
            redirigirSegunRol();
        }

        recordLoginFailure($username);
        usleep(250000);
        $error = 'El usuario o la contraseña son incorrectos.';
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión · Atención a Clientes</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/app.css">
</head>
<body class="login-page login-modern">
    <div class="login-shell">
        <section class="login-promo">
            <div class="promo-orb promo-orb-1"></div>
            <div class="promo-orb promo-orb-2"></div>
            <div class="promo-dots"></div>
            <div class="promo-content">
                <div class="login-logo" aria-hidden="true">
                    <svg viewBox="0 0 64 64" role="img">
                        <circle cx="32" cy="32" r="30" fill="rgba(255,255,255,.14)"/>
                        <path d="M19 35v-5c0-8 6-14 13-14s13 6 13 14v5" fill="none" stroke="#fff" stroke-width="4" stroke-linecap="round"/>
                        <rect x="14" y="30" width="8" height="13" rx="4" fill="#fff"/>
                        <rect x="42" y="30" width="8" height="13" rx="4" fill="#fff"/>
                        <path d="M43 43c-1 6-6 9-13 9" fill="none" stroke="#fff" stroke-width="4" stroke-linecap="round"/>
                        <path d="M25 23h14a7 7 0 0 1 7 7v3a7 7 0 0 1-7 7H31l-6 5v-5h-1a7 7 0 0 1-6-7v-3a7 7 0 0 1 7-7Z" fill="#fff" opacity=".98"/>
                        <circle cx="29" cy="31.5" r="1.8" fill="#1769e8"/><circle cx="34" cy="31.5" r="1.8" fill="#1769e8"/><circle cx="39" cy="31.5" r="1.8" fill="#1769e8"/>
                    </svg>
                </div>
                <div class="promo-kicker">SISTEMA DE GESTIÓN</div>
                <h1>ATENCIÓN A<br>CLIENTES</h1>
                <div class="promo-line"></div>
                <h2>¡Bienvenido!</h2>
                <p>Ingresa a tu cuenta para gestionar reportes, dar seguimiento a solicitudes y brindar una mejor atención a nuestros clientes.</p>
                <ul class="promo-benefits">
                    <li><span>▥</span> Gestión de reportes</li>
                    <li><span>♧</span> Seguimiento de solicitudes</li>
                    <li><span>◈</span> Trabajo más eficiente</li>
                </ul>
            </div>
            <div class="promo-wave promo-wave-a"></div>
            <div class="promo-wave promo-wave-b"></div>
        </section>

        <section class="login-form-panel">
            <div class="login-form-inner">
                <div class="login-form-heading">
                    <span>ACCESO AL SISTEMA</span>
                    <h2>INICIAR SESIÓN</h2>
                    <p>Ingresa tus credenciales para acceder al sistema de atención a clientes.</p>
                </div>

                <?php if ($error): ?>
                    <div class="login-error" role="alert"><span>!</span><?= e($error) ?></div>
                <?php endif; ?>

                <form method="post" autocomplete="on" class="modern-login-form"><input type="hidden" name="_csrf" value="<?= e(csrfToken()) ?>">
                    <div class="modern-login-group">
                        <label for="username">Usuario o correo electrónico</label>
                        <div class="modern-input-wrap">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 21a8 8 0 0 0-16 0M12 13a5 5 0 1 0 0-10 5 5 0 0 0 0 10Z"/></svg>
                            <input id="username" name="username" type="text" maxlength="150" autocomplete="username" placeholder="Ingresa tu usuario o correo" required autofocus>
                        </div>
                    </div>

                    <div class="modern-login-group">
                        <label for="password">Contraseña</label>
                        <div class="modern-input-wrap">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                            <input id="password" name="password" type="password" autocomplete="current-password" placeholder="Ingresa tu contraseña" required>
                            <button type="button" class="password-toggle" onclick="togglePassword()" aria-label="Mostrar contraseña" title="Mostrar contraseña">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                            </button>
                        </div>
                    </div>

                    <div class="login-options">
                        <label class="remember-check"><input type="checkbox" name="remember_ui" value="1"><span>Recordarme</span></label>
                        <span class="login-recovery">¿Olvidaste tu contraseña?</span>
                    </div>

                    <button class="modern-login-btn" type="submit">Iniciar sesión <span>→</span></button>
                </form>

                <div class="login-divider"><span></span><b>O</b><span></span></div>
                <p class="login-contact">¿No tienes una cuenta? <strong>Contacta al administrador</strong></p>
                <div class="login-footer">Sistema de Atención a Clientes · Acceso seguro</div>
            </div>
        </section>
    </div>
    <script>
        function togglePassword(){
            const input=document.getElementById('password');
            const btn=document.querySelector('.password-toggle');
            const visible=input.type==='text';
            input.type=visible?'password':'text';
            btn.setAttribute('aria-label', visible ? 'Mostrar contraseña' : 'Ocultar contraseña');
        }
    </script>
</body>
</html>
