<?php
/**
 * GA7-220501096-AA2-EV01 - Codificación de módulos del software
 * Proyecto: Mueblería CrediCauca S.A.S.
 * Módulo: CRUD de productos (PHP + MySQLi + MySQL, XAMPP)
 * Base de datos: muebles_credicauca | Tabla: productos
 */

// ---------------------------------------------------------------
// 1. CONEXIÓN A LA BASE DE DATOS
// ---------------------------------------------------------------
$host = "localhost";
$user = "root";
$pass = "";
$db   = "muebles_credicauca";

mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Error de conexión a la base de datos: " . htmlspecialchars($conn->connect_error));
}
$conn->set_charset("utf8mb4");

// Carpeta para imágenes subidas
$carpetaImg = "uploads/";
if (!is_dir($carpetaImg)) {
    @mkdir($carpetaImg, 0777, true);
}

// ---------------------------------------------------------------
// 2. FUNCIONES AUXILIARES
// ---------------------------------------------------------------
function e($valor)
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

/** Sube la imagen (si se envió) y devuelve la ruta; si no, devuelve $actual */
function procesarImagen($actual, $carpeta, &$error)
{
    if (!isset($_FILES['imagen_archivo']) || $_FILES['imagen_archivo']['error'] === UPLOAD_ERR_NO_FILE) {
        return $actual;
    }
    if ($_FILES['imagen_archivo']['error'] !== UPLOAD_ERR_OK) {
        $error = "No se pudo subir la imagen.";
        return $actual;
    }
    $ext = strtolower(pathinfo($_FILES['imagen_archivo']['name'], PATHINFO_EXTENSION));
    $permitidas = array("jpg", "jpeg", "png", "gif", "webp");
    if (!in_array($ext, $permitidas)) {
        $error = "Formato de imagen no permitido (use jpg, png, gif o webp).";
        return $actual;
    }
    $nombre  = uniqid("prod_", true) . "." . $ext;
    $destino = $carpeta . $nombre;
    if (move_uploaded_file($_FILES['imagen_archivo']['tmp_name'], $destino)) {
        return $destino;
    }
    $error = "No se pudo guardar la imagen en el servidor.";
    return $actual;
}

function redirigir($tipo, $texto)
{
    header("Location: productos.php?tipo=" . urlencode($tipo) . "&msg=" . urlencode($texto));
    exit;
}

// ---------------------------------------------------------------
// 3. LÓGICA CRUD
// ---------------------------------------------------------------

// ----- CREAR (POST) -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'crear') {
    $nombre      = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $precio      = (float)($_POST['precio'] ?? 0);
    $stock       = (int)($_POST['stock'] ?? 0);
    $categoria   = trim($_POST['categoria'] ?? '');
    $errorImg    = "";
    $imagen      = procesarImagen(trim($_POST['imagen'] ?? ''), $carpetaImg, $errorImg);

    if ($nombre === '' || $precio < 0 || $stock < 0) {
        redirigir("error", "Complete el nombre y use valores no negativos en precio y stock.");
    }
    if ($errorImg !== "") {
        redirigir("error", $errorImg);
    }

    $stmt = $conn->prepare("INSERT INTO productos (nombre, descripcion, precio, stock, imagen, categoria) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssdiss", $nombre, $descripcion, $precio, $stock, $imagen, $categoria);
    if ($stmt->execute()) {
        $stmt->close();
        redirigir("exito", "Producto creado correctamente.");
    }
    $msg = "Error al crear el producto: " . $stmt->error;
    $stmt->close();
    redirigir("error", $msg);
}

// ----- ACTUALIZAR (POST) -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'actualizar') {
    $id          = (int)($_POST['id_producto'] ?? 0);
    $nombre      = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $precio      = (float)($_POST['precio'] ?? 0);
    $stock       = (int)($_POST['stock'] ?? 0);
    $categoria   = trim($_POST['categoria'] ?? '');
    $errorImg    = "";
    $imagen      = procesarImagen(trim($_POST['imagen'] ?? ''), $carpetaImg, $errorImg);

    if ($id <= 0 || $nombre === '' || $precio < 0 || $stock < 0) {
        redirigir("error", "Datos inválidos para actualizar el producto.");
    }
    if ($errorImg !== "") {
        redirigir("error", $errorImg);
    }

    $stmt = $conn->prepare("UPDATE productos SET nombre = ?, descripcion = ?, precio = ?, stock = ?, imagen = ?, categoria = ? WHERE id_producto = ?");
    $stmt->bind_param("ssdissi", $nombre, $descripcion, $precio, $stock, $imagen, $categoria, $id);
    if ($stmt->execute()) {
        $stmt->close();
        redirigir("exito", "Producto actualizado correctamente.");
    }
    $msg = "Error al actualizar el producto: " . $stmt->error;
    $stmt->close();
    redirigir("error", $msg);
}

