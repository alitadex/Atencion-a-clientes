<?php
/**
 * reportes.php — LISTADO DE REPORTES / QUEJAS
 * ------------------------------------------------------------
 * PARA QUÉ SIRVE: tabla con los últimos 100 reportes, con filtros
 * que se pueden combinar entre sí:
 *   - Texto (?q=): folio, cuenta o nombre de usuario
 *   - Estatus (?estatus=): un estatus del catálogo
 *   - Prioridad (?prioridad=): BAJA, MEDIA o ALTA
 *   - Fecha (?desde= y ?hasta=): rango de fechas del reporte
 * Cada fila tiene un botón "Ver" que abre reporte.php?id=...
 * ------------------------------------------------------------
 */
require_once 'config/app.php';
requireRole('administrador', 'empleado');

$active    = 'reportes';
$pageTitle = 'Reportes / Quejas';

// ---------- 1) FILTROS RECIBIDOS ----------
$q      = trim($_GET['q'] ?? '');
$status = (int) ($_GET['estatus'] ?? 0);   // 0 = todos los estatus

// Prioridad: solo se aceptan los tres valores válidos; cualquier otro = sin filtro
$prioridad = strtoupper(trim($_GET['prioridad'] ?? ''));
if (!in_array($prioridad, ['BAJA', 'MEDIA', 'ALTA'], true)) {
    $prioridad = '';
}

/**
 * fechaValida() — revisa que el texto sea una fecha real con formato AAAA-MM-DD
 * (el que envía <input type="date">). Devuelve la fecha o '' si no es válida.
 */
function fechaValida(string $f): string
{
    $d = DateTime::createFromFormat('Y-m-d', $f);
    return ($d && $d->format('Y-m-d') === $f) ? $f : '';
}
$desde = fechaValida(trim($_GET['desde'] ?? ''));   // fecha inicial (incluida)
$hasta = fechaValida(trim($_GET['hasta'] ?? ''));   // fecha final (incluida)

// ¿Hay algún filtro activo? (sirve para mostrar el botón "Limpiar")
$hayFiltros = ($q !== '' || $status || $prioridad !== '' || $desde !== '' || $hasta !== '');

// ---------- 2) ARMAR EL WHERE SEGÚN LOS FILTROS ----------
$where  = [];   // condiciones SQL
$params = [];   // valores de cada condición (consulta preparada)

// Texto: folio, cuenta o nombre de usuario
if ($q !== '') {
    $where[] = '(CAST(r.folio_reporte AS CHAR) LIKE :q1
              OR CAST(r.cuenta AS CHAR) LIKE :q2
              OR COALESCE(u.usuario, r.usuario) LIKE :q3)';
    $params['q1'] = "%$q%";
    $params['q2'] = "%$q%";
    $params['q3'] = "%$q%";
}

// Estatus
if ($status) {
    $where[] = 'r.estatus_final_id = :status';
    $params['status'] = $status;
}

// Prioridad. Los reportes sin prioridad se muestran como "MEDIA" en la tabla,
// por eso al filtrar MEDIA también se incluyen los que la tienen vacía.
if ($prioridad !== '') {
    if ($prioridad === 'MEDIA') {
        $where[] = "(r.prioridad = 'MEDIA' OR r.prioridad IS NULL OR r.prioridad = '')";
    } else {
        $where[] = 'r.prioridad = :prioridad';
        $params['prioridad'] = $prioridad;
    }
}

// Fecha desde: desde las 00:00:00 de ese día
if ($desde !== '') {
    $where[] = 'r.fecha_reporte >= :desde';
    $params['desde'] = $desde . ' 00:00:00';
}

// Fecha hasta: incluye TODO ese día (se compara contra el día siguiente a las 00:00)
if ($hasta !== '') {
    $where[] = 'r.fecha_reporte < :hasta';
    $params['hasta'] = (new DateTime($hasta))->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
}

