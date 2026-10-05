<?php
/**
 * administrador/usuarios_sistema.php — administración de cuentas del sistema.
 * Administrador: ver, crear y modificar.
 * Empleado: solo lectura.
 */
require_once __DIR__ . '/../config/app.php';
requireRole('administrador', 'empleado');
$basePath = '../';
$pageTitle = 'Usuarios del sistema';
$active = 'sistema';
$error = '';
$mensaje = '';

// ------------------------------------------------------------
// EMPLEADO: solo puede consultar su propio perfil y sus órdenes.
// No se muestran otros usuarios ni formularios de administración.
// La asignación de órdenes puede estar guardada como "personal_asignado"
// (versiones existentes) o como "personal" (esquema base).
// ------------------------------------------------------------
if (tieneRol('empleado')) {
    $idEmpleado = (int) ($_SESSION['usuario_sistema']['id_usuario'] ?? 0);
    $stmt = $pdo->prepare('SELECT id_usuario, nombre, correo, username, telefono, rol, activo, fecha_registro FROM usuarios_sistema WHERE id_usuario = ? LIMIT 1');
    $stmt->execute([$idEmpleado]);
    $miPerfil = $stmt->fetch();

    $columnasOrden = $pdo->query('SHOW COLUMNS FROM ordenes_trabajo')->fetchAll(PDO::FETCH_COLUMN);
    $colAsignacion = in_array('personal_asignado', $columnasOrden, true)
        ? 'personal_asignado'
        : (in_array('personal', $columnasOrden, true) ? 'personal' : null);

    $ordenesEmpleado = [];
    if ($colAsignacion !== null && $miPerfil) {
        // Se acepta nombre o username porque las órdenes existentes pueden haber
        // sido asignadas con cualquiera de esos identificadores.
        $ordenSql = "SELECT * FROM ordenes_trabajo
                     WHERE LOWER(TRIM(`$colAsignacion`)) IN (LOWER(TRIM(?)), LOWER(TRIM(?)))
                     ORDER BY id_orden DESC
                     LIMIT 150";
        $stmt = $pdo->prepare($ordenSql);
        $stmt->execute([$miPerfil['nombre'], $miPerfil['username']]);
        $ordenesEmpleado = $stmt->fetchAll();
    }

    require __DIR__ . '/../partials/header.php';
    ?>
    <div class="page-heading">
        <div>
            <h1>Mi usuario</h1>
            <p>Consulta únicamente tus datos y las órdenes de trabajo asignadas a tu perfil.</p>
        </div>
    </div>

    <?php if (!$miPerfil): ?>
        <div class="notice">No se encontró la información de tu usuario.</div>
    <?php else: ?>
        <section class="panel">
            <div class="panel-head"><h3>Mis datos</h3><span class="badge blue">Solo lectura</span></div>
            <div class="panel-body">
                <div class="form-grid">
                    <div class="form-group"><label>Nombre</label><input class="form-control" value="<?= e($miPerfil['nombre']) ?>" readonly></div>
                    <div class="form-group"><label>Correo</label><input class="form-control" value="<?= e($miPerfil['correo']) ?>" readonly></div>
                    <div class="form-group"><label>Username</label><input class="form-control" value="<?= e($miPerfil['username']) ?>" readonly></div>
                    <div class="form-group"><label>Teléfono</label><input class="form-control" value="<?= e($miPerfil['telefono'] ?? '') ?>" readonly></div>
                    <div class="form-group"><label>Rol</label><input class="form-control" value="Empleado" readonly></div>
                    <div class="form-group"><label>Estado</label><input class="form-control" value="<?= (int)$miPerfil['activo'] === 1 ? 'Activo' : 'Inactivo' ?>" readonly></div>
                </div>
            </div>
        </section>

        <section class="panel" style="margin-top:18px">
            <div class="panel-head">
                <div><h3>Mis órdenes de trabajo</h3><div style="font-size:11px;color:#718096;margin-top:4px">Solo aparecen las órdenes asignadas a tu nombre o username.</div></div>
                <span class="badge blue"><?= count($ordenesEmpleado) ?> órdenes</span>
            </div>
            <div class="table-wrap"><table class="data-table"><thead><tr>
                <th>Orden</th><th>Cuenta</th><th>Descripción</th><th>Asignado a</th><th>Inicio</th><th>Fin</th><th>Estatus</th>
            </tr></thead><tbody>
            <?php if (!$ordenesEmpleado): ?>
                <tr><td colspan="7" class="empty">No tienes órdenes de trabajo asignadas actualmente.</td></tr>
            <?php else: ?>
                <?php foreach ($ordenesEmpleado as $orden): ?>
                    <tr>
                        <td><strong><?= e(isset($orden['folio_orden']) ? 'OT-' . str_pad((string)$orden['folio_orden'], 6, '0', STR_PAD_LEFT) : (string)($orden['id_orden'] ?? '—')) ?></strong></td>
                        <td><?= e($orden['cuenta'] ?? '') ?></td>
                        <td><?= e($orden['descripcion'] ?? '') ?></td>
                        <td><?= e($orden[$colAsignacion] ?? $miPerfil['nombre']) ?></td>
                        <td><?= e($orden['fecha_inicio'] ?? ($orden['fecha_orden'] ?? '—')) ?></td>
                        <td><?= e($orden['fecha_fin'] ?? '—') ?></td>
                        <td><?= e($orden['estatus'] ?? ($orden['estatus_id'] ?? 'Pendiente')) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody></table></div>
        </section>
    <?php endif; ?>
    <?php require __DIR__ . '/../partials/footer.php'; ?>
    <?php
    exit;
}

