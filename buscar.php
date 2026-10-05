<?php
/**
 * buscar.php — BÚSQUEDA GLOBAL
 * ------------------------------------------------------------
 * PARA QUÉ SIRVE: recibe el texto del buscador de la barra superior
 * (?q=...) y busca coincidencias en tres tablas a la vez:
 *   - Usuarios   (cuenta, nombre, teléfono, colonia)
 *   - Reportes   (folio, cuenta, nombre de usuario)
 *   - Convenios  (folio, cuenta, nombre de usuario)
 * Cada bloque muestra máximo 12 resultados.
 * ------------------------------------------------------------
 */
require_once 'config/app.php';
requireRole('administrador', 'empleado');

$active    = 'dashboard';
$pageTitle = 'Búsqueda';

$q = trim($_GET['q'] ?? '');            // texto buscado, sin espacios sobrantes
$users = $reports = $convs = [];        // resultados (vacíos por defecto)

if ($q !== '') {
    $p = "%$q%";                        // comodines para LIKE: coincide si contiene el texto

    // ---------- Búsqueda en USUARIOS ----------
    $s = $pdo->prepare("
        SELECT cuenta, usuario, colonia, telefono
        FROM usuarios
        WHERE CAST(cuenta AS CHAR) LIKE ?
           OR usuario  LIKE ?
           OR telefono LIKE ?
           OR colonia  LIKE ?
        LIMIT 12
    ");
    $s->execute([$p, $p, $p, $p]);
    $users = $s->fetchAll();

    // ---------- Búsqueda en REPORTES ----------
    $s = $pdo->prepare("
        SELECT r.id_reporte, r.folio_reporte, r.cuenta,
               COALESCE(u.usuario, r.usuario) AS usuario,
               cr.nombre AS tipo,
               ce.nombre AS estatus
        FROM reportes r
        LEFT JOIN usuarios u      ON u.cuenta = r.cuenta
        LEFT JOIN cat_reportes cr ON cr.id_reporte = r.tipo_reporte_id
        LEFT JOIN cat_estatus ce  ON ce.id_estatus = r.estatus_final_id
        WHERE CAST(r.folio_reporte AS CHAR) LIKE ?
           OR CAST(r.cuenta AS CHAR) LIKE ?
           OR COALESCE(u.usuario, r.usuario) LIKE ?
        LIMIT 12
    ");
    $s->execute([$p, $p, $p]);
    $reports = $s->fetchAll();

    // ---------- Búsqueda en CONVENIOS ----------
    // CORRECCIÓN: antes decía "cuenta" sin prefijo y, como esa columna existe en
    // convenios Y en usuarios, MySQL marcaba "Column 'cuenta' is ambiguous".
    $s = $pdo->prepare("
        SELECT c.id_convenio, c.folio_convenio, c.cuenta,
               COALESCE(u.usuario, c.usuario) AS usuario2,
               c.adeudo, c.pagado
        FROM convenios c
        LEFT JOIN usuarios u ON u.cuenta = c.cuenta
        WHERE CAST(c.folio_convenio AS CHAR) LIKE ?
           OR CAST(c.cuenta AS CHAR) LIKE ?
           OR COALESCE(u.usuario, c.usuario) LIKE ?
        LIMIT 12
    ");
    $s->execute([$p, $p, $p]);
    $convs = $s->fetchAll();
}

require 'partials/header.php';
?>

<div class="page-heading">
    <div>
        <h1>Búsqueda global</h1>
        <p>Resultados para: <strong><?= e($q) ?></strong></p>
    </div>
</div>

<?php if (!$q): ?>
    <!-- Sin texto de búsqueda: solo un mensaje de ayuda -->
    <div class="panel empty">Escribe una cuenta, nombre, folio o teléfono en el buscador superior.</div>

<?php else: ?>
    <div class="grid-2">

        <!-- ===== RESULTADOS: USUARIOS ===== -->
        <section class="panel">
            <div class="panel-head"><h3>Usuarios</h3></div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Cuenta</th><th>Usuario</th><th>Colonia</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?= e((string) $u['cuenta']) ?></td>
                            <td><?= e($u['usuario']) ?></td>
                            <td><?= e($u['colonia']) ?></td>
                            <!-- Abre el expediente completo del usuario -->
                            <td><a class="btn" href="usuario.php?cuenta=<?= e((string) $u['cuenta']) ?>">Abrir</a></td>
                        </tr>
                    <?php endforeach; if (!$users): ?>
                        <tr><td colspan="4" class="empty">Sin resultados.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- ===== RESULTADOS: REPORTES ===== -->
        <section class="panel">
            <div class="panel-head"><h3>Reportes</h3></div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Folio</th><th>Usuario</th><th>Tipo</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($reports as $r): ?>
                        <tr>
                            <td>R-<?= $r['folio_reporte'] ?></td>
                            <td><?= e($r['usuario']) ?></td>
                            <td><?= e($r['tipo']) ?></td>
                            <!-- Abre el detalle del reporte -->
                            <td><a class="btn" href="reporte.php?id=<?= (int) $r['id_reporte'] ?>">Abrir</a></td>
                        </tr>
                    <?php endforeach; if (!$reports): ?>
                        <tr><td colspan="4" class="empty">Sin resultados.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <!-- ===== RESULTADOS: CONVENIOS ===== -->
    <section class="panel" style="margin-top:18px">
        <div class="panel-head"><h3>Convenios</h3></div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Folio</th><th>Cuenta</th><th>Usuario</th><th>Adeudo</th><th>Pagado</th></tr></thead>
                <tbody>
                <?php foreach ($convs as $c): ?>
                    <tr>
                        <td>C-<?= e((string) $c['folio_convenio']) ?></td>
                        <td><?= $c['cuenta'] ?></td>
                        <td><?= e($c['usuario2']) ?></td>
                        <td><?= money($c['adeudo']) ?></td>
                        <td><?= e($c['pagado'] ?: 'Pendiente') ?></td>
                    </tr>
                <?php endforeach; if (!$convs): ?>
                    <tr><td colspan="5" class="empty">Sin resultados.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<?php require 'partials/footer.php'; ?>
