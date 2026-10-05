<?php
require_once __DIR__ . '/../config/app.php';
requireRole('usuario');
$basePath = '../'; $pageTitle = 'Inicio'; $active = 'dashboard';
require __DIR__ . '/../partials/header.php';
?>
<div class="page-heading"><div><h1>¡Hola, <?=e(usuarioActual()['nombre'] ?? 'Usuario')?>!</h1><p>Este es tu espacio dentro de Atención Clientes.</p></div></div>
<section class="panel"><div class="panel-body"><h3>Panel de usuario</h3><p>Tu acceso está separado del panel administrativo. Aquí colocaremos las funciones correspondientes al usuario.</p></div></section>
<?php require __DIR__ . '/../partials/footer.php'; ?>
