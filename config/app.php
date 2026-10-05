<?php
/**
 * Configuración central y capa de seguridad de la aplicación.
 */
declare(strict_types=1);

// Sesiones endurecidas: evita fijación de sesión y limita el acceso a la cookie.
if (session_status() !== PHP_SESSION_ACTIVE) {
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/database.php';

// Cabeceras de seguridad. El SQL y el código PHP nunca se envían al navegador.
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'; img-src 'self' data: blob:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; script-src 'self' 'unsafe-inline'; connect-src 'self'; object-src 'none';");
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function money($value): string
{
    return '$' . number_format((float) $value, 2, '.', ',');
}

function scalar(PDO $pdo, string $sql, array $params = []): mixed
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

if (!function_exists('fechaValida')) {
    function fechaValida($f) {
        $d = DateTime::createFromFormat('Y-m-d', $f);
        return ($d && $d->format('Y-m-d') === $f) ? $f : '';
    }
}

/** CSRF */
function csrfToken(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function validarCsrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    $token = (string) ($_POST['_csrf'] ?? '');
    $sessionToken = (string) ($_SESSION['_csrf'] ?? '');
    if ($token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
        http_response_code(419);
        exit('Solicitud no válida. Recarga la página e inténtalo nuevamente.');
    }
}

// Todos los POST de la aplicación pasan por esta comprobación antes de ejecutar SQL.
validarCsrf();

/** Protección básica contra intentos repetidos de acceso. */
function loginRateKey(string $username): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return hash('sha256', strtolower(trim($username)) . '|' . $ip);
}

function loginRateFile(string $username): string
{
    $dir = __DIR__ . '/../storage/security';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    return $dir . '/login_' . loginRateKey($username) . '.json';
}

function loginRateLimited(string $username): bool
{
    $file = loginRateFile($username);
    if (!is_file($file)) {
        return false;
    }
    $data = json_decode((string) @file_get_contents($file), true) ?: [];
    $now = time();
    $attempts = array_values(array_filter((array) ($data['attempts'] ?? []), static fn($t) => is_int($t) && $t > $now - 600));
    if ($attempts !== ($data['attempts'] ?? [])) {
        @file_put_contents($file, json_encode(['attempts' => $attempts], JSON_THROW_ON_ERROR), LOCK_EX);
    }
    return count($attempts) >= 5;
}

function recordLoginFailure(string $username): void
{
    $file = loginRateFile($username);
    $data = json_decode((string) (@file_get_contents($file) ?: '{}'), true) ?: [];
    $now = time();
    $attempts = array_values(array_filter((array) ($data['attempts'] ?? []), static fn($t) => is_int($t) && $t > $now - 600));
    $attempts[] = $now;
    @file_put_contents($file, json_encode(['attempts' => $attempts], JSON_THROW_ON_ERROR), LOCK_EX);
}

function clearLoginFailures(string $username): void
{
    $file = loginRateFile($username);
    if (is_file($file)) {
        @unlink($file);
    }
}

function estaLogueado(): bool
{
    return isset($_SESSION['usuario_sistema']['id_usuario']);
}

function usuarioActual(): array
{
    return $_SESSION['usuario_sistema'] ?? [];
}

function tieneRol(string ...$roles): bool
{
    return estaLogueado() && in_array($_SESSION['usuario_sistema']['rol'] ?? '', $roles, true);
}

function redirigirSegunRol(): never
{
    $rol = $_SESSION['usuario_sistema']['rol'] ?? '';
    $destino = match ($rol) {
        'administrador' => 'administrador/index.php',
        'usuario'       => 'usuario/index.php',
        'empleado'      => 'empleado/index.php',
        default         => 'index.php',
    };

    header('Location: ' . $destino, true, 303);
    exit;
}

function requireLogin(): void
{
    if (!estaLogueado()) {
        header('Location: index.php', true, 303);
        exit;
    }
}

function requireRole(string ...$roles): void
{
    requireLogin();
    if (!tieneRol(...$roles)) {
        http_response_code(403);
        exit('No tienes permiso para acceder a esta sección.');
    }
}

/**
 * Acceso exclusivo de administrador.
 * Si un empleado/usuario intenta entrar manualmente a una ruta administrativa,
 * se destruye su sesión y se le devuelve al login, tal como solicita el sistema.
 */
function requireAdmin(): void
{
    if (!estaLogueado()) {
        header('Location: index.php', true, 303);
        exit;
    }
    if (!tieneRol('administrador')) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'] ?? '/',
                'domain' => $params['domain'] ?? '',
                'secure' => (bool) ($params['secure'] ?? false),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        session_destroy();
        header('Location: index.php?acceso=denegado', true, 303);
        exit;
    }
}

function esEmpleado(): bool
{
    return tieneRol('empleado');
}

function puedeModificar(): bool
{
    return tieneRol('administrador');
}

function requireReadAccess(): void
{
    requireRole('administrador', 'empleado');
}
