<?php
session_start();
require_once "tlogica.php";

$logica = new Logica();
$inicio = microtime(true);
$dirCsv = __DIR__ . "/ArchivosDatos";
$listadoCsv = glob($dirCsv . "/*.csv");

echo "<h2>Proceso de importación Batch</h2>";

if (!$listadoCsv || count($listadoCsv) === 0) {
    echo "<p>No hay archivos .csv disponibles en la carpeta ArchivosDatos.</p>";
    exit;
}

$registrosProcesados = [];
$timestampLote = date("Y-m-d H:i:s");

foreach ($listadoCsv as $rutaCsv) {

    $nombreArchivo = basename($rutaCsv);

    echo "<hr>";
    echo "<h3>Procesando archivo: {$nombreArchivo}</h3>";

    $info = $logica->cargarInformacion($rutaCsv);
    $exito = ($info["errores"] === 0);
    if ($exito) {
        

        echo "<p>El archivo se procesó de forma correcta.</p>";
        echo "<ul>";
        echo "<li><b>Total:</b> {$info['total']}</li>";
        echo "<li><b>Insertados:</b> {$info['insertados']}</li>";
        echo "<li><b>Errores:</b> {$info['errores']}</li>";
        echo "</ul>";

        $registrosProcesados[] = [
            "archivo"    => $nombreArchivo,
            "insertados" => $info["insertados"],
            "errores"    => $info["errores"],
            "total"      => $info["total"],
            "fecha"      => $timestampLote,
            "exito"      => $exito
        ];

    } else {

        echo "<p>Error al procesar.</p>";
        echo "<ul>";
        echo "<li><b>Total:</b> {$info['total']}</li>";
        echo "<li><b>Datos correctos:</b> {$info['insertados']}</li>";
        echo "<li><b>Errores:</b> {$info['errores']}</li>";
        echo "</ul>";
        echo "<p>Revise logs para encontrar el/los dato/s que causa el error</p>";
        $registrosProcesados[] = [
            "archivo"         => $nombreArchivo,
            "datos correctos" => $info["insertados"],
            "errores"         => $info["errores"],
            "total"           => $info["total"],
            "fecha"           => $timestampLote,
            "exito"           => $exito
        ];
    }
}

if (!empty($registrosProcesados)) {

    $jsonLote = [
        "hora_lote" => $timestampLote,
        "archivos"  => $registrosProcesados
    ];

    $rutaJson = __DIR__ . "/procesos.json";
    file_put_contents($rutaJson, json_encode($jsonLote, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    echo "<p>Información del proceso guardada. Total de archivos: " . count($registrosProcesados) . "</p>";
}
$fin = microtime(true);
$duracion = $fin - $inicio;
$segundos = round($duracion, 2);
$minutos = round($duracion / 60, 2);
echo "<hr><b>Proceso finalizado.</b><br>";
echo "<b>Tiempo total:</b> {$segundos} segundos ({$minutos} minutos).<br>";
?>

