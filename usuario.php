<?php
/**
 * usuario.php — EXPEDIENTE DE UN USUARIO
 * ------------------------------------------------------------
 * PARA QUÉ SIRVE: muestra todo lo de una cuenta (?cuenta=123):
 *   - Datos personales (teléfono, colonia, tarifa, dirección)
 *   - Resumen: cuántos reportes y convenios tiene
 *   - Historial de sus últimos 20 reportes
 *   - Sus últimos 10 convenios
 *   - Botón para crear un nuevo reporte ya con la cuenta cargada
 * ------------------------------------------------------------
 */
require_once 'config/app.php';
requireRole('administrador', 'empleado');

$active = 'usuarios';

// ---------- 1) DATOS DEL USUARIO ----------
$cuenta = (int) ($_GET['cuenta'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM usuarios WHERE cuenta = ?');
$stmt->execute([$cuenta]);
$u = $stmt->fetch();

// Si la cuenta no existe, se detiene con error 404
if (!$u) {
    http_response_code(404);
    die('Usuario no encontrado.');
}
$pageTitle = $u['usuario'];

// ---------- 2) REPORTES DEL USUARIO (últimos 20) ----------
$rep = $pdo->prepare("
    SELECT r.*, cr.nombre AS tipo, ce.nombre AS estatus
    FROM reportes r
    LEFT JOIN cat_reportes cr ON cr.id_reporte = r.tipo_reporte_id
    LEFT JOIN cat_estatus ce  ON ce.id_estatus = r.estatus_final_id
    WHERE r.cuenta = ?
    ORDER BY r.fecha_reporte DESC
    LIMIT 20
");
$rep->execute([$cuenta]);
$reportes = $rep->fetchAll();

// ---------- 3) CONVENIOS DEL USUARIO (últimos 10) ----------
$conv = $pdo->prepare('SELECT * FROM convenios WHERE cuenta = ? ORDER BY fecha_convenio DESC LIMIT 10');
$conv->execute([$cuenta]);
$convenios = $conv->fetchAll();

require 'partials/header.php';
?>

<div class="page-heading">
    <div>
        <h1><?= e($u['usuario']) ?></h1>
        <p>Cuenta <?= e((string) $u['cuenta']) ?> · Expediente del usuario</p>
    </div>
    <?php if (tieneRol('administrador')): ?><a class="btn primary" href="nuevo_reporte.php?cuenta=<?= e((string) $u['cuenta']) ?>">＋ Nuevo reporte</a><?php endif; ?>
</div>

<div class="detail-grid">

    <!-- ===== DATOS DEL USUARIO ===== -->
    <section class="panel">
        <div class="panel-head">
            <h3>Datos del usuario</h3>
            <span class="badge green">Activo</span>
        </div>
        <div class="panel-body">
            <div class="form-grid">
                <div><small>Cuenta</small><strong><?= e((string) $u['cuenta']) ?></strong></div>
                <div><small>Teléfono</small><strong><?= e($u['telefono'] ?: '—') ?></strong></div>
                <div><small>Colonia</small><strong><?= e($u['colonia'] ?: '—') ?></strong></div>
                <div><small>Tarifa</small><strong><?= e($u['tarifa'] ?: '—') ?></strong></div>
                <div class="form-group full">
                    <label>Dirección</label>
                    <div><?= e($u['direccion'] ?: '—') ?></div>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== RESUMEN (contadores) ===== -->
    <section class="panel">
        <div class="panel-head"><h3>Resumen</h3></div>
        <div class="panel-body">
            <div class="cards" style="grid-template-columns:1fr 1fr">
                <div class="card stat"><div><strong><?= count($reportes) ?></strong><span>Reportes</span></div></div>
                <div class="card stat"><div><strong><?= count($convenios) ?></strong><span>Convenios</span></div></div>
            </div>
        </div>
    </section>
</div>

<!-- ===== HISTORIAL DE REPORTES ===== -->
<section class="panel" style="margin-top:18px">
    <div class="panel-head"><h3>Historial de reportes</h3></div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr><th>Folio</th><th>Tipo</th><th>Fecha</th><th>Prioridad</th><th>Estado</th><th>Descripción</th></tr>
            </thead>
            <tbody>
            <?php foreach ($reportes as $r): ?>
                <tr>
                    <td>R-<?= str_pad((string) $r['folio_reporte'], 6, '0', STR_PAD_LEFT) ?></td>
                    <td><?= e($r['tipo']) ?></td>
                    <td><?= e(date('d/m/Y H:i', strtotime($r['fecha_reporte']))) ?></td>
                    <!-- Color según prioridad: ALTA=rojo, BAJA=verde, otra=amarillo -->
                    <td><span class="badge <?= $r['prioridad'] === 'ALTA' ? 'red' : ($r['prioridad'] === 'BAJA' ? 'green' : 'yellow') ?>"><?= e($r['prioridad']) ?></span></td>
                    <td><span class="badge blue"><?= e($r['estatus'] ?: 'Sin estatus') ?></span></td>
                    <!-- Descripción recortada a 65 caracteres -->
                    <td><?= e(mb_strimwidth((string) $r['especificaciones'], 0, 65, '…')) ?></td>
                </tr>
            <?php endforeach; if (!$reportes): ?>
                <tr><td colspan="6" class="empty">No hay reportes para este usuario.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<!-- ===== CONVENIOS ===== -->
<section class="panel" style="margin-top:18px">
    <div class="panel-head"><h3>Convenios</h3></div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr><th>Folio</th><th>Fecha</th><th>Adeudo</th><th>Primer pago</th><th>Parcialidades</th><th>Pagado</th></tr>
            </thead>
            <tbody>
            <?php foreach ($convenios as $c): ?>
                <tr>
                    <td><?= e((string) $c['folio_convenio']) ?></td>
                    <td><?= e($c['fecha_convenio']) ?></td>
                    <td><?= money($c['adeudo']) ?></td>
                    <td><?= money($c['primer_pago']) ?></td>
                    <td><?= money($c['parcialidades']) ?></td>
                    <td><span class="badge green"><?= e($c['pagado'] ?: 'Pendiente') ?></span></td>
                </tr>
            <?php endforeach; if (!$convenios): ?>
                <tr><td colspan="6" class="empty">No hay convenios para este usuario.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require 'partials/footer.php'; ?>
