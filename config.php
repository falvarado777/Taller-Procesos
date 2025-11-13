<?php
function cargarRutas($archivoConfig = 'config.txt') {
    $rutas = [];

    if (!file_exists($archivoConfig)) {
        die("Error: No se encontró el archivo de configuración ($archivoConfig)");
    }

    $lineas = file($archivoConfig, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lineas as $linea) {
        $linea = trim($linea);
        list($clave, $valor) = explode('=', $linea, 2);
        $rutas[trim($clave)] = trim($valor);
    }

    return $rutas;
}
?>
