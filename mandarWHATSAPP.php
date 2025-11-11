<?php
require_once "tlogica.php";

$logica = new Logica();
$rutaJson = __DIR__ . "/procesos.json";

if (!is_file($rutaJson)) {
    exit("No se pudo localizar el archivo procesos.json.");
}

$contenido = file_get_contents($rutaJson);
$infoProceso = json_decode($contenido, true);
$fechaLote = $infoProceso["hora_lote"];

if (count($infoProceso["archivos"]) === 0) {
    exit("No hay información disponible en el archivo JSON");
}

foreach ($infoProceso["archivos"] as $registro) {

    $archivo = $registro["archivo"]     ?? "Archivo desconocido";
    $okIns   = $registro["insertados"] ?? 0;
    $okErr   = $registro["errores"]    ?? 0;
    $okTot   = $registro["total"]      ?? 0;
    $exito   = $registro["exito"]      ?? false;

    if ($exito) {
        $mensaje =
            "Carga EXITOSA: {$archivo}\n" .
            "Total: {$okTot} | Insertados: {$okIns} | Errores: {$okErr}\n" .
            "Hora: " . date("Y-m-d H:i:s") . " (UTC-5)";
    }else {
        $mensaje =
            "CARGA FALLIDA: {$archivo}\n" .
            "Total procesado: {$okTot}\n" .
            "Insertados: 0\n" .
            "Errores: {$okErr}\n" .
            "La carga fue cancelada.\n" .
            "Hora: " . date("Y-m-d H:i:s") . " (UTC-5)";
    }

    $textoSms =
        "Carga finalizada: {$archivo}\n" .
        "Total: {$okTot} | Insertados: {$okIns} | Errores: {$okErr}\n" .
        "Hora del proceso: " . date("Y-m-d H:i:s") . " (UTC-5)";

    $logica->enviarWHATSAPP($mensaje);

    echo "SMS enviado correctamente para: {$archivo}<br>";
}
?>

