<?php
/**
 * presupuestos.php — PRESUPUESTOS
 * ------------------------------------------------------------
 * PARA QUÉ SIRVE: muestra
 *   1. Tres tarjetas: total presupuestado, total pagado y saldo pendiente
 *   2. Tabla con los últimos 100 presupuestos (importe, pagado y saldo)
 * ------------------------------------------------------------
 */
require_once 'config/app.php';
requireRole('administrador', 'empleado');

$active    = 'presupuestos';
$pageTitle = 'Presupuestos';

// ---------- 1) TOTALES GENERALES (suma de todos los presupuestos) ----------
$tot = $pdo->query("
    SELECT COALESCE(SUM(importe), 0)        AS total,
           COALESCE(SUM(importe_pagado), 0) AS pagado
    FROM presupuestos
")->fetch();

// ---------- 2) ÚLTIMOS 100 PRESUPUESTOS ----------
$rows = $pdo->query("
    SELECT p.*,
           COALESCE(u.usuario, 'Sin usuario') AS usuario,
           ce.nombre AS estatus
    FROM presupuestos p
    LEFT JOIN usuarios u     ON u.cuenta = p.cuenta
    LEFT JOIN cat_estatus ce ON ce.id_estatus = p.estatus_id
    ORDER BY p.id_presupuesto DESC
    LIMIT 100
")->fetchAll();

require 'partials/header.php';
?>

<div class="page-heading">
    <div>
        <h1>Presupuestos</h1>
        <p>Control de importes asociados a trabajos y reportes.</p>
    </div>
</div>

<!-- ===== TARJETAS DE TOTALES ===== -->
<div class="cards">
    <div class="card stat"><div><strong><?= money($tot['total']) ?></strong><span>Total presupuestado</span></div></div>
    <div class="card stat"><div><strong><?= money($tot['pagado']) ?></strong><span>Total pagado</span></div></div>
    <!-- Pendiente = presupuestado − pagado -->
    <div class="card stat"><div><strong><?= money((float) $tot['total'] - (float) $tot['pagado']) ?></strong><span>Pendiente</span></div></div>
</div>

<!-- ===== TABLA ===== -->
<section class="panel" style="margin-top:18px">
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Folio</th><th>Usuario</th><th>Concepto</th><th>Importe</th>
                    <th>Pagado</th><th>Saldo</th><th>Estatus</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <!-- Si no tiene folio, muestra el id interno -->
                    <td><?= e($r['folio_presupuesto'] ?: $r['id_presupuesto']) ?></td>
                    <td><?= e($r['usuario']) ?></td>
                    <!-- Concepto recortado a 55 caracteres -->
                    <td><?= e(mb_strimwidth((string) $r['concepto'], 0, 55, '…')) ?></td>
                    <td><?= money($r['importe']) ?></td>
                    <td><?= money($r['importe_pagado']) ?></td>
                    <!-- Saldo = importe − pagado -->
                    <td><?= money((float) $r['importe'] - (float) $r['importe_pagado']) ?></td>
                    <td><span class="badge blue"><?= e($r['estatus'] ?: 'Sin estatus') ?></span></td>
                </tr>
            <?php endforeach; if (!$rows): ?>
                <tr><td colspan="7" class="empty">No hay presupuestos registrados.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require 'partials/footer.php'; ?>
