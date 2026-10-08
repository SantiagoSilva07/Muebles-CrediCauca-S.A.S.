<?php

include("../backend/config/conexion.php");

/*
Servicio web de registro
Mueblería CrediCauca S.A.S.
*/

header("Content-Type: application/json");

$usuario = $_POST['usuario'] ?? '';
$password = $_POST['password'] ?? '';

if ($usuario == '' || $password == '')
{
    echo json_encode([
        "estado" => "error",
        "mensaje" => "Debe enviar usuario y contraseña"
    ]);
    exit;
}

$passwordHash = password_hash(
    $password,
    PASSWORD_DEFAULT
);

$sql = "INSERT INTO usuarios
(usuario,password)
VALUES
('$usuario','$passwordHash')";

if(mysqli_query($conexion,$sql))
{
    echo json_encode([
        "estado" => "ok",
        "mensaje" => "Usuario registrado correctamente"
    ]);
}
else
{
    echo json_encode([
        "estado" => "error",
        "mensaje" => "No fue posible registrar el usuario"
    ]);
}