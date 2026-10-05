<?php
$pageTitle = $pageTitle ?? 'Atención Clientes';
$basePath = $basePath ?? '';
$actual = usuarioActual();
$nombreSesion = $actual['nombre'] ?? 'Usuario';
$rolSesion = $actual['rol'] ?? '';
$inicialSesion = strtoupper(substr($nombreSesion, 0, 1));
$dashboardUrl = $rolSesion === 'empleado' ? 'empleado/index.php' : ($rolSesion === 'administrador' ? 'administrador/index.php' : 'usuario/index.php');
$menu = [
    'dashboard'    => [$dashboardUrl, '⌂', 'Inicio'],
    'usuarios'     => ['usuarios.php', '♙', 'Usuarios'],
    'reportes'     => ['reportes.php', '▤', 'Reportes / Quejas'],
    'ordenes'      => ['ordenes.php', '⚒', 'Órdenes de trabajo'],
    'seguimiento'  => ['seguimiento.php', '◔', 'Seguimiento'],
    'presupuestos' => ['presupuestos.php', '$', 'Presupuestos'],
    'convenios'    => ['convenios.php', '♧', 'Convenios'],
    'sistema'      => ['administrador/usuarios_sistema.php', '⚙', 'Usuarios del sistema'],
];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> · Atención Clientes</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $basePath ?>assets/app.css">
</head>
<body>
<?php
$mostrarLoaderPerfil = !empty($_SESSION['mostrar_loader_perfil']);
if ($mostrarLoaderPerfil) {
    unset($_SESSION['mostrar_loader_perfil']);
}
?>
<?php if ($mostrarLoaderPerfil): ?>
<div class="profile-loader" id="profile-loader" role="status" aria-live="polite" aria-label="Cargando tu perfil">
    <div class="loader-card">
        <div class="loader-logo" aria-hidden="true">
            <img class="loader-logo-gray" src="<?= $basePath ?>assets/capashh-logo.png" alt="">
            <img class="loader-color-fill" src="<?= $basePath ?>assets/capashh-logo.png" alt="">
            <div class="loader-shine"></div>
        </div>
        <h2 class="loader-title">Cargando tu perfil</h2>
        <p class="loader-subtitle">Preparando tu espacio de Atención a Clientes...</p>
        <div class="loader-progress" aria-hidden="true"><div class="loader-progress-bar"></div></div>
        <div class="loader-meta"><span>CAPASHH</span><span class="loader-percent">0%</span></div>
    </div>
    <div class="loader-watermark">COMISIÓN DE AGUA POTABLE, ALCANTARILLADO Y SANEAMIENTO</div>
</div>
<?php endif; ?>
<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <div class="brand"><div class="brand-icon"><?= e($inicialSesion) ?></div><div><strong>Atención Clientes</strong><span><?= e($nombreSesion) ?> </span></div></div>
        <div class="nav-section">Menú principal</div>
        <nav>
            <?php foreach ($menu as $clave => [$url, $icono, $texto]): ?>
                <a href="<?= ($clave === 'sistema' ? ($basePath . 'administrador/usuarios_sistema.php') : ($basePath . $url)) ?>" class="nav-item <?= ($active ?? '') === $clave ? 'active' : '' ?>"><span><?= $icono ?></span><?= $texto ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="nav-section" style="margin-top:12px">Cuenta</div>
        <a class="nav-item" href="<?= $basePath ?>auth/logout.php"><span>↪</span>Cerrar sesión</a>
        <div class="sidebar-footer"><div class="avatar"><?= e($inicialSesion) ?></div><div><strong><?= e($nombreSesion) ?></strong><small><?= e(ucfirst($rolSesion)) ?></small></div></div>
    </aside>
    <main class="main-content">
        <header class="topbar">
            <button class="mobile-menu" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button>
            <form class="global-search" action="<?= $basePath ?>buscar.php" method="get"><span>⌕</span><input name="q" placeholder="Buscar cuenta, usuario, reporte o convenio..." value="<?= e($_GET['q'] ?? '') ?>"></form>
            <div class="top-actions"><span class="notification">♢<b></b></span><div class="user-pill"><div class="avatar small"><?= e($inicialSesion) ?></div><?= e($nombreSesion) ?> · <?= e(ucfirst($rolSesion)) ?></div></div>
        </header>
        <div class="page-wrap">
