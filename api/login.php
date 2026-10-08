<?php

include("../backend/config/conexion.php");

/*
Servicio web de autenticación
Mueblería CrediCauca S.A.S.
*/

header("Content-Type: application/json");

$usuario = $_POST['usuario'] ?? '';
$password = $_POST['password'] ?? '';

$sql = "SELECT * FROM usuarios
WHERE usuario='$usuario'";

$resultado = mysqli_query(
    $conexion,
    $sql
);

if(mysqli_num_rows($resultado)==0)
{
    echo json_encode([
        "estado"=>"error",
        "mensaje"=>"Usuario no encontrado"
    ]);
    exit;
}

$usuarioBD = mysqli_fetch_assoc(
    $resultado
);

if(
    password_verify(
        $password,
        $usuarioBD['password']
    )
)
{
    echo json_encode([
        "estado"=>"ok",
        "mensaje"=>"Autenticación satisfactoria"
    ]);
}
else
{
    echo json_encode([
        "estado"=>"error",
        "mensaje"=>"Error de autenticación"
    ]);
}