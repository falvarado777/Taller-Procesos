<?php
session_start();
require_once "tlogica.php";
require_once "config.php";

$cfg = cargarRutas();
$logica = new Logica();
$inicioGeneral = microtime(true);
$dirCsv = $cfg["archivos_csv"];
$listadoCsv = glob($dirCsv . "/*.csv");

echo "<h2>Proceso de importación Batch</h2>";

if (!$listadoCsv || count($listadoCsv) === 0) {
    echo "<p>No hay archivos CSV en la carpeta configurada: {$cfg['archivos_csv']}.</p>";
    exit;
}

$registrosProcesados = [];
$timestampLote = date("Y-m-d H:i:s");

foreach ($listadoCsv as $rutaCsv) {

    $nombreArchivo = basename($rutaCsv);
    $inicioArchivo = microtime(true);

    echo "<hr>";
    echo "<h3>Procesando archivo: {$nombreArchivo}</h3>";

    $info = $logica->cargarInformacion($rutaCsv);
    $exito = ($info["errores"] === 0);

    $finArchivo = microtime(true);

    $tiempoArchivo = round($finArchivo - $inicioArchivo, 3);
    $memoriaConsumida = round(memory_get_peak_usage(true) / 1024 / 1024, 2);

    echo "<p>Tiempo de procesamiento: {$tiempoArchivo} segundos.</p>";
    echo "<p>Memoria consumida: {$memoriaConsumida} MB.</p>";
    if ($exito) {
        

        echo "<p>El archivo se procesó de forma correcta.</p>";
        echo "<ul>";
        echo "<li><b>Total:</b> {$info['total']}</li>";
        echo "<li><b>Insertados:</b> {$info['insertados']}</li>";
        echo "<li><b>Errores:</b> {$info['errores']}</li>";
        echo "<li><b>Duración:</b> {$tiempoArchivo} segundos</li>";
        echo "<li><b>Memoria:</b> {$memoriaConsumida}MB</li>";
        echo "</ul>";

        $registrosProcesados[] = [
            "archivo"    => $nombreArchivo,
            "insertados" => $info["insertados"],
            "errores"    => $info["errores"],
            "total"      => $info["total"],
            "fecha"      => $timestampLote,
            "exito"      => $exito,
            "tiempo"     => $tiempoArchivo,
            "memoria"    => $memoriaConsumida
        ];

    } else {

        echo "<p>Error al procesar.</p>";
        echo "<ul>";
        echo "<li><b>Total:</b> {$info['total']}</li>";
        echo "<li><b>Datos correctos:</b> {$info['insertados']}</li>";
        echo "<li><b>Errores:</b> {$info['errores']}</li>";
        echo "<li><b>Duración:</b> {$tiempoArchivo} segundos</li>";
        echo "<li><b>Memoria:</b> {$memoriaConsumida}MB</li>";
        echo "</ul>";
        echo "<p>Revise logs para encontrar el/los dato/s que causa el error</p>";
        $registrosProcesados[] = [
            "archivo"         => $nombreArchivo,
            "datos correctos" => $info["insertados"],
            "errores"         => $info["errores"],
            "total"           => $info["total"],
            "fecha"           => $timestampLote,
            "exito"           => $exito,
            "tiempo"     => $tiempoArchivo,
            "memoria"    => $memoriaConsumida
        ];
    }
}

if (!empty($registrosProcesados)) {

    $jsonLote = [
        "hora_lote" => $timestampLote,
        "archivos"  => $registrosProcesados
    ];

    file_put_contents($cfg["json_proceso"], json_encode($jsonLote, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    echo "<p>Información del proceso guardada. Total de archivos: " . count($registrosProcesados) . "</p>";
}
$finGeneral = microtime(true);
$duracionTotal = round($finGeneral - $inicioGeneral, 2);
echo "<hr><b>Proceso finalizado.</b><br>";
echo "<b>Tiempo total:</b> {$duracionTotal} segundos.<br>";
?>

