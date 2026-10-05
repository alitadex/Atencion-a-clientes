<?php
/**
 * index.php — PÁGINA DE INICIO (Dashboard)
 * ------------------------------------------------------------
 * PARA QUÉ SIRVE: muestra un resumen general:
 *   1. Tarjetas con totales (usuarios, reportes, órdenes, convenios)
 *   2. Gráfica de barras de reportes por tipo
 *   3. Leyenda de estados
 *   4. Tabla con los 8 reportes más recientes
 * ------------------------------------------------------------
 */
require_once __DIR__ . '/../config/app.php';
requireAdmin();

$basePath = '../';

$active    = 'dashboard';   // resalta "Inicio" en el menú
$pageTitle = 'Inicio';

// ---------- 1) TOTALES PARA LAS TARJETAS ----------
$usuarios  = (int) scalar($pdo, 'SELECT COUNT(*) FROM usuarios');
$reportes  = (int) scalar($pdo, 'SELECT COUNT(*) FROM reportes');
$ordenes   = (int) scalar($pdo, 'SELECT COUNT(*) FROM ordenes_trabajo');
$convenios = (int) scalar($pdo, 'SELECT COUNT(*) FROM convenios');

// ---------- 2) ÚLTIMOS 8 REPORTES ----------
// Une cada reporte con su usuario, tipo y estatus (catálogos).
// COALESCE: si no hay usuario/prioridad en un lado, usa el del otro.
$ultimos = $pdo->query("
    SELECT r.folio_reporte,
           r.fecha_reporte,
           COALESCE(u.usuario, r.usuario)          AS usuario,
           cr.nombre                               AS tipo,
           ce.nombre                               AS estatus,
           COALESCE(r.prioridad, cr.prioridad)     AS prioridad
    FROM reportes r
    LEFT JOIN usuarios u       ON u.cuenta = r.cuenta
    LEFT JOIN cat_reportes cr  ON cr.id_reporte = r.tipo_reporte_id
    LEFT JOIN cat_estatus ce   ON ce.id_estatus = r.estatus_final_id
    ORDER BY r.fecha_reporte DESC
    LIMIT 8
")->fetchAll();

// ---------- 3) REPORTES POR TIPO (para la gráfica de barras) ----------
// Cuenta cuántos reportes hay de cada tipo; muestra los 7 más frecuentes.
$tipos = $pdo->query("
    SELECT cr.nombre, COUNT(r.id_reporte) AS total
    FROM cat_reportes cr
    LEFT JOIN reportes r ON r.tipo_reporte_id = cr.id_reporte
    GROUP BY cr.id_reporte, cr.nombre
    ORDER BY total DESC
    LIMIT 7
")->fetchAll();

require __DIR__ . '/../partials/header.php';
?>

<div class="dashboard-hero"><div><h1>¡Hola, <?= e(ucfirst($rolSesion)) ?> <?= e($nombreSesion) ?>!</h1><p>Resumen general de atención a clientes. Consulta y administra la operación desde un solo lugar.</p></div><div class="hero-badge">Panel administrativo</div></div>

<!-- ===== ENCABEZADO DE LA PÁGINA ===== -->
<div class="page-heading"><div><h1>Resumen</h1><p>Indicadores principales y actividad reciente.</p></div><a class="btn primary" href="../nuevo_reporte.php">＋ Nuevo reporte</a></div>

<!-- ===== TARJETAS DE TOTALES ===== -->
<div class="cards">
    <div class="card stat">
        <div class="stat-icon">♙</div>
        <div><strong><?= number_format($usuarios) ?></strong><span>Usuarios registrados</span></div>
    </div>
    <div class="card stat">
        <div class="stat-icon">▤</div>
        <div><strong><?= number_format($reportes) ?></strong><span>Reportes / quejas</span></div>
    </div>
    <div class="card stat">
        <div class="stat-icon">⚒</div>
        <div><strong><?= number_format($ordenes) ?></strong><span>Órdenes de trabajo</span></div>
    </div>
    <div class="card stat">
        <div class="stat-icon">♧</div>
        <div><strong><?= number_format($convenios) ?></strong><span>Convenios</span></div>
    </div>
</div>

<div class="grid-2">

    <!-- ===== GRÁFICA: REPORTES POR TIPO ===== -->
    <section class="panel">
        <div class="panel-head">
            <h3>Reportes por tipo</h3>
            <a class="btn" href="../reportes.php">Ver todos</a>
        </div>
        <div class="panel-body">
            <div class="chart-bars">
                <?php
                // Valor más alto, para calcular la altura proporcional de cada barra
                $max = max(1, ...array_column($tipos, 'total'));
                foreach ($tipos as $t):
                    // Altura en píxeles: mínimo 8px, máximo 145px
                    $altura = max(8, ($t['total'] / $max) * 145);
                ?>
                    <div class="bar-col">
                        <div class="bar" style="--h:<?= $altura ?>px" title="<?= e($t['nombre']) ?>: <?= $t['total'] ?>"></div>
                        <!-- Nombre recortado a 14 caracteres para que quepa -->
                        <small><?= e(mb_strimwidth($t['nombre'], 0, 14, '…')) ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ===== RESUMEN DE ESTADOS (leyenda de colores) ===== -->
    <!-- NOTA: la dona (.donut) es solo diseño en CSS; no lee datos de la BD todavía. -->
    <section class="panel">
        <div class="panel-head"><h3>Resumen de estados</h3></div>
        <div class="panel-body">
            <div class="donut-wrap">
                <div class="donut"></div>
                <div class="legend">
                    <span><i class="dot" style="background:#087df5"></i>En proceso</span>
                    <span><i class="dot" style="background:#13a673"></i>Solucionados</span>
                    <span><i class="dot" style="background:#eea800"></i>Pendientes</span>
                    <span><i class="dot" style="background:#7856d6"></i>Asignados</span>
                    <span><i class="dot" style="background:#e34b4b"></i>Cancelados</span>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- ===== TABLA: ÚLTIMOS REPORTES ===== -->
<section class="panel" style="margin-top:18px">
    <div class="panel-head">
        <h3>Últimos reportes</h3>
        <a href="../reportes.php" class="btn">Ver todos →</a>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Folio</th><th>Cuenta</th><th>Usuario</th><th>Tipo</th>
                    <th>Fecha</th><th>Prioridad</th><th>Estado</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$ultimos): ?>
                <tr><td colspan="7" class="empty">Todavía no hay reportes registrados.</td></tr>
            <?php else: foreach ($ultimos as $r): ?>
                <tr>
                    <!-- Folio con formato R-000123 (6 dígitos con ceros a la izquierda) -->
                    <td><strong><?= e($r['folio_reporte'] ? 'R-' . str_pad((string) $r['folio_reporte'], 6, '0', STR_PAD_LEFT) : 'Sin folio') ?></strong></td>
                    <!-- NOTA: la consulta no trae la cuenta, por eso aquí siempre aparece "—" -->
                    <td>—</td>
                    <td><?= e($r['usuario'] ?: 'Sin usuario') ?></td>
                    <td><?= e($r['tipo'] ?: 'Sin clasificar') ?></td>
                    <td><?= e(date('d/m/Y H:i', strtotime($r['fecha_reporte']))) ?></td>
                    <!-- Color de la etiqueta según prioridad: ALTA=rojo, BAJA=verde, otra=amarillo -->
                    <td>
                        <span class="badge <?= strtoupper($r['prioridad']) === 'ALTA' ? 'red' : (strtoupper($r['prioridad']) === 'BAJA' ? 'green' : 'yellow') ?>">
                            <?= e($r['prioridad'] ?: 'MEDIA') ?>
                        </span>
                    </td>
                    <td><span class="badge blue"><?= e($r['estatus'] ?: 'Sin estatus') ?></span></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require __DIR__ . '/../partials/footer.php'; ?>
