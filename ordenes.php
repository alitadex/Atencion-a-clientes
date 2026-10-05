<?php
/**
 * ordenes.php — ÓRDENES DE TRABAJO
 * ------------------------------------------------------------
 * PARA QUÉ SIRVE: lista las últimas 150 órdenes de trabajo asignadas
 * a las brigadas, con su reporte, usuario, fecha, personal y estatus.
 *
 * Filtros que se pueden combinar:
 *   - Estatus  (?estatus=)  : un estatus del catálogo
 *   - Personal (?personal=) : nombre (o parte del nombre) del personal asignado.
 *                             Escribir "Sin asignar" muestra las órdenes sin personal.
 *   - Fecha    (?desde= y ?hasta=) : rango de fechas de la orden
 * Además hay un buscador rápido (JavaScript) para buscar dentro de la tabla.
 * ------------------------------------------------------------
 */
require_once 'config/app.php';
requireRole('administrador', 'empleado');

$active    = 'ordenes';
$pageTitle = 'Órdenes de trabajo';

// ---------- 1) FILTROS RECIBIDOS ----------
$status   = (int) ($_GET['estatus'] ?? 0);        // 0 = todos los estatus
$personal = trim($_GET['personal'] ?? '');
$desde    = fechaValida(trim($_GET['desde'] ?? ''));   // fecha inicial (incluida)
$hasta    = fechaValida(trim($_GET['hasta'] ?? ''));   // fecha final (incluida)

// ¿Hay algún filtro activo? (sirve para mostrar el botón "Limpiar")
$hayFiltros = ($status || $personal !== '' || $desde !== '' || $hasta !== '');

// ---------- 2) ARMAR EL WHERE SEGÚN LOS FILTROS ----------
$where  = [];   // condiciones SQL
$params = [];   // valores de cada condición (consulta preparada)

// Estatus
if ($status) {
    $where[] = 'o.estatus_id = :status';
    $params['status'] = $status;
}

// Personal asignado
if ($personal !== '') {
    if (mb_strtolower($personal) === 'sin asignar') {
        // Órdenes que no tienen a nadie asignado
        $where[] = "(o.personal_asignado IS NULL OR o.personal_asignado = '')";
    } else {
        // Coincide si el nombre contiene el texto escrito
        $where[] = 'o.personal_asignado LIKE :personal';
        $params['personal'] = "%$personal%";
    }
}

// Fecha desde: desde las 00:00:00 de ese día
if ($desde !== '') {
    $where[] = 'o.fecha_orden >= :desde';
    $params['desde'] = $desde . ' 00:00:00';
}

// Fecha hasta: incluye TODO ese día (se compara contra el día siguiente a las 00:00)
if ($hasta !== '') {
    $where[] = 'o.fecha_orden < :hasta';
    $params['hasta'] = (new DateTime($hasta))->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
}

