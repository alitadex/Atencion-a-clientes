<?php
require_once __DIR__ . '/../config/app.php';
requireRole('empleado');

$basePath = '../';
$pageTitle = 'Inicio';
$active = 'dashboard';

$usuarios  = (int) scalar($pdo, 'SELECT COUNT(*) FROM usuarios');
$reportes  = (int) scalar($pdo, 'SELECT COUNT(*) FROM reportes');
$ordenes   = (int) scalar($pdo, 'SELECT COUNT(*) FROM ordenes_trabajo');
$convenios = (int) scalar($pdo, 'SELECT COUNT(*) FROM convenios');

$ultimos = $pdo->query("
    SELECT r.folio_reporte, r.cuenta, r.fecha_reporte,
           COALESCE(u.usuario, r.usuario) AS usuario,
           cr.nombre AS tipo, ce.nombre AS estatus,
           COALESCE(r.prioridad, cr.prioridad) AS prioridad
    FROM reportes r
    LEFT JOIN usuarios u ON u.cuenta = r.cuenta
    LEFT JOIN cat_reportes cr ON cr.id_reporte = r.tipo_reporte_id
    LEFT JOIN cat_estatus ce ON ce.id_estatus = r.estatus_final_id
    ORDER BY r.fecha_reporte DESC LIMIT 8
")->fetchAll();

require __DIR__ . '/../partials/header.php';
?>
<div class="dashboard-hero"><div><h1>¡Hola, <?=e(usuarioActual()['nombre'] ?? 'Empleado')?>!</h1><p>Consulta la operación del sistema. Tu cuenta tiene permisos de solo lectura.</p></div><div class="hero-badge">Modo consulta</div></div><div class="page-heading"><div><h1>Resumen</h1><p>Indicadores y actividad reciente.</p></div></div>

<div class="cards">
    <div class="card stat"><div class="stat-icon">♙</div><div><strong><?=number_format($usuarios)?></strong><span>Usuarios registrados</span></div></div>
    <div class="card stat"><div class="stat-icon">▤</div><div><strong><?=number_format($reportes)?></strong><span>Reportes / quejas</span></div></div>
    <div class="card stat"><div class="stat-icon">⚒</div><div><strong><?=number_format($ordenes)?></strong><span>Órdenes de trabajo</span></div></div>
    <div class="card stat"><div class="stat-icon">♧</div><div><strong><?=number_format($convenios)?></strong><span>Convenios</span></div></div>
</div>

<section class="panel" style="margin-top:18px">
    <div class="panel-head"><h3>Últimos reportes</h3><a href="../reportes.php" class="btn">Ver todos →</a></div>
    <div class="table-wrap"><table class="data-table"><thead><tr><th>Folio</th><th>Cuenta</th><th>Usuario</th><th>Tipo</th><th>Fecha</th><th>Prioridad</th><th>Estado</th></tr></thead><tbody>
    <?php if (!$ultimos): ?>
        <tr><td colspan="7" class="empty">Todavía no hay reportes registrados.</td></tr>
    <?php else: foreach ($ultimos as $r): ?>
        <tr>
            <td><strong>R-<?=str_pad((string)$r['folio_reporte'], 6, '0', STR_PAD_LEFT)?></strong></td>
            <td><?=e((string)$r['cuenta'])?></td>
            <td><?=e($r['usuario'] ?: 'Sin usuario')?></td>
            <td><?=e($r['tipo'] ?: 'Sin clasificar')?></td>
            <td><?=e(date('d/m/Y H:i', strtotime($r['fecha_reporte'])))?></td>
            <td><span class="badge <?=strtoupper($r['prioridad'] ?? '') === 'ALTA' ? 'red' : (strtoupper($r['prioridad'] ?? '') === 'BAJA' ? 'green' : 'yellow')?>"><?=e($r['prioridad'] ?: 'MEDIA')?></span></td>
            <td><span class="badge blue"><?=e($r['estatus'] ?: 'Sin estatus')?></span></td>
        </tr>
    <?php endforeach; endif; ?>
    </tbody></table></div>
</section>

<?php require __DIR__ . '/../partials/footer.php'; ?>
