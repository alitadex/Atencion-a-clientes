<?php
/**
 * usuarios.php — LISTADO DE USUARIOS (padrón)
 * ------------------------------------------------------------
 * PARA QUÉ SIRVE: lista los usuarios en una tabla con:
 *   - Búsqueda por cuenta, nombre, teléfono o colonia (?q=)
 *   - Filtro por tarifa (?tarifa=)
 *   - Filtro por cantidad de reportes: mínimo y máximo (?rmin= y ?rmax=)
 *   - Orden: de MAYOR a MENOR cantidad de reportes
 *   - Paginación de 20 en 20 (?page=)
 *   - Botón para abrir el expediente del usuario (usuario.php)
 * Todos los filtros se pueden combinar.
 * ------------------------------------------------------------
 */
require_once 'config/app.php';
requireRole('administrador', 'empleado');

$active    = 'usuarios';
$pageTitle = 'Usuarios';

// ---------- 1) PARÁMETROS DE BÚSQUEDA, FILTROS Y PAGINACIÓN ----------
$q      = trim($_GET['q'] ?? '');
$tarifa = trim($_GET['tarifa'] ?? '');

// Cantidad de reportes: solo se aceptan números enteros >= 0; si está vacío = sin límite
$rmin = (isset($_GET['rmin']) && ctype_digit(trim($_GET['rmin']))) ? (int) trim($_GET['rmin']) : null;
$rmax = (isset($_GET['rmax']) && ctype_digit(trim($_GET['rmax']))) ? (int) trim($_GET['rmax']) : null;

$page   = max(1, (int) ($_GET['page'] ?? 1));   // página actual (mínimo 1)
$limit  = 20;                                   // usuarios por página
$offset = ($page - 1) * $limit;                 // cuántos registros saltar

// ¿Hay algún filtro activo? (para mostrar el botón "Limpiar")
$hayFiltros = ($q !== '' || $tarifa !== '' || $rmin !== null || $rmax !== null);

// ---------- 2) ARMAR EL WHERE SEGÚN LOS FILTROS ----------
// Número de reportes de cada usuario (0 si no tiene). Viene del JOIN que se
// define en las consultas de abajo (alias "rc").
$cuentaReportes = 'COALESCE(rc.total, 0)';

$cond   = [];   // condiciones SQL
$params = [];   // valores de cada condición (consulta preparada)

// Texto: cuenta, nombre, teléfono o colonia
if ($q !== '') {
    $cond[] = '(CAST(u.cuenta AS CHAR) LIKE :q1
             OR u.usuario  LIKE :q2
             OR u.telefono LIKE :q3
             OR u.colonia  LIKE :q4)';
    $params['q1'] = "%$q%";
    $params['q2'] = "%$q%";
    $params['q3'] = "%$q%";
    $params['q4'] = "%$q%";
}

// Tarifa (coincidencia exacta con la tarifa elegida en la lista)
if ($tarifa !== '') {
    $cond[] = 'u.tarifa = :tarifa';
    $params['tarifa'] = $tarifa;
}

// Cantidad mínima / máxima de reportes
if ($rmin !== null) {
    $cond[] = "$cuentaReportes >= :rmin";
    $params['rmin'] = $rmin;
}
if ($rmax !== null) {
    $cond[] = "$cuentaReportes <= :rmax";
    $params['rmax'] = $rmax;
}

$where = $cond ? 'WHERE ' . implode(' AND ', $cond) : '';

// Tabla auxiliar: cuántos reportes tiene cada cuenta (se calcula una sola vez)
$joinReportes = 'LEFT JOIN (SELECT cuenta, COUNT(*) AS total FROM reportes GROUP BY cuenta) rc
                        ON rc.cuenta = u.cuenta';

// ---------- 3) TOTAL DE USUARIOS (para calcular páginas) ----------
$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios u $joinReportes $where");
$totalStmt->execute($params);
$total = (int) $totalStmt->fetchColumn();

