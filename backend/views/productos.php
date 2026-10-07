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
    <!-- Bootstrap 5.3.3 (CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<header class="bg-dark text-white py-3 mb-4">
    <div class="container">
        <h1 class="h3 mb-0">Mueblería CrediCauca S.A.S.</h1>
        <p class="mb-0 small opacity-75">Módulo de gestión de productos</p>
    </div>
</header>

<div class="container my-4">

    <?php if ($mensaje !== ''): ?>
        <div class="alert alert-<?php echo ($tipoMsg === 'exito') ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
            <?php echo e($mensaje); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>

    <!-- FORMULARIO CREAR / ACTUALIZAR (POST) -->
    <div class="card card-body shadow-sm mb-4">
        <h2 class="h5 text-dark border-bottom pb-2 mb-3"><?php echo $modoEdicion ? "Editar producto #" . e($productoEditar['id_producto']) : "Registrar nuevo producto"; ?></h2>

        <form action="productos.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="accion" value="<?php echo $modoEdicion ? 'actualizar' : 'crear'; ?>">
            <?php if ($modoEdicion): ?>
                <input type="hidden" name="id_producto" value="<?php echo (int)$productoEditar['id_producto']; ?>">
            <?php endif; ?>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="nombre">Nombre *</label>
                    <input type="text" class="form-control" id="nombre" name="nombre" maxlength="100" required
                           value="<?php echo $modoEdicion ? e($productoEditar['nombre']) : ''; ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="categoria">Categoría</label>
                    <input type="text" class="form-control" id="categoria" name="categoria" maxlength="100"
                           value="<?php echo $modoEdicion ? e($productoEditar['categoria']) : ''; ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="precio">Precio *</label>
                    <input type="number" class="form-control" id="precio" name="precio" step="0.01" min="0" required
                           value="<?php echo $modoEdicion ? e($productoEditar['precio']) : ''; ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="stock">Stock *</label>
                    <input type="number" class="form-control" id="stock" name="stock" min="0" step="1" required
                           value="<?php echo $modoEdicion ? e($productoEditar['stock']) : ''; ?>">
                </div>
                <div class="col-12">
                    <label class="form-label" for="descripcion">Descripción</label>
                    <textarea class="form-control" rows="3" id="descripcion" name="descripcion"><?php echo $modoEdicion ? e($productoEditar['descripcion']) : ''; ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="imagen_archivo">Subir imagen (jpg, png, gif, webp)</label>
                    <input type="file" class="form-control" id="imagen_archivo" name="imagen_archivo" accept="image/*">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="imagen">o ruta / URL de la imagen</label>
                    <input type="text" class="form-control" id="imagen" name="imagen" maxlength="255" placeholder="uploads/mesa.jpg"
                           value="<?php echo $modoEdicion ? e($productoEditar['imagen']) : ''; ?>">
                    <?php if ($modoEdicion && !empty($productoEditar['imagen'])): ?>
                        <div class="mt-2">
                            <img src="<?php echo e($productoEditar['imagen']); ?>" alt="Imagen actual" class="img-thumbnail" style="width:90px;">
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <button type="submit" class="btn btn-success mt-3">
                <?php echo $modoEdicion ? "Actualizar producto" : "Guardar producto"; ?>
            </button>
            <?php if ($modoEdicion): ?>
                <a href="productos.php" class="btn btn-secondary mt-3">Cancelar</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- LISTADO / CONSULTA (GET) -->
    <div class="card card-body shadow-sm mb-4">
        <h2 class="h5 text-dark border-bottom pb-2 mb-3">Listado de productos</h2>

        <form action="productos.php" method="GET" class="input-group mb-3">
            <input type="text" class="form-control" name="buscar" placeholder="Buscar por nombre, categoría o descripción..."
                   value="<?php echo e($buscar); ?>">
            <button type="submit" class="btn btn-primary">Buscar</button>
            <?php if ($buscar !== ''): ?>
                <a href="productos.php" class="btn btn-secondary">Limpiar</a>
            <?php endif; ?>
        </form>

        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead class="table-dark">
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
                                    <img src="<?php echo e($fila['imagen']); ?>" alt="<?php echo e($fila['nombre']); ?>" class="img-thumbnail" style="width:60px;height:60px;object-fit:cover;">
                                <?php else: ?>
                                    <span class="text-muted small">Sin imagen</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo e($fila['nombre']); ?></td>
                            <td><?php echo e($fila['categoria']); ?></td>
                            <td><?php echo e($fila['descripcion']); ?></td>
                            <td>$<?php echo number_format((float)$fila['precio'], 2, ',', '.'); ?></td>
                            <td><?php echo (int)$fila['stock']; ?></td>
                            <td class="text-nowrap">
                                <a class="btn btn-primary btn-sm" href="productos.php?editar=<?php echo (int)$fila['id_producto']; ?>">Editar</a>
                                <a class="btn btn-danger btn-sm"
                                   href="productos.php?eliminar=<?php echo (int)$fila['id_producto']; ?>"
                                   onclick="return confirm('¿Seguro que desea eliminar este producto?');">Eliminar</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No hay productos para mostrar.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Bootstrap 5.3.3 JS (CDN) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
$conn->close();
?>