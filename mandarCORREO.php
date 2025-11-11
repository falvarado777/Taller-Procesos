<?php
require_once "tlogica.php";

$logica = new Logica();
$rutaJson = __DIR__ . "/procesos.json";

if (!is_file($rutaJson)) {
    exit("No se pudo localizar el archivo procesos.json");
}

$contenido = file_get_contents($rutaJson);
$infoLote = json_decode($contenido, true);

if (count($infoLote["archivos"]) === 0 || $infoLote === null) {
    exit("No hay información disponible en el archivo JSON");
}
echo "<h2>Procesando envíos de correo</h2>";

foreach ($infoLote["archivos"] as $registro) {

    $archivoName = $registro["archivo"]    ?? "Archivo desconocido";
    $cantIns     = $registro["insertados"] ?? 0;
    $cantErr     = $registro["errores"]    ?? 0;
    $cantTot     = $registro["total"]      ?? 0;
    $exito       = $registro["exito"]      ?? false;

    echo "<hr>";
    echo "<b>Generando envío para:</b> {$archivoName}<br>";
    $logica->enviarCorreo($archivoName, $cantIns, $cantErr, $cantTot, $exito);
}

echo "<hr><b>Finalizó el envío de todos los correos.</b><br>";
?>