// ----- ELIMINAR (GET) -----
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['eliminar'])) {
    $id = (int)$_GET['eliminar'];
    if ($id > 0) {
        $stmt = $conn->prepare("DELETE FROM productos WHERE id_producto = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $stmt->close();
            redirigir("exito", "Producto eliminado correctamente.");
        }
        $stmt->close();
    }
    redirigir("error", "No se pudo eliminar el producto.");
}

// ----- CARGAR PRODUCTO PARA EDITAR (GET) -----
$productoEditar = null;
if (isset($_GET['editar'])) {
    $idEditar = (int)$_GET['editar'];
    $stmt = $conn->prepare("SELECT id_producto, nombre, descripcion, precio, stock, imagen, categoria FROM productos WHERE id_producto = ?");
    $stmt->bind_param("i", $idEditar);
    $stmt->execute();
    $res = $stmt->get_result();
    $productoEditar = $res->fetch_assoc();
    $stmt->close();
    if (!$productoEditar) {
        redirigir("error", "El producto que intenta editar no existe.");
    }
}

// ----- CONSULTAR / BUSCAR (GET) -----
$buscar = trim($_GET['buscar'] ?? '');
if ($buscar !== '') {
    $like = "%" . $buscar . "%";
    $stmt = $conn->prepare("SELECT id_producto, nombre, descripcion, precio, stock, imagen, categoria FROM productos WHERE nombre LIKE ? OR categoria LIKE ? OR descripcion LIKE ? ORDER BY id_producto DESC");
    $stmt->bind_param("sss", $like, $like, $like);
    $stmt->execute();
    $resultado = $stmt->get_result();
} else {
    $resultado = $conn->query("SELECT id_producto, nombre, descripcion, precio, stock, imagen, categoria FROM productos ORDER BY id_producto DESC");
}

// Mensajes
$mensaje = $_GET['msg'] ?? '';
$tipoMsg = (($_GET['tipo'] ?? '') === 'exito') ? 'exito' : 'error';

