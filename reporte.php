<?php
/**
 * reporte.php — DETALLE Y SEGUIMIENTO DE UN REPORTE
 * ------------------------------------------------------------
 * PARA QUÉ SIRVE: muestra un reporte (?id=123) con:
 *   - Su información (usuario, cuenta, fecha, medio, descripción, referencias)
 *   - Un formulario para cambiar el estatus y agregar un comentario
 *   - El historial de movimientos (línea de tiempo)
 * Al guardar el formulario (POST) actualiza el reporte, registra el
 * movimiento en el historial y recarga la página.
 * ------------------------------------------------------------
 */
require_once 'config/app.php';
requireRole('administrador', 'empleado');

$active = 'reportes';

// ---------- 1) CARGAR EL REPORTE ----------
$id = (int) ($_GET['id'] ?? 0);

$s = $pdo->prepare("
    SELECT r.*,
           COALESCE(u.usuario, r.usuario) AS usuario,
           u.telefono AS user_tel,
           cr.nombre  AS tipo,
           ce.nombre  AS estatus,
           cm.nombre  AS medio
    FROM reportes r
    LEFT JOIN usuarios u            ON u.cuenta = r.cuenta
    LEFT JOIN cat_reportes cr       ON cr.id_reporte = r.tipo_reporte_id
    LEFT JOIN cat_estatus ce        ON ce.id_estatus = r.estatus_final_id
    LEFT JOIN cat_medios_reporte cm ON cm.id_medio = r.medio_id
    WHERE r.id_reporte = ?
");
$s->execute([$id]);
$r = $s->fetch();

// Si el reporte no existe, error 404
if (!$r) {
    http_response_code(404);
    die('Reporte no encontrado.');
}

$pageTitle = 'Reporte R-' . str_pad((string) $r['folio_reporte'], 6, '0', STR_PAD_LEFT);

// ---------- 2) CARGAR EL HISTORIAL DE SEGUIMIENTO ----------
$hist = $pdo->prepare("
    SELECT sr.*, ce.nombre AS estatus
    FROM seguimiento_reportes sr
    LEFT JOIN cat_estatus ce ON ce.id_estatus = sr.estatus_id
    WHERE sr.id_reporte = ?
    ORDER BY sr.fecha DESC
");
$hist->execute([$id]);
$hist = $hist->fetchAll();

// ---------- 3) GUARDAR UNA ACTUALIZACIÓN (cuando se envía el formulario) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAdmin();
    $coment = trim($_POST['comentario'] ?? '');
    $sid    = (int) ($_POST['estatus_id'] ?? 0);   // nuevo estatus elegido

    if ($sid) {
        // a) Actualiza el reporte: nuevo estatus, último comentario y fecha de actualización
        $u = $pdo->prepare('UPDATE reportes
                            SET estatus_final_id = ?, ultima_actualizacion = ?, fecha_ultima_actualizacion = NOW()
                            WHERE id_reporte = ?');
        $u->execute([$sid, $coment, $id]);

        // b) Agrega el movimiento al historial (seguimiento_reportes)
        $g = $pdo->prepare('INSERT INTO seguimiento_reportes (id_reporte, estatus_id, comentario, personal)
                            VALUES (?, ?, ?, ?)');
        $g->execute([$id, $sid, $coment, 'Administrador']);
    }

    // Redirige para recargar la página (evita reenviar el formulario al refrescar)
    header('Location: reporte.php?id=' . $id);
    exit;
}

// Catálogo de estatus para la lista desplegable del formulario
$statuses = $pdo->query('SELECT * FROM cat_estatus WHERE activo = 1 ORDER BY nombre')->fetchAll();

require 'partials/header.php';
?>

<div class="page-heading">
    <div>
        <h1>Reporte R-<?= str_pad((string) $r['folio_reporte'], 6, '0', STR_PAD_LEFT) ?></h1>
        <p><?= e($r['tipo'] ?: 'Sin clasificar') ?> · <?= e($r['usuario'] ?: 'Usuario no identificado') ?></p>
    </div>
    <!-- Crea otro reporte para la misma cuenta -->
    <?php if (tieneRol('administrador')): ?><a class="btn primary" href="nuevo_reporte.php?cuenta=<?= $r['cuenta'] ?>">＋ Nuevo reporte</a><?php endif; ?>
</div>


<div class="detail-grid">

    <!-- ===== INFORMACIÓN DEL REPORTE ===== -->
    <section class="panel">
        <div class="panel-head">
            <h3>Información del reporte</h3>
            <!-- Etiqueta de prioridad: ALTA=rojo, BAJA=verde, otra=amarillo -->
            <span class="badge <?= $r['prioridad'] === 'ALTA' ? 'red' : ($r['prioridad'] === 'BAJA' ? 'green' : 'yellow') ?>"><?= e($r['prioridad']) ?></span>
        </div>
        <div class="panel-body">
            <div class="form-grid">
                <div><small>Usuario</small><strong><?= e($r['usuario']) ?></strong></div>
                <div><small>Cuenta</small><strong><?= e((string) $r['cuenta']) ?></strong></div>
                <div><small>Fecha</small><strong><?= e(date('d/m/Y H:i', strtotime($r['fecha_reporte']))) ?></strong></div>
                <div><small>Medio</small><strong><?= e($r['medio'] ?: '—') ?></strong></div>
                <div class="form-group full">
                    <label>Descripción</label>
                    <!-- nl2br conserva los saltos de línea que escribió el usuario -->
                    <div><?= nl2br(e($r['especificaciones'] ?: '—')) ?></div>
                </div>
                <div class="form-group full">
                    <label>Referencias</label>
                    <div><?= nl2br(e($r['referencias'] ?: '—')) ?></div>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== FORMULARIO: ACTUALIZAR SEGUIMIENTO ===== -->
    <section class="panel">
        <div class="panel-head"><h3>Actualizar seguimiento</h3></div>
        <div class="panel-body">
            <?php if (tieneRol('administrador')): ?>
            <form method="post"><input type="hidden" name="_csrf" value="<?= e(csrfToken()) ?>">
                <div class="form-group">
                    <label>Nuevo estatus</label>
                    <select class="form-control" name="estatus_id" required>
                        <?php foreach ($statuses as $st): ?>
                            <!-- Preselecciona el estatus actual del reporte -->
                            <option value="<?= $st['id_estatus'] ?>" <?= $r['estatus'] === $st['nombre'] ? 'selected' : '' ?>>
                                <?= e($st['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-top:12px">
                    <label>Comentario</label>
                    <textarea class="form-control" name="comentario" placeholder="Describe el avance..."></textarea>
                </div>
                <div class="actions">
                    <button class="btn primary">Guardar actualización</button>
                </div>
            </form>
            <?php else: ?>
                <p class="muted">Modo solo lectura: los empleados pueden consultar el seguimiento, pero no modificar el reporte.</p>
            <?php endif; ?>
        </div>
    </section>
</div>

<!-- ===== HISTORIAL (línea de tiempo) ===== -->
<section class="panel" style="margin-top:18px">
    <div class="panel-head">
        <h3>Historial</h3>
        <span class="badge blue"><?= e($r['estatus'] ?: 'Sin estatus') ?></span>
    </div>
    <div class="panel-body">
        <div class="timeline">
            <?php foreach ($hist as $h): ?>
                <div class="timeline-item">
                    <strong><?= e($h['estatus'] ?: 'Actualización') ?></strong>
                    <small><?= e(date('d/m/Y H:i', strtotime($h['fecha']))) ?> · <?= e($h['personal'] ?: 'Sistema') ?></small>
                    <p><?= nl2br(e($h['comentario'] ?: '')) ?></p>
                </div>
            <?php endforeach; if (!$hist): ?>
                <div class="empty">Aún no hay movimientos de seguimiento.</div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require 'partials/footer.php'; ?>
