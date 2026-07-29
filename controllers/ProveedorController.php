<?php
// controllers/ProveedorController.php
require_once __DIR__ . '/../models/ProveedorModelo.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['accion'] ?? '';
    $proveedorModel = new Proveedor();

    // 1. ACCIÓN: REGISTRAR PROVEEDOR
    if ($action === 'registrar') {
        $nombre_empresa  = trim($_POST['nombre_empresa'] ?? '');
        $nombre_contacto = trim($_POST['nombre_contacto'] ?? '');
        $telefono        = trim($_POST['telefono'] ?? '');

        if (empty($nombre_empresa)) {
            header('Location: ../index.php?v=proveedores&error=' . urlencode('El nombre de la empresa es obligatorio.'));
            exit;
        }

        $nombre_contacto = ($nombre_contacto === '') ? null : $nombre_contacto;
        $telefono        = ($telefono === '') ? null : $telefono;

        $resultado = $proveedorModel->registrar($nombre_empresa, $nombre_contacto, $telefono);

        if ($resultado) {
            header('Location: ../index.php?v=proveedores&success=' . urlencode('Proveedor registrado correctamente.'));
        } else {
            header('Location: ../index.php?v=proveedores&error=' . urlencode('No se pudo registrar el proveedor.'));
        }
        exit;
    }

    // 2. ACCIÓN: EDITAR PROVEEDOR
    if ($action === 'editar') {
        $id              = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $nombre_empresa  = trim($_POST['nombre_empresa'] ?? '');
        $nombre_contacto = trim($_POST['nombre_contacto'] ?? '');
        $telefono        = trim($_POST['telefono'] ?? '');

        if (!$id || empty($nombre_empresa)) {
            header('Location: ../index.php?v=proveedores&error=' . urlencode('Datos insuficientes para actualizar.'));
            exit;
        }

        $nombre_contacto = ($nombre_contacto === '') ? null : $nombre_contacto;
        $telefono        = ($telefono === '') ? null : $telefono;

        $resultado = $proveedorModel->actualizar($id, $nombre_empresa, $nombre_contacto, $telefono, 'activo');

        if ($resultado) {
            header('Location: ../index.php?v=proveedores&success=' . urlencode('Proveedor actualizado correctamente.'));
        } else {
            header('Location: ../index.php?v=proveedores&error=' . urlencode('No se pudo actualizar el proveedor.'));
        }
        exit;
    }

    // 3. ACCIÓN: BORRADO LÓGICO
    if ($action === 'eliminar') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

        if (!$id) {
            header('Location: ../index.php?v=proveedores&error=' . urlencode('ID de proveedor inválido.'));
            exit;
        }

        $resultado = $proveedorModel->eliminarLogico($id);

        if ($resultado) {
            header('Location: ../index.php?v=proveedores&success=' . urlencode('Proveedor eliminado correctamente.'));
        } else {
            header('Location: ../index.php?v=proveedores&error=' . urlencode('No se pudo eliminar el proveedor.'));
        }
        exit;
    }
}

// Si entran directo al controlador sin POST, los regresamos por seguridad
header('Location: ../index.php?v=proveedores');
exit;
