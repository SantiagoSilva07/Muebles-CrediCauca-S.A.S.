<?php

class Producto
{
    private $idProducto;
    private $nombre;
    private $descripcion;
    private $precio;
    private $stock;
    private $imagen;
    private $categoria;

    public function __construct(
        $nombre,
        $descripcion,
        $precio,
        $stock,
        $imagen,
        $categoria
    ) {
        $this->nombre = $nombre;
        $this->descripcion = $descripcion;
        $this->precio = $precio;
        $this->stock = $stock;
        $this->imagen = $imagen;
        $this->categoria = $categoria;
    }
}

?>