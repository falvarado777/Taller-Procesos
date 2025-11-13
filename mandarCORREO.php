<?php
require_once "tlogica.php";
require_once "config.php";

$cfg = cargarRutas();
$logica = new Logica();

if (!is_file($cfg["json_proceso"])) {
    exit("No se pudo localizar el archivo procesos.json");
}

$infoLote = json_decode(file_get_contents($cfg["json_proceso"]), true);
if (empty($infoLote["archivos"])) exit("No hay información en el archivo JSON.");

echo "<h2>Procesando envíos de correo</h2>";

foreach ($infoLote["archivos"] as $registro) {

    $archivoName = $registro["archivo"]    ?? "Archivo desconocido";
    $cantIns     = $registro["insertados"] ?? 0;
    $cantErr     = $registro["errores"]    ?? 0;
    $cantTot     = $registro["total"]      ?? 0;
    $exito       = $registro["exito"]      ?? false;
    $tiempo      = $registro["tiempo"]     ?? 0;
    $memoria     = $registro["memoria"]    ?? 0;

    echo "<hr>";
    echo "<b>Generando envío para:</b> {$archivoName}<br>";
    $logica->enviarCorreo($archivoName, $cantIns, $cantErr, $cantTot, $exito, $tiempo, $memoria);
}

echo "<hr><b>Finalizó el envío de todos los correos.</b><br>";
?>