// Une las condiciones con AND (o deja vacío si no hay filtros)
$w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ---------- 3) CONSULTA PRINCIPAL ----------
$st = $pdo->prepare("
    SELECT r.*,
           COALESCE(u.usuario, r.usuario) AS usuario,
           cr.nombre AS tipo,
           ce.nombre AS estatus
    FROM reportes r
    LEFT JOIN usuarios u      ON u.cuenta = r.cuenta
    LEFT JOIN cat_reportes cr ON cr.id_reporte = r.tipo_reporte_id
    LEFT JOIN cat_estatus ce  ON ce.id_estatus = r.estatus_final_id
    $w
    ORDER BY r.fecha_reporte DESC
    LIMIT 100
");
$st->execute($params);
$rows = $st->fetchAll();

// Catálogo de estatus (para llenar la lista desplegable del filtro)
$statuses = $pdo->query('SELECT * FROM cat_estatus ORDER BY nombre')->fetchAll();

require 'partials/header.php';
?>

<div class="page-heading">
    <div>
        <h1>Reportes / Quejas</h1>
        <p>Registra, consulta y da seguimiento a las solicitudes de los usuarios.</p>
    </div>
    <?php if (tieneRol('administrador')): ?><a class="btn primary" href="nuevo_reporte.php">＋ Nuevo reporte</a><?php endif; ?>
</div>

<section class="panel">

    <!-- ===== FILTROS ===== -->
    <form class="filters" method="get">
        <!-- Texto libre -->
        <input name="q" value="<?= e($q) ?>" placeholder="Buscar folio, cuenta o usuario...">

        <!-- Estatus -->
        <select name="estatus" title="Estatus">
            <option value="0">Todos los estatus</option>
            <?php foreach ($statuses as $s): ?>
                <option value="<?= $s['id_estatus'] ?>" <?= $status == $s['id_estatus'] ? 'selected' : '' ?>>
                    <?= e($s['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <!-- Prioridad -->
        <select name="prioridad" title="Prioridad">
            <option value="">Todas las prioridades</option>
            <?php foreach (['ALTA', 'MEDIA', 'BAJA'] as $p): ?>
                <option value="<?= $p ?>" <?= $prioridad === $p ? 'selected' : '' ?>><?= $p ?></option>
            <?php endforeach; ?>
        </select>

        <!-- Rango de fechas -->
        <label>Desde <input type="date" name="desde" value="<?= e($desde) ?>"></label>
        <label>Hasta <input type="date" name="hasta" value="<?= e($hasta) ?>"></label>

        <button class="btn primary">Filtrar</button>
        <?php if ($hayFiltros): ?>
            <a class="btn" href="reportes.php">Limpiar</a>
        <?php endif; ?>
    </form>

    <!-- ===== TABLA ===== -->
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Folio</th><th>Usuario</th><th>Tipo</th><th>Fecha</th>
                    <th>Prioridad</th><th>Estado</th><th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><strong>R-<?= str_pad((string) $r['folio_reporte'], 6, '0', STR_PAD_LEFT) ?></strong></td>
                    <td><?= e($r['usuario'] ?: '—') ?></td>
                    <td><?= e($r['tipo'] ?: '—') ?></td>
                    <td><?= e(date('d/m/Y H:i', strtotime($r['fecha_reporte']))) ?></td>
                    <!-- Color según prioridad: ALTA=rojo, BAJA=verde, otra=amarillo -->
                    <td><span class="badge <?= $r['prioridad'] === 'ALTA' ? 'red' : ($r['prioridad'] === 'BAJA' ? 'green' : 'yellow') ?>"><?= e($r['prioridad'] ?: 'MEDIA') ?></span></td>
                    <td><span class="badge blue"><?= e($r['estatus'] ?: 'Sin estatus') ?></span></td>
                    <td><a class="btn" href="reporte.php?id=<?= (int) $r['id_reporte'] ?>">Ver</a></td>
                </tr>
            <?php endforeach; if (!$rows): ?>
                <tr><td colspan="7" class="empty">No hay reportes que coincidan con los filtros.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require 'partials/footer.php'; ?>
