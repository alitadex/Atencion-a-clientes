<?php
/**
 * seguimiento.php — TABLERO KANBAN DE SEGUIMIENTO
 * ------------------------------------------------------------
 * PARA QUÉ SIRVE: muestra los últimos 100 reportes agrupados en
 * 4 columnas según su estatus:
 *   Pendientes · Asignados · En proceso · Terminados
 * Cada tarjeta abre el detalle del reporte. Máximo 12 por columna.
 * ------------------------------------------------------------
 */
require_once 'config/app.php';
requireRole('administrador', 'empleado');

$active    = 'seguimiento';
$pageTitle = 'Seguimiento';

// ---------- 1) ÚLTIMOS 100 REPORTES ----------
$rows = $pdo->query("
    SELECT r.id_reporte, r.folio_reporte, r.fecha_reporte,
           COALESCE(u.usuario, r.usuario)      AS usuario,
           cr.nombre                           AS tipo,
           ce.nombre                           AS estatus,
           COALESCE(r.prioridad, cr.prioridad) AS prioridad
    FROM reportes r
    LEFT JOIN usuarios u      ON u.cuenta = r.cuenta
    LEFT JOIN cat_reportes cr ON cr.id_reporte = r.tipo_reporte_id
    LEFT JOIN cat_estatus ce  ON ce.id_estatus = r.estatus_final_id
    ORDER BY r.fecha_reporte DESC
    LIMIT 100
")->fetchAll();

// ---------- 2) COLUMNAS DEL TABLERO ----------
$groups = [
    'Pendientes' => [],
    'Asignados'  => [],
    'En proceso' => [],
    'Terminados' => [],
];

// ---------- 3) CLASIFICAR CADA REPORTE SEGÚN EL TEXTO DE SU ESTATUS ----------
foreach ($rows as $r) {
    $s = strtolower((string) $r['estatus']);

    if (str_contains($s, 'termin') || str_contains($s, 'solucion') || str_contains($s, 'cerrad')) {
        $groups['Terminados'][] = $r;
    } elseif (str_contains($s, 'proceso')) {
        $groups['En proceso'][] = $r;
    } elseif (str_contains($s, 'asign')) {
        $groups['Asignados'][] = $r;
    } else {
        $groups['Pendientes'][] = $r;   // todo lo demás (incluye "sin estatus")
    }
}

require 'partials/header.php';
?>

<div class="page-heading">
    <div>
        <h1>Seguimiento</h1>
        <p>Vista rápida del flujo de atención.</p>
    </div>
</div>

<div class="kanban">
    <?php foreach ($groups as $name => $items): ?>
        <div class="kanban-col">
            <!-- Título de la columna con el total de reportes -->
            <div class="kanban-title"><?= e($name) ?> <span class="badge gray"><?= count($items) ?></span></div>

            <!-- Tarjetas (máximo 12 por columna) -->
            <?php foreach (array_slice($items, 0, 12) as $r): ?>
                <a class="ticket" href="reporte.php?id=<?= (int) $r['id_reporte'] ?>" style="display:block;text-decoration:none;color:inherit">
                    <strong>R-<?= str_pad((string) $r['folio_reporte'], 6, '0', STR_PAD_LEFT) ?></strong>
                    <small><?= e($r['usuario'] ?: 'Sin usuario') ?></small>
                    <small><?= e($r['tipo'] ?: 'Sin clasificar') ?></small>
                    <!-- Prioridad: ALTA=rojo, BAJA=verde, otra=amarillo -->
                    <span class="badge <?= $r['prioridad'] === 'ALTA' ? 'red' : ($r['prioridad'] === 'BAJA' ? 'green' : 'yellow') ?>" style="margin-top:8px">
                        <?= e($r['prioridad'] ?: 'MEDIA') ?>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
</div>

<?php require 'partials/footer.php'; ?>