$editarId = (int) ($_GET['editar'] ?? 0);
$busqueda = trim($_GET['q'] ?? '');
$rolFiltro = $_GET['rol'] ?? '';

if (!in_array($rolFiltro, ['', 'administrador', 'empleado', 'usuario'], true)) {
    $rolFiltro = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAdmin();
    $accion = $_POST['accion'] ?? '';

    try {
        if ($accion === 'crear') {
            $nombre = trim($_POST['nombre'] ?? '');
            $correo = trim($_POST['correo'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $password = (string) ($_POST['password'] ?? '');
            $telefono = trim($_POST['telefono'] ?? '');
            $rol = $_POST['rol'] ?? 'usuario';

            if ($nombre === '' || $correo === '' || $username === '' || $password === '') {
                throw new RuntimeException('Nombre, correo, username y contraseña son obligatorios.');
            }
            if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('El correo no es válido.');
            }
            if (strlen($password) < 8) {
                throw new RuntimeException('La contraseña debe tener al menos 8 caracteres.');
            }
            if (!in_array($rol, ['administrador', 'usuario', 'empleado'], true)) {
                throw new RuntimeException('Rol no válido.');
            }

            $stmt = $pdo->prepare('INSERT INTO usuarios_sistema (nombre, correo, username, password, telefono, rol) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$nombre, $correo, $username, password_hash($password, PASSWORD_DEFAULT), $telefono ?: null, $rol]);
            $mensaje = 'Usuario creado correctamente.';
        }

        if ($accion === 'editar') {
            $id = (int) ($_POST['id_usuario'] ?? 0);
            $nombre = trim($_POST['nombre'] ?? '');
            $correo = trim($_POST['correo'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $password = (string) ($_POST['password'] ?? '');
            $telefono = trim($_POST['telefono'] ?? '');
            $rol = $_POST['rol'] ?? 'usuario';
            $activo = (int) ($_POST['activo'] ?? 1) === 1 ? 1 : 0;

            if ($id <= 0 || $nombre === '' || $correo === '' || $username === '') {
                throw new RuntimeException('Nombre, correo y username son obligatorios.');
            }
            if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('El correo no es válido.');
            }
            if (!in_array($rol, ['administrador', 'usuario', 'empleado'], true)) {
                throw new RuntimeException('Rol no válido.');
            }
            if ($id === (int) ($_SESSION['usuario_sistema']['id_usuario'] ?? 0) && $activo === 0) {
                throw new RuntimeException('No puedes desactivar tu propia cuenta.');
            }
            if ($password !== '' && strlen($password) < 8) {
                throw new RuntimeException('La nueva contraseña debe tener al menos 8 caracteres.');
            }

            if ($password !== '') {
                $stmt = $pdo->prepare('UPDATE usuarios_sistema SET nombre = ?, correo = ?, username = ?, password = ?, telefono = ?, rol = ?, activo = ? WHERE id_usuario = ?');
                $stmt->execute([$nombre, $correo, $username, password_hash($password, PASSWORD_DEFAULT), $telefono ?: null, $rol, $activo, $id]);
            } else {
                $stmt = $pdo->prepare('UPDATE usuarios_sistema SET nombre = ?, correo = ?, username = ?, telefono = ?, rol = ?, activo = ? WHERE id_usuario = ?');
                $stmt->execute([$nombre, $correo, $username, $telefono ?: null, $rol, $activo, $id]);
            }

            $mensaje = 'Usuario actualizado correctamente.';
            $editarId = 0;
        }

        if ($accion === 'estado') {
            $id = (int) ($_POST['id_usuario'] ?? 0);
            $activo = (int) ($_POST['activo'] ?? 0);
            if ($id === (int) ($_SESSION['usuario_sistema']['id_usuario'] ?? 0) && $activo === 0) {
                throw new RuntimeException('No puedes desactivar tu propia cuenta.');
            }
            $stmt = $pdo->prepare('UPDATE usuarios_sistema SET activo = ? WHERE id_usuario = ?');
            $stmt->execute([$activo ? 1 : 0, $id]);
            $mensaje = 'Estado actualizado.';
        }
    } catch (Throwable $e) {
        $error = $e instanceof PDOException && $e->getCode() === '23000'
            ? 'El correo o username ya está registrado.'
            : $e->getMessage();
    }
}

$sql = 'SELECT id_usuario, nombre, correo, username, telefono, rol, activo, fecha_registro FROM usuarios_sistema WHERE 1=1';
$params = [];
if ($busqueda !== '') {
    $sql .= ' AND (nombre LIKE ? OR correo LIKE ? OR username LIKE ? OR telefono LIKE ?)';
    $term = '%' . $busqueda . '%';
    array_push($params, $term, $term, $term, $term);
}
if ($rolFiltro !== '') {
    $sql .= ' AND rol = ?';
    $params[] = $rolFiltro;
}
$sql .= ' ORDER BY CASE WHEN rol = \'empleado\' THEN 0 ELSE 1 END, nombre ASC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$usuarios = $stmt->fetchAll();

$usuarioEditar = null;
if ($editarId > 0 && tieneRol('administrador')) {
    $stmt = $pdo->prepare('SELECT id_usuario, nombre, correo, username, telefono, rol, activo FROM usuarios_sistema WHERE id_usuario = ? LIMIT 1');
    $stmt->execute([$editarId]);
    $usuarioEditar = $stmt->fetch() ?: null;
    if (!$usuarioEditar) {
        $error = 'El usuario seleccionado no existe.';
    }
}

require __DIR__ . '/../partials/header.php';
?>
<div class="page-heading">
    <div>
        <h1>Usuarios del sistema</h1>
        <p><?= tieneRol('administrador') ? 'Consulta, crea y modifica las cuentas que pueden entrar a la aplicación.' : 'Consulta las cuentas del sistema en modo solo lectura.' ?></p>
    </div>
</div>

<?php if ($mensaje): ?><div class="notice"><?= e($mensaje) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice"><?= e($error) ?></div><?php endif; ?>

<section class="panel">
    <div class="panel-head">
        <div>
            <h3>Buscar usuarios</h3>
            <div style="font-size:11px;color:#718096;margin-top:4px">Busca rápidamente por nombre, correo, username o teléfono.</div>
        </div>
    </div>
    <form method="get" class="filters">
        <input type="search" name="q" value="<?= e($busqueda) ?>" placeholder="Ej. Juan, empleado01, correo..." autofocus>
        <select name="rol">
            <option value="">Todos los roles</option>
            <option value="empleado" <?= $rolFiltro === 'empleado' ? 'selected' : '' ?>>Empleados</option>
            <option value="administrador" <?= $rolFiltro === 'administrador' ? 'selected' : '' ?>>Administradores</option>
            <option value="usuario" <?= $rolFiltro === 'usuario' ? 'selected' : '' ?>>Usuarios</option>
        </select>
        <button class="btn primary" type="submit">Buscar</button>
        <a class="btn" href="usuarios_sistema.php">Limpiar</a>
    </form>
</section>

<?php if (tieneRol('administrador')): ?>
<section class="panel" style="margin-top:18px" id="formulario-usuario">
    <div class="panel-head">
        <h3><?= $usuarioEditar ? 'Modificar usuario' : 'Crear usuario' ?></h3>
        <?php if ($usuarioEditar): ?><a class="btn" href="usuarios_sistema.php">Cancelar edición</a><?php endif; ?>
    </div>
    <div class="panel-body">
        <form method="post" class="form-grid"><input type="hidden" name="_csrf" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="accion" value="<?= $usuarioEditar ? 'editar' : 'crear' ?>">
            <?php if ($usuarioEditar): ?><input type="hidden" name="id_usuario" value="<?= (int) $usuarioEditar['id_usuario'] ?>"><?php endif; ?>
            <div class="form-group"><label>Nombre *</label><input class="form-control" name="nombre" value="<?= e($usuarioEditar['nombre'] ?? '') ?>" required></div>
            <div class="form-group"><label>Correo *</label><input class="form-control" type="email" name="correo" value="<?= e($usuarioEditar['correo'] ?? '') ?>" required></div>
            <div class="form-group"><label>Username *</label><input class="form-control" name="username" maxlength="50" value="<?= e($usuarioEditar['username'] ?? '') ?>" required></div>
            <div class="form-group"><label><?= $usuarioEditar ? 'Nueva contraseña (opcional)' : 'Contraseña *' ?></label><input class="form-control" type="password" name="password" minlength="8" <?= $usuarioEditar ? '' : 'required' ?> autocomplete="new-password" placeholder="<?= $usuarioEditar ? 'Dejar vacío para conservarla' : 'Mínimo 8 caracteres' ?>"></div>
            <div class="form-group"><label>Teléfono</label><input class="form-control" name="telefono" value="<?= e($usuarioEditar['telefono'] ?? '') ?>"></div>
            <div class="form-group"><label>Rol *</label><select class="form-control" name="rol">
                <option value="usuario" <?= ($usuarioEditar['rol'] ?? '') === 'usuario' ? 'selected' : '' ?>>Usuario</option>
                <option value="empleado" <?= ($usuarioEditar['rol'] ?? '') === 'empleado' ? 'selected' : '' ?>>Empleado</option>
                <option value="administrador" <?= ($usuarioEditar['rol'] ?? '') === 'administrador' ? 'selected' : '' ?>>Administrador</option>
            </select></div>
            <?php if ($usuarioEditar): ?>
            <div class="form-group"><label>Estado *</label><select class="form-control" name="activo">
                <option value="1" <?= (int)$usuarioEditar['activo'] === 1 ? 'selected' : '' ?>>Activo</option>
                <option value="0" <?= (int)$usuarioEditar['activo'] === 0 ? 'selected' : '' ?>>Inactivo</option>
            </select></div>
            <?php endif; ?>
            <div class="form-group full"><button class="btn primary" type="submit"><?= $usuarioEditar ? 'Guardar cambios' : 'Crear usuario' ?></button></div>
        </form>
    </div>
</section>
<?php endif; ?>

<section class="panel" style="margin-top:18px">
    <div class="panel-head">
        <h3>Usuarios registrados (<?= count($usuarios) ?>)</h3>
        <?php if ($busqueda !== '' || $rolFiltro !== ''): ?><span style="font-size:11px;color:#718096">Filtro aplicado</span><?php endif; ?>
    </div>
    <div class="table-wrap"><table class="data-table"><thead><tr>
        <th>Nombre</th><th>Correo</th><th>Username</th><th>Teléfono</th><th>Rol</th><th>Estado</th>
        <?php if (tieneRol('administrador')): ?><th>Acciones</th><?php endif; ?>
    </tr></thead><tbody>
    <?php if (!$usuarios): ?>
        <tr><td colspan="<?= tieneRol('administrador') ? 7 : 6 ?>" class="empty">No se encontraron usuarios con esos criterios.</td></tr>
    <?php else: ?>
        <?php foreach ($usuarios as $u): ?>
        <tr>
            <td><strong><?= e($u['nombre']) ?></strong></td>
            <td><?= e($u['correo']) ?></td>
            <td><?= e($u['username']) ?></td>
            <td><?= e($u['telefono']) ?></td>
            <td><span class="badge <?= $u['rol'] === 'empleado' ? 'blue' : ($u['rol'] === 'administrador' ? 'yellow' : 'gray') ?>"><?= e(ucfirst($u['rol'])) ?></span></td>
            <td><?= $u['activo'] ? '<span class="badge green">Activo</span>' : '<span class="badge red">Inactivo</span>' ?></td>
            <?php if (tieneRol('administrador')): ?>
            <td>
                <a class="btn" href="usuarios_sistema.php?editar=<?= (int)$u['id_usuario'] ?><?= $busqueda !== '' ? '&q=' . urlencode($busqueda) : '' ?><?= $rolFiltro !== '' ? '&rol=' . urlencode($rolFiltro) : '' ?>#formulario-usuario">Modificar</a>
                <form method="post" style="display:inline;margin-left:5px"><input type="hidden" name="_csrf" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="accion" value="estado">
                    <input type="hidden" name="id_usuario" value="<?= (int)$u['id_usuario'] ?>">
                    <input type="hidden" name="activo" value="<?= $u['activo'] ? 0 : 1 ?>">
                    <button class="btn <?= $u['activo'] ? 'danger' : '' ?>" type="submit"><?= $u['activo'] ? 'Desactivar' : 'Activar' ?></button>
                </form>
            </td>
            <?php endif; ?>
        </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody></table></div>
</section>
<?php require __DIR__ . '/../partials/footer.php'; ?>
