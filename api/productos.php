<?php

include("../backend/config/conexion.php");

/*
API Productos
Proyecto: Mueblería CrediCauca S.A.S.
Evidencia: AA5-EV03
*/

header("Content-Type: application/json");

$metodo = $_SERVER['REQUEST_METHOD'];

// CONSULTAR PRODUCTOS
if ($metodo === 'GET') {

    $sql = "SELECT * FROM productos";
    $resultado = mysqli_query($conexion, $sql);

    $productos = [];

    while ($fila = mysqli_fetch_assoc($resultado)) {
        $productos[] = $fila;
    }

    echo json_encode($productos);
    exit();
}

// REGISTRAR PRODUCTO
if ($metodo === 'POST') {

    $nombre = $_POST['nombre'] ?? '';
    $descripcion = $_POST['descripcion'] ?? '';
    $precio = $_POST['precio'] ?? 0;
    $stock = $_POST['stock'] ?? 0;
    $categoria = $_POST['categoria'] ?? '';

    if ($nombre == '') {

        echo json_encode([
            "estado" => "error",
            "mensaje" => "Debe enviar el nombre del producto"
        ]);

        exit();
    }

    $sql = "INSERT INTO productos
    (nombre, descripcion, precio, stock, categoria)
    VALUES
    ('$nombre','$descripcion','$precio','$stock','$categoria')";

    if (mysqli_query($conexion, $sql)) {

        echo json_encode([
            "estado" => "ok",
            "mensaje" => "Producto registrado correctamente"
        ]);
    } else {

        echo json_encode([
            "estado" => "error",
            "mensaje" => "No fue posible registrar el producto"
        ]);
    }

    exit();
}

echo json_encode([
    "estado" => "error",
    "mensaje" => "Método no permitido"
]);