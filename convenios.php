<?php
/**
 * convenios.php — CONVENIOS Y PROMESAS DE PAGO
 * ------------------------------------------------------------
 * PARA QUÉ SIRVE: lista los convenios (máximo 150) con adeudo, primer
 * pago, parcialidades y si ya están pagados.
 *
 * Filtros que se pueden combinar:
 *   - Texto (?q=)      : folio, cuenta o nombre de usuario
 *   - Pagado (?pagado=): pendiente, no, o pagado (SI)
 *
 * Orden: de MAYOR a MENOR adeudo.
 *
 * Colores de la columna "Pagado":
 *   SI = verde · NO = rojo · Pendiente (sin valor) = amarillo
 * ------------------------------------------------------------
 */
require_once 'config/app.php';
requireRole('administrador', 'empleado');

$active    = 'convenios';
$pageTitle = 'Convenios';

// ---------- 1) FILTROS RECIBIDOS ----------
$q = trim($_GET['q'] ?? '');

// Estado de pago: solo se aceptan 3 valores; cualquier otro = sin filtro
$pagado = strtolower(trim($_GET['pagado'] ?? ''));
if (!in_array($pagado, ['pendiente', 'no', 'si'], true)) {
    $pagado = '';
}

// ---------- 2) ARMAR EL WHERE SEGÚN LOS FILTROS ----------
$where  = [];
$params = [];

// Texto: cuenta, usuario o folio
if ($q !== '') {
    $where[] = '(CAST(c.cuenta AS CHAR) LIKE :q1
              OR c.usuario LIKE :q2
              OR CAST(c.folio_convenio AS CHAR) LIKE :q3)';
    $params['q1'] = "%$q%";
    $params['q2'] = "%$q%";
    $params['q3'] = "%$q%";
}

// Estado de pago
//   pendiente → la columna "pagado" está vacía (NULL o texto en blanco)
//   no        → "NO"
//   si        → "SI" (pagado)
if ($pagado === 'pendiente') {
    $where[] = "(c.pagado IS NULL OR TRIM(c.pagado) = '')";
} elseif ($pagado === 'no') {
    $where[] = "UPPER(TRIM(c.pagado)) = 'NO'";
} elseif ($pagado === 'si') {
    $where[] = "UPPER(TRIM(c.pagado)) = 'SI'";
}

$w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ---------- 3) CONSULTA ----------
// usuario2 = nombre del padrón; si no existe, el que se guardó en el convenio.
// ORDER BY: mayor adeudo primero (los convenios sin adeudo quedan al final);
// si hay empate, el más reciente primero.
$s = $pdo->prepare("
    SELECT c.*, COALESCE(u.usuario, c.usuario) AS usuario2
    FROM convenios c
    LEFT JOIN usuarios u ON u.cuenta = c.cuenta
    $w
    ORDER BY c.adeudo DESC, c.fecha_convenio DESC, c.id_convenio DESC
    LIMIT 150
");
$s->execute($params);
$rows = $s->fetchAll();

require 'partials/header.php';
?>

<div class="page-heading">
    <div>
        <h1>Convenios</h1>
        <p>Consulta convenios y promesas de pago.</p>
    </div>
    <?php if (tieneRol('administrador')): ?>
    <div>
        <a class="btn primary" href="#">＋ Nuevo  Convenio</a>
    </div>
    <?php endif; ?>
</div>

<section class="panel">

    <!-- ===== FILTROS ===== -->
    <form class="filters">
        <input name="q" value="<?= e($q) ?>" placeholder="Buscar folio, cuenta o usuario...">

        <!-- Estado de pago -->
        <select name="pagado" title="Estado de pago">
            <option value="">Todos los estados</option>
            <option value="pendiente" <?= $pagado === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
            <option value="no"        <?= $pagado === 'no'        ? 'selected' : '' ?>>No</option>
            <option value="si"        <?= $pagado === 'si'        ? 'selected' : '' ?>>Pagado (SI)</option>
        </select>

        <button class="btn primary">Buscar</button>
        <?php if ($q !== '' || $pagado !== ''): ?>
            <a class="btn" href="convenios.php">Limpiar</a>
        <?php endif; ?>
    </form>

    <!-- ===== TABLA ===== -->
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Folio</th><th>Fecha</th><th>Cuenta</th><th>Usuario</th>
                    <th>Adeudo ↓</th><th>Primer pago</th><th>Parcialidades</th><th>Pagado</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $c): ?>
                <?php
                // Color de la etiqueta "Pagado": SI = verde, NO = rojo, vacío (Pendiente) = amarillo
                $pg    = strtoupper(trim((string) $c['pagado']));
                $color = $pg === 'SI' ? 'green' : ($pg === 'NO' ? 'red' : 'yellow');
                ?>
                <tr>
                    <td><strong>C-<?= e((string) $c['folio_convenio']) ?></strong></td>
                    <td><?= e($c['fecha_convenio']) ?></td>
                    <td><?= e((string) $c['cuenta']) ?></td>
                    <td><?= e($c['usuario2'] ?: '—') ?></td>
                    <td><?= money($c['adeudo']) ?></td>
                    <td><?= money($c['primer_pago']) ?></td>
                    <td><?= money($c['parcialidades']) ?></td>
                    <td><span class="badge <?= $color ?>"><?= e($c['pagado'] ?: 'Pendiente') ?></span></td>
                </tr>
            <?php endforeach; if (!$rows): ?>
                <tr><td colspan="8" class="empty">No se encontraron convenios.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require 'partials/footer.php'; ?>