// Une las condiciones con AND (o deja vacío si no hay filtros)
$w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ---------- 3) CONSULTA PRINCIPAL ----------
// Órdenes con el nombre del usuario y el estatus (si no hay usuario: "Sin usuario")
$st = $pdo->prepare("
    SELECT o.*,
           COALESCE(u.usuario, 'Sin usuario') AS usuario,
           ce.nombre AS estatus
    FROM ordenes_trabajo o
    LEFT JOIN usuarios u     ON u.cuenta = o.cuenta
    LEFT JOIN cat_estatus ce ON ce.id_estatus = o.estatus_id
    $w
    ORDER BY o.fecha_orden DESC
    LIMIT 150
");
$st->execute($params);
$rows = $st->fetchAll();

// ---------- 4) DATOS PARA LAS LISTAS DEL FILTRO ----------
// Catálogo de estatus
$statuses = $pdo->query('SELECT * FROM cat_estatus ORDER BY nombre')->fetchAll();

// Nombres de personal ya registrados en las órdenes (sugerencias al escribir)
$personas = $pdo->query("
    SELECT DISTINCT personal_asignado
    FROM ordenes_trabajo
    WHERE personal_asignado IS NOT NULL AND personal_asignado <> ''
    ORDER BY personal_asignado
    LIMIT 200
")->fetchAll(PDO::FETCH_COLUMN);

require 'partials/header.php';
?>

<div class="page-heading">
    <div>
        <h1>Órdenes de trabajo</h1>
        <p>Control de trabajos asignados a las brigadas.</p>
    </div>
</div>

<section class="panel">

    <!-- ===== FILTROS (se envían al servidor) ===== -->
    <form class="filters" method="get">
        <!-- Estatus -->
        <select name="estatus" title="Estatus">
            <option value="0">Todos los estatus</option>
            <?php foreach ($statuses as $s): ?>
                <option value="<?= $s['id_estatus'] ?>" <?= $status == $s['id_estatus'] ? 'selected' : '' ?>>
                    <?= e($s['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <!-- Personal: texto libre con sugerencias de los nombres ya registrados -->
        <input name="personal" list="listaPersonal" value="<?= e($personal) ?>" placeholder="Personal asignado...">
        <datalist id="listaPersonal">
            <option value="Sin asignar">
            <?php foreach ($personas as $per): ?>
                <option value="<?= e($per) ?>">
            <?php endforeach; ?>
        </datalist>

        <!-- Rango de fechas -->
        <label>Desde <input type="date" name="desde" value="<?= e($desde) ?>"></label>
        <label>Hasta <input type="date" name="hasta" value="<?= e($hasta) ?>"></label>

        <button class="btn primary">Filtrar</button>
        <?php if ($hayFiltros): ?>
            <a class="btn" href="ordenes.php">Limpiar</a>
        <?php endif; ?>
    </form>

    <!-- Buscador rápido (lo maneja el <script> del final) + contador de registros -->
    <div class="filters">
        <input placeholder="Buscar folio o reporte..." id="filterTable">
        <span class="badge blue"><?= count($rows) ?> registros<?= $hayFiltros ? '' : ' recientes' ?></span>
    </div>

    <div class="table-wrap">
        <table class="data-table" id="ordersTable">
            <thead>
                <tr>
                    <th>Orden</th><th>Reporte</th><th>Usuario</th><th>Fecha</th>
                    <th>Personal</th><th>Estatus</th><th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <!-- Folio con formato OT-000123 -->
                    <td>OT-<?= str_pad((string) $r['folio_orden'], 6, '0', STR_PAD_LEFT) ?></td>
                    <td><?= e($r['folio_reporte'] ? 'R-' . $r['folio_reporte'] : '—') ?></td>
                    <td><?= e($r['usuario']) ?></td>
                    <td><?= e(date('d/m/Y H:i', strtotime($r['fecha_orden']))) ?></td>
                    <td><?= e($r['personal_asignado'] ?: 'Sin asignar') ?></td>
                    <!-- Verde si el estatus contiene "termin"; amarillo en cualquier otro caso -->
                    <td>
                        <span class="badge <?= $r['estatus'] && stripos($r['estatus'], 'termin') !== false ? 'green' : 'yellow' ?>">
                            <?= e($r['estatus'] ?: 'Pendiente') ?>
                        </span>
                    </td>
                    <!-- Si la orden tiene reporte, enlaza a la lista de reportes filtrada por ese folio -->
                    <td>
                        <?php if ($r['folio_reporte']): ?>
                            <a class="btn" href="reportes.php?q=<?= urlencode((string) $r['folio_reporte']) ?>">Ver reporte</a>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; if (!$rows): ?>
                <tr><td colspan="7" class="empty">No hay órdenes de trabajo que coincidan con los filtros.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<script>
// Buscador rápido: al escribir, oculta las filas que no contienen el texto.
document.getElementById('filterTable')?.addEventListener('input', e => {
    const q = e.target.value.toLowerCase();
    document.querySelectorAll('#ordersTable tbody tr').forEach(r =>
        r.style.display = r.innerText.toLowerCase().includes(q) ? '' : 'none'
    );
});
</script>

<?php require 'partials/footer.php'; ?>
