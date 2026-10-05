<?php
declare(strict_types=1);
require_once __DIR__ . '/config/app.php';

$mensaje = '';
$error = '';

try {
    $adminCount = (int) scalar($pdo, "SELECT COUNT(*) FROM usuarios_sistema WHERE rol = 'administrador'");
} catch (Throwable $e) {
    $adminCount = -1;
    $error = 'La tabla usuarios_sistema no existe. Ejecuta primero database/instalar.sql en phpMyAdmin.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $adminCount >= 0) {
    $accion = $_POST['accion'] ?? 'crear';
    try {
        if ($accion === 'crear') {
            $nombre = trim($_POST['nombre'] ?? '');
            $correo = trim($_POST['correo'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $password = (string) ($_POST['password'] ?? '');
            $telefono = trim($_POST['telefono'] ?? '');

            if ($adminCount > 0) throw new RuntimeException('Ya existe un administrador. Usa la opción de restablecer contraseña.');
            if ($nombre === '' || $correo === '' || $username === '' || $password === '') throw new RuntimeException('Completa todos los campos obligatorios.');
            if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('El correo no tiene un formato válido.');
            if (strlen($password) < 8) throw new RuntimeException('La contraseña debe tener al menos 8 caracteres.');

            $stmt = $pdo->prepare('INSERT INTO usuarios_sistema (nombre, correo, username, password, telefono, rol, activo) VALUES (?, ?, ?, ?, ?, ?, 1)');
            $stmt->execute([$nombre, $correo, $username, password_hash($password, PASSWORD_DEFAULT), $telefono ?: null, 'administrador']);
            $mensaje = 'Administrador creado correctamente. Ya puedes iniciar sesión.';
            $adminCount++;
        } elseif ($accion === 'reset') {
            // Nunca permitimos un restablecimiento público: sería un vector de toma
            // de control de la cuenta administrativa. Un administrador autenticado
            // puede cambiar contraseñas desde Usuarios del sistema.
            requireAdmin();
            throw new RuntimeException('Por seguridad, el restablecimiento se realiza desde Usuarios del sistema.');
        }
    } catch (PDOException $e) {
        $error = $e->getCode() === '23000' ? 'El correo o username ya está registrado.' : 'No fue posible completar la operación. Revisa la base de datos.';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Acceso inicial · Atención Clientes</title><link rel="stylesheet" href="assets/app.css">
<style>body{font-family:Arial,sans-serif;background:#f4f7fb;margin:0}.box{max-width:600px;margin:40px auto;background:#fff;padding:30px;border-radius:18px;box-shadow:0 10px 35px #0001}.field{margin:14px 0}.field label{display:block;font-weight:bold;margin-bottom:6px}.field input{width:100%;box-sizing:border-box;padding:11px;border:1px solid #ccd5e0;border-radius:8px}.btnx{border:0;background:#087df5;color:#fff;padding:12px 18px;border-radius:8px;font-weight:bold;cursor:pointer}.msg{padding:12px;border-radius:8px;background:#eaf8ef;color:#18723a}.err{padding:12px;border-radius:8px;background:#fff0f0;color:#b52c2c}.card{border:1px solid #e4e9f0;padding:20px;border-radius:12px;margin-top:18px}.muted{color:#687585;font-size:14px}</style></head>
<body><div class="box"><h1>Acceso de Atención Clientes</h1><p class="muted">Crea el primer administrador. Una vez creado, las contraseñas se administran desde Usuarios del sistema.</p>
<?php if($mensaje): ?><p class="msg"><?=e($mensaje)?></p><p><a href="index.php">Ir al login</a></p><?php endif; ?>
<?php if($error): ?><p class="err"><?=e($error)?></p><?php endif; ?>
<?php if($adminCount === 0): ?>
<div class="card"><h2>Crear administrador</h2><form method="post"><input type="hidden" name="_csrf" value="<?= e(csrfToken()) ?>"><input type="hidden" name="accion" value="crear">
<div class="field"><label>Nombre *</label><input name="nombre" required></div><div class="field"><label>Correo *</label><input type="email" name="correo" required></div><div class="field"><label>Usuario *</label><input name="username" maxlength="50" required></div><div class="field"><label>Contraseña *</label><input type="password" name="password" minlength="8" required></div><div class="field"><label>Teléfono</label><input name="telefono"></div><button class="btnx">Crear administrador</button></form></div>
<?php elseif($adminCount > 0): ?>
<div class="card"><h2>Administrador ya configurado</h2><p class="muted">Por seguridad, esta página no permite restablecer contraseñas públicamente. Inicia sesión con una cuenta administradora y utiliza <strong>Usuarios del sistema</strong> para cambiar una contraseña.</p></div>
<?php endif; ?>
<div style="margin-top:20px"><a href="index.php">Volver al inicio de sesión</a></div></div></body></html>
