<?php
require_once "tlogica.php";
require_once "config.php";

$cfg = cargarRutas();
$logica = new Logica();

if (!is_file($cfg["json_proceso"])) {
    exit("No se pudo localizar el archivo procesos.json.");
}

$infoProceso = json_decode(file_get_contents($cfg["json_proceso"]), true);
$fechaLote = $infoProceso["hora_lote"];

if (count($infoProceso["archivos"]) === 0) {
    exit("No hay información disponible en el archivo JSON");
}

foreach ($infoProceso["archivos"] as $registro) {

    $archivo = $registro["archivo"]     ?? "Archivo desconocido";
    $okIns   = $registro["insertados"] ?? 0;
    $okErr   = $registro["errores"]    ?? 0;
    $okTot   = $registro["total"]      ?? 0;
    $fecha   = $registro["fecha"]      ?? 0;
    $exito   = $registro["exito"]      ?? false;
    $tiempo  = $registro["tiempo"]     ?? 0;
    $memoria = $registro["memoria"]    ?? 0;

    if ($exito) {
        $mensaje =
            "Carga EXITOSA: {$archivo}\n" .
            "Total: {$okTot} | Insertados: {$okIns} | Errores: {$okErr}\n" .
            "Fecha del proceso: {$fecha}\n" .
            "Duración: {$tiempo}s | Memoria usada: {$memoria}MB\n" .
            "Hora: " . date("Y-m-d H:i:s") . " (UTC-5)";
    }else {
        $mensaje =
            "CARGA FALLIDA: {$archivo}\n" .
            "Total procesado: {$okTot}\n" .
            "Errores: {$okErr}\n" .
            "Fecha del proceso: {$fecha}\n" .
            "Duración: {$tiempo}s | Memoria usada: {$memoria}MB\n" .
            "La carga fue cancelada.\n" .
            "Hora: " . date("Y-m-d H:i:s") . " (UTC-5)";
    }

    $logica->enviarWHATSAPP($mensaje, $tiempo, $memoria);

    echo "SMS enviado correctamente para: {$archivo}<br>";
}
?>