$modoEdicion = ($productoEditar !== null);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productos - Mueblería CrediCauca S.A.S.</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; background: #f4f1ec; margin: 0; color: #333; }
        header { background: #5a3825; color: #fff; padding: 18px 30px; }
        header h1 { margin: 0; font-size: 24px; }
        header p { margin: 4px 0 0; font-size: 14px; opacity: .85; }
        .contenedor { max-width: 1150px; margin: 25px auto; padding: 0 15px; }
        .tarjeta { background: #fff; border-radius: 8px; padding: 22px; margin-bottom: 25px; box-shadow: 0 2px 6px rgba(0,0,0,.1); }
        .tarjeta h2 { margin-top: 0; color: #5a3825; border-bottom: 2px solid #e6dccf; padding-bottom: 8px; }
        .mensaje { padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-weight: bold; }
        .mensaje.exito { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .mensaje.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; }
        .grid .completo { grid-column: 1 / -1; }
        label { display: block; font-weight: bold; margin-bottom: 5px; font-size: 14px; }
        input[type=text], input[type=number], input[type=file], textarea {
            width: 100%; padding: 9px; border: 1px solid #bbb; border-radius: 5px; font-size: 14px; font-family: inherit;
        }
        textarea { resize: vertical; min-height: 80px; }
        .btn { display: inline-block; padding: 9px 18px; border: none; border-radius: 5px; cursor: pointer;
               font-size: 14px; text-decoration: none; color: #fff; }
        .btn-guardar { background: #2e7d32; }
        .btn-cancelar { background: #757575; }
        .btn-editar { background: #1565c0; padding: 6px 12px; }
        .btn-eliminar { background: #c62828; padding: 6px 12px; }
        .btn-buscar { background: #5a3825; }
        .btn:hover { opacity: .88; }
        .barra-busqueda { display: flex; gap: 8px; margin-bottom: 15px; }
        .barra-busqueda input { flex: 1; }
        .tabla-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th { background: #5a3825; color: #fff; padding: 10px; text-align: left; }
        td { padding: 9px 10px; border-bottom: 1px solid #e2e2e2; vertical-align: middle; }
        tr:hover td { background: #faf6f0; }
        td img { width: 60px; height: 60px; object-fit: cover; border-radius: 5px; border: 1px solid #ddd; }
        .sin-imagen { color: #999; font-size: 12px; }
        .vacio { text-align: center; padding: 25px; color: #777; }
        .imagen-actual { margin-top: 8px; }
        .imagen-actual img { width: 90px; border-radius: 5px; border: 1px solid #ddd; }
        @media (max-width: 700px) { .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<header>
    <h1>Mueblería CrediCauca S.A.S.</h1>
    <p>Módulo de gestión de productos</p>
</header>

<div class="contenedor">

    <?php if ($mensaje !== ''): ?>
        <div class="mensaje <?php echo e($tipoMsg); ?>"><?php echo e($mensaje); ?></div>
    <?php endif; ?>

    <!-- FORMULARIO CREAR / ACTUALIZAR (POST) -->
    <div class="tarjeta">
        <h2><?php echo $modoEdicion ? "Editar producto #" . e($productoEditar['id_producto']) : "Registrar nuevo producto"; ?></h2>

        <form action="productos.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="accion" value="<?php echo $modoEdicion ? 'actualizar' : 'crear'; ?>">
            <?php if ($modoEdicion): ?>
                <input type="hidden" name="id_producto" value="<?php echo (int)$productoEditar['id_producto']; ?>">
            <?php endif; ?>

            <div class="grid">
                <div>
                    <label for="nombre">Nombre *</label>
                    <input type="text" id="nombre" name="nombre" maxlength="100" required
                           value="<?php echo $modoEdicion ? e($productoEditar['nombre']) : ''; ?>">
                </div>
                <div>
                    <label for="categoria">Categoría</label>
                    <input type="text" id="categoria" name="categoria" maxlength="100"
                           value="<?php echo $modoEdicion ? e($productoEditar['categoria']) : ''; ?>">
                </div>
                <div>
                    <label for="precio">Precio *</label>
                    <input type="number" id="precio" name="precio" step="0.01" min="0" required
                           value="<?php echo $modoEdicion ? e($productoEditar['precio']) : ''; ?>">
                </div>
                <div>
                    <label for="stock">Stock *</label>
                    <input type="number" id="stock" name="stock" min="0" step="1" required
                           value="<?php echo $modoEdicion ? e($productoEditar['stock']) : ''; ?>">
                </div>
                <div class="completo">
                    <label for="descripcion">Descripción</label>
                    <textarea id="descripcion" name="descripcion"><?php echo $modoEdicion ? e($productoEditar['descripcion']) : ''; ?></textarea>
                </div>
                <div>
                    <label for="imagen_archivo">Subir imagen (jpg, png, gif, webp)</label>
                    <input type="file" id="imagen_archivo" name="imagen_archivo" accept="image/*">
                </div>
                <div>
                    <label for="imagen">o ruta / URL de la imagen</label>
                    <input type="text" id="imagen" name="imagen" maxlength="255" placeholder="uploads/mesa.jpg"
                           value="<?php echo $modoEdicion ? e($productoEditar['imagen']) : ''; ?>">
                    <?php if ($modoEdicion && !empty($productoEditar['imagen'])): ?>
                        <div class="imagen-actual">
                            <img src="<?php echo e($productoEditar['imagen']); ?>" alt="Imagen actual">
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <br>
            <button type="submit" class="btn btn-guardar">
                <?php echo $modoEdicion ? "Actualizar producto" : "Guardar producto"; ?>
            </button>
            <?php if ($modoEdicion): ?>
                <a href="productos.php" class="btn btn-cancelar">Cancelar</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- LISTADO / CONSULTA (GET) -->
    <div class="tarjeta">
        <h2>Listado de productos</h2>

        <form action="productos.php" method="GET" class="barra-busqueda">
            <input type="text" name="buscar" placeholder="Buscar por nombre, categoría o descripción..."
                   value="<?php echo e($buscar); ?>">
            <button type="submit" class="btn btn-buscar">Buscar</button>
            <?php if ($buscar !== ''): ?>
                <a href="productos.php" class="btn btn-cancelar">Limpiar</a>
            <?php endif; ?>
        </form>

        <div class="tabla-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Imagen</th>
                        <th>Nombre</th>
                        <th>Categoría</th>
                        <th>Descripción</th>
                        <th>Precio</th>
                        <th>Stock</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($resultado && $resultado->num_rows > 0): ?>
                    <?php while ($fila = $resultado->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo (int)$fila['id_producto']; ?></td>
                            <td>
                                <?php if (!empty($fila['imagen'])): ?>
                                    <img src="<?php echo e($fila['imagen']); ?>" alt="<?php echo e($fila['nombre']); ?>">
                                <?php else: ?>
                                    <span class="sin-imagen">Sin imagen</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo e($fila['nombre']); ?></td>
                            <td><?php echo e($fila['categoria']); ?></td>
                            <td><?php echo e($fila['descripcion']); ?></td>
                            <td>$<?php echo number_format((float)$fila['precio'], 2, ',', '.'); ?></td>
                            <td><?php echo (int)$fila['stock']; ?></td>
                            <td>
                                <a class="btn btn-editar" href="productos.php?editar=<?php echo (int)$fila['id_producto']; ?>">Editar</a>
                                <a class="btn btn-eliminar"
                                   href="productos.php?eliminar=<?php echo (int)$fila['id_producto']; ?>"
                                   onclick="return confirm('¿Seguro que desea eliminar este producto?');">Eliminar</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="vacio">No hay productos para mostrar.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

</body>
</html>
<?php
$conn->close();
?>