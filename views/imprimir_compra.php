<?php
// views/imprimir_compra.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 1. Validamos el ID de la factura de compra enviado por URL
$compra_id = filter_var($_GET['compra_id'] ?? 0, FILTER_VALIDATE_INT);
if (!$compra_id || $compra_id <= 0) {
    die("🚨 Error: ID de factura de compra no válido o inexistente.");
}

// 2. Conectamos y extraemos los metadatos de la cabecera
require_once __DIR__ . '/../config/conexion.php';
$db = (new Conexion())->conectar();

$sqlC = "SELECT c.*, p.nombre_empresa as proveedor_nombre, u.nombre as usuario_nombre 
         FROM compras c
         INNER JOIN proveedores p ON c.proveedor_id = p.id
         INNER JOIN usuarios u ON c.usuario_id = u.id
         WHERE c.id = :id";
$stmtC = $db->prepare($sqlC);
$stmtC->execute(['id' => $compra_id]);
$compra = $stmtC->fetch(PDO::FETCH_ASSOC);

if (!$compra) {
    die("🚨 Error: La factura de compra #" . $compra_id . " no se encuentra en el archivo digital.");
}

// 3. Extraemos el desglose de ingredientes ingresados a bodega
$sqlD = "SELECT cd.*, p.nombre as producto_nombre, p.unidad_medida 
         FROM compra_detalles cd
         INNER JOIN productos p ON cd.producto_id = p.id
         WHERE cd.compra_id = :id ORDER BY p.nombre ASC";
$stmtD = $db->prepare($sqlD);
$stmtD->execute(['id' => $compra_id]);
$detalles = $stmtD->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Abasto_#<?php echo $compra['id']; ?></title>
    <style>
        body { font-family: 'Courier New', Courier, monospace; font-size: 13px; color: #000; width: 72mm; margin: 0; padding: 5px; background: #fff; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .bold { font-weight: bold; }
        .linea { border-top: 1px dashed #000; margin: 8px 0; }
        .tabla-items { width: 100%; border-collapse: collapse; font-size: 12px; margin-top: 5px; }
        .tabla-items td { padding: 3px 0; vertical-align: top; }
        @media print { 
            body { width: 100%; margin: 0; padding: 0; } 
            .no-print { display: none; } 
        }
    </style>
</head>
<body>

<div class="text-center bold" style="font-size: 15px;">🌿 JUNGLE PIZZA 🌿</div>
<div class="text-center bold">VALE DE RECEPCIÓN DE BODEGA</div>
<div class="linea"></div>

<div>
    <span class="bold">Registro ID:</span> #<?php echo $compra['id']; ?><br>
    <span class="bold">Fac. Proveedor:</span> <?php echo htmlspecialchars($compra['numero_factura']); ?><br>
    <span class="bold">Proveedor:</span> <?php echo htmlspecialchars($compra['proveedor_nombre']); ?><br>
    <span class="bold">Fecha Recibo:</span> <?php echo date('d/m/Y H:i', strtotime($compra['fecha_compra'])); ?><br>
    <span class="bold">Operador:</span> <?php echo htmlspecialchars($compra['usuario_nombre']); ?>
</div>

<div class="linea"></div>
<div class="bold" style="font-size: 11px;">DESGLOSE MATERIAS PRIMAS INGRESADAS:</div>

<table class="tabla-items">
    <?php foreach ($detalles as $item): ?>
        <tr>
            <td colspan="2" class="bold"><?php echo htmlspecialchars($item['producto_nombre']); ?></td>
        </tr>
        <tr>
            <td style="color: #444;">
                <!-- 🚀 CORREGIDO: Usamos floatval para limpiar los decimales en PHP -->
                <?php echo floatval($item['cantidad']); ?> <?php echo htmlspecialchars($item['unidad_medida']); ?> x C$ <?php echo number_format($item['precio_unitario'], 2); ?>
            </td>
            <td class="text-right bold" style="width: 70px;">
                C$<?php echo number_format($item['subtotal'], 2); ?>
            </td>
        </tr>
    <?php endforeach; ?>
</table>


<div class="linea"></div>

<table style="width: 100%; font-size: 14px;" class="bold">
    <tr>
        <td>TOTAL NETO GASTO:</td>
        <td class="text-right">C$ <?php echo number_format($compra['total'], 2); ?></td>
    </tr>
</table>

<?php if (!empty(trim($compra['observaciones']))): ?>
    <div class="linea"></div>
    <div style="font-size: 11px; font-style: italic;">
        <span class="bold">Nota contable:</span> <?php echo htmlspecialchars($compra['observaciones']); ?>
    </div>
<?php endif; ?>

<div class="linea" style="margin-top: 30px;"></div>
<div class="text-center" style="font-size: 11px;">Firma de Recibido Conforme<br>Encargado de Almacén</div>

<!-- 🚀 DISPARADOR AUTOMÁTICO DE IMPRESIÓN TERMICA -->
<script>
    window.onload = function() {
        window.print();
        // Cierra la pestaña secundaria automáticamente después de imprimir para no estorbar
        setTimeout(() => { window.close(); }, 500);
    }
</script>

</body>
</html>
