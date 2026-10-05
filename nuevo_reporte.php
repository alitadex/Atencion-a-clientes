<?php
/**
 * nuevo_reporte.php — CAPTURAR UN NUEVO REPORTE / QUEJA
 * ------------------------------------------------------------
 * PARA QUÉ SIRVE: formulario para registrar un reporte nuevo.
 *   - Si llega ?cuenta=123 precarga nombre y teléfono del usuario.
 *   - Al enviarse (POST) genera el siguiente folio, guarda el reporte
 *     con estatus inicial "pendiente/recibido" y redirige a su detalle.
 *   - Si algo falla muestra el mensaje de error sobre el formulario.
 * ------------------------------------------------------------
 */
require_once 'config/app.php';
requireAdmin();

$active    = 'reportes';
$pageTitle = 'Nuevo reporte';

// ---------- 1) PRECARGAR DATOS DEL USUARIO (si viene ?cuenta=) ----------
$cuenta  = (int) ($_GET['cuenta'] ?? 0);
$usuario = null;
if ($cuenta) {
    $s = $pdo->prepare('SELECT * FROM usuarios WHERE cuenta = ?');
    $s->execute([$cuenta]);
    $usuario = $s->fetch();
}

// ---------- 2) CATÁLOGOS PARA LAS LISTAS DESPLEGABLES (solo activos) ----------
$tipos   = $pdo->query('SELECT * FROM cat_reportes WHERE activo = 1 ORDER BY nombre')->fetchAll();
$medios  = $pdo->query('SELECT * FROM cat_medios_reporte WHERE activo = 1 ORDER BY nombre')->fetchAll();
$estatus = $pdo->query('SELECT * FROM cat_estatus WHERE activo = 1 ORDER BY nombre')->fetchAll();  // (no se usa en el formulario)

$error = '';

// ---------- 3) GUARDAR EL REPORTE (cuando se envía el formulario) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Datos capturados
        $cu        = (int) $_POST['cuenta'];
        $tipo      = (int) $_POST['tipo'];
        $medio     = (int) $_POST['medio'];
        $prioridad = $_POST['prioridad'];
        $desc      = trim($_POST['descripcion']);
        $ref       = trim($_POST['referencias']);
        $tel       = trim($_POST['telefono']);
        $sol       = trim($_POST['solicitante']);

        // Siguiente folio = el folio más alto actual + 1
        $last = (int) scalar($pdo, 'SELECT COALESCE(MAX(folio_reporte), 0) + 1 FROM reportes');

        // Estatus inicial: el primer estatus cuyo nombre contenga "pend" o "recib"
        $defaultStatus = (int) scalar($pdo, "
            SELECT id_estatus FROM cat_estatus
            WHERE LOWER(nombre) LIKE '%pend%' OR LOWER(nombre) LIKE '%recib%'
            ORDER BY id_estatus LIMIT 1
        ");

        // Inserta el reporte con la fecha/hora actual (NOW())
        $stmt = $pdo->prepare("
            INSERT INTO reportes
                (folio_reporte, fecha_reporte, tipo_reporte_id, prioridad, medio_id,
                 especificaciones, cuenta, referencias, solicitante, telefono, estatus_final_id)
            VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $last, $tipo, $prioridad, $medio,
            $desc, $cu, $ref, $sol, $tel,
            $defaultStatus ?: null,   // si no existe ese estatus, se guarda NULL
        ]);

        // Redirige al detalle del reporte recién creado
        header('Location: reporte.php?id=' . $pdo->lastInsertId() . '&creado=1');
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();   // se muestra más abajo
    }
}

require 'partials/header.php';
?>

<div class="page-heading">
    <div>
        <h1>Nuevo reporte / queja</h1>
        <p>Captura la solicitud en unos cuantos pasos.</p>
    </div>
    <a class="btn" href="reportes.php">Cancelar</a>
</div>

<!-- Mensaje de error (solo si falló el guardado) -->
<?php if ($error): ?>
    <div class="notice">No fue posible guardar el reporte: <?= e($error) ?></div>
<?php endif; ?>

<!-- ===== FORMULARIO ===== -->
<form method="post" class="panel"><input type="hidden" name="_csrf" value="<?= e(csrfToken()) ?>">
    <div class="panel-body">
        <div class="form-grid">

            <div class="form-group">
                <label>Cuenta *</label>
                <input class="form-control" type="number" name="cuenta" value="<?= $usuario['cuenta'] ?? $cuenta ?>" required>
            </div>

            <div class="form-group">
                <label>Solicitante</label>
                <input class="form-control" name="solicitante" value="<?= e($usuario['usuario'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>Tipo de reporte *</label>
                <select class="form-control" name="tipo" required>
                    <option value="">Seleccionar...</option>
                    <?php foreach ($tipos as $t): ?>
                        <option value="<?= $t['id_reporte'] ?>"><?= e($t['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Medio de atención *</label>
                <select class="form-control" name="medio" required>
                    <option value="">Seleccionar...</option>
                    <?php foreach ($medios as $m): ?>
                        <option value="<?= $m['id_medio'] ?>"><?= e($m['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Prioridad</label>
                <select class="form-control" name="prioridad">
                    <option>BAJA</option>
                    <option selected>MEDIA</option>
                    <option>ALTA</option>
                </select>
            </div>

            <div class="form-group">
                <label>Teléfono</label>
                <input class="form-control" name="telefono" value="<?= e($usuario['telefono'] ?? '') ?>">
            </div>

            <div class="form-group full">
                <label>Descripción del problema *</label>
                <textarea class="form-control" name="descripcion" required placeholder="Describe el problema o solicitud..."></textarea>
            </div>

            <div class="form-group full">
                <label>Referencias del domicilio</label>
                <textarea class="form-control" name="referencias" placeholder="Puntos de referencia, ubicación, detalles adicionales..."></textarea>
            </div>
        </div>

        <div class="actions">
            <a class="btn" href="reportes.php">Cancelar</a>
            <button class="btn primary">Crear reporte</button>
        </div>
    </div>
</form>

<?php require 'partials/footer.php'; ?>