// ---------- 4) USUARIOS DE LA PÁGINA ACTUAL ----------
// "reportes" = cantidad de reportes del usuario; la subconsulta cuenta sus convenios.
// ORDER BY: primero quien tiene MÁS reportes; si empatan, por nombre.
$stmt = $pdo->prepare("
    SELECT u.*,
           $cuentaReportes AS reportes,
           (SELECT COUNT(*) FROM convenios c WHERE c.cuenta = u.cuenta) AS convenios
    FROM usuarios u
    $joinReportes
    $where
    ORDER BY $cuentaReportes DESC, u.usuario
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$pages = max(1, (int) ceil($total / $limit));   // total de páginas

// ---------- 5) DATOS PARA LAS LISTAS DEL FILTRO ----------
// Tarifas que existen en el padrón
$tarifas = $pdo->query("
    SELECT DISTINCT tarifa FROM usuarios
    WHERE tarifa IS NOT NULL AND tarifa <> ''
    ORDER BY tarifa
")->fetchAll(PDO::FETCH_COLUMN);

// Texto de los filtros para mantenerlos al cambiar de página (?q=...&tarifa=...)
$qs = http_build_query(array_filter([
    'q'      => $q,
    'tarifa' => $tarifa,
    'rmin'   => $rmin,
    'rmax'   => $rmax,
], fn($v) => $v !== null && $v !== ''));

require 'partials/header.php';
?>

<div class="page-heading">
    <div>
        <h1>Usuarios</h1>
        <p>Consulta el padrón y abre el expediente de cada usuario.</p>
    </div>
    <?php if (tieneRol('administrador')): ?>
    <div>
        <a class="btn primary" href="#">＋ Nuevo  Usuario</a>
    </div>
    <?php endif; ?>
</div>

<section class="panel">

    <!-- ===== BUSCADOR Y FILTROS ===== -->
    <form class="filters" method="get">
        <input name="q" value="<?= e($q) ?>" placeholder="Buscar por cuenta, nombre, teléfono o colonia...">

        <!-- Tarifa -->
        <select name="tarifa" title="Tarifa">
            <option value="">Todas las tarifas</option>
            <?php foreach ($tarifas as $t): ?>
                <option value="<?= e($t) ?>" <?= $tarifa === $t ? 'selected' : '' ?>><?= e($t) ?></option>
            <?php endforeach; ?>
        </select>

        <!-- Cantidad de reportes (mínimo y máximo) -->
        <label>Reportes de <input type="number" name="rmin" min="0" style="width:80px" value="<?= $rmin ?? '' ?>"></label>
        <label>a <input type="number" name="rmax" min="0" style="width:80px" value="<?= $rmax ?? '' ?>"></label>

        <button class="btn primary">Buscar</button>
        <?php if ($hayFiltros): ?><a class="btn" href="usuarios.php">Limpiar</a><?php endif; ?>
    </form>

    <!-- ===== TABLA DE USUARIOS ===== -->
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Cuenta</th><th>Usuario</th><th>Colonia</th><th>Dirección</th>
                    <th>Teléfono</th><th>Tarifa</th><th>Reportes ↓</th><th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><strong><?= e((string) $r['cuenta']) ?></strong></td>
                    <td><?= e($r['usuario']) ?></td>
                    <td><?= e($r['colonia']) ?></td>
                    <td><?= e($r['direccion']) ?></td>
                    <td><?= e($r['telefono']) ?></td>
                    <td><?= e($r['tarifa']) ?></td>
                    <td><span class="badge blue"><?= e((string) $r['reportes']) ?></span></td>
                    <td><a class="btn" href="usuario.php?cuenta=<?= e((string) $r['cuenta']) ?>">Ver expediente</a></td>
                </tr>
            <?php endforeach; if (!$rows): ?>
                <tr><td colspan="8" class="empty">No se encontraron usuarios.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- ===== PAGINACIÓN ===== -->
    <div class="panel-head">
        <small>Mostrando <?= count($rows) ?> de <?= number_format($total) ?> usuarios</small>
        <div>
            <?php
            // Muestra la página actual y hasta 2 antes y 2 después (conservando los filtros)
            for ($p = max(1, $page - 2); $p <= min($pages, $page + 2); $p++):
            ?>
                <a class="btn <?= $p === $page ? 'primary' : '' ?>" href="?<?= $qs ? $qs . '&' : '' ?>page=<?= $p ?>"><?= $p ?></a>
            <?php endfor; ?>
        </div>
    </div>
</section>

<?php require 'partials/footer.php'; ?>
