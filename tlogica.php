<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once 'db.class.php';
require_once 'config.php';
require_once __DIR__ . '/lib/PHPMailer/Exception.php';
require_once __DIR__ . '/lib/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/lib/PHPMailer/SMTP.php';
date_default_timezone_set('Etc/GMT+5');
class Logica {
    private $db;
    private $cfg;

    public function __construct() {
        $this->db = new Database();
        $this->cfg = cargarRutas();
    }
    public function cargarInformacion($rutaArchivo) {
    if (!file_exists($rutaArchivo)) {
        return [
            "ok" => false,
            "msg" => "El archivo no existe: $rutaArchivo"
        ];
    }

    $archivo = fopen($rutaArchivo, "r");
    if (!$archivo) {
        return [
            "ok" => false,
            "msg" => "No se pudo abrir el archivo: $rutaArchivo"
        ];
    }

    $insertados = 0;
    $errores = 0;
    $total = 0;
    $erroresDetalle = [];
    $conn = $this->db->connect();
    $conn->beginTransaction();
    $validUnidades = $conn->query("SELECT id FROM unidad")->fetchAll(PDO::FETCH_COLUMN);
    $validCategorias = $conn->query("SELECT id FROM categoria")->fetchAll(PDO::FETCH_COLUMN);



    $cabecera = fgetcsv($archivo);
    if ($cabecera === false) {
        fclose($archivo);
        return [
            "ok" => false,
            "msg" => "El archivo está vacío o no tiene cabecera"
        ];
    }

    $expectedCols = 15;
    if (count($cabecera) < $expectedCols) {
        fclose($archivo);
        return [
            "ok" => false,
            "msg" => "La cabecera no tiene las columnas esperadas. Se esperaban $expectedCols columnas."
        ];
    }

    $regexCodigo = '/^PROD\d{6}$/';   
    $regexNombre = '/^NOM_PROD\d{6}$/';
    $regexDescr  = '/^DES_PROD\d{6}$/';

    $logDir = $this->cfg["log_dir"];
    if (!file_exists($logDir)) {
        mkdir($logDir, 0777, true);
    }
    $logFile = null;
    $logPath = "";

    $sql = "INSERT INTO elemento (
            codigo_elemnto, nmbre_elemnto, dscrpcion_elemnto, ctgria_elemnto, und_elemnto,
            exstncia_elemnto, bdga_elemnto, precio_venta_ac, precio_venta_an,
            costo_venta, mrgen_utldad, tiene_iva, stock_minimo, stock_maximo, estado
        ) VALUES (
            :codigo, :nombre, :descripcion, :categoria, :unidad,
            :existencia, :bodega, :precio_ac, :precio_an,
            :costo, :margen, :tiene_iva, :stock_min, :stock_max, :estado
        )";
    $stmtInsert = $conn->prepare($sql);

    while (($fila = fgetcsv($archivo)) !== false) {
        $total++;
        if (count($fila) < $expectedCols) {
            $errores++;
            $erroresDetalle[] = "Línea $total: columnas insuficientes (" . count($fila) . " encontradas)";
            continue;
        }
    
    if (count($fila) === 16) {
        array_shift($fila);
    }

        $codigo     = trim($fila[0]);
        $nombre     = trim($fila[1]);
        $descripcion= trim($fila[2]);
        $categoria  = is_numeric($fila[3]) ? intval($fila[3]) : null;
        $unidad     = is_numeric($fila[4]) ? intval($fila[4]) : null;
        $existencia = is_numeric($fila[5]) ? intval($fila[5]) : null;
        $bodega     = is_numeric($fila[6]) ? intval($fila[6]) : null;
        $precio_ac  = is_numeric($fila[7]) ? floatval($fila[7]) : null;
        $precio_an  = is_numeric($fila[8]) ? floatval($fila[8]) : null;
        $costo      = is_numeric($fila[9]) ? floatval($fila[9]) : null;
        $margen     = is_numeric($fila[10]) ? floatval($fila[10]) : null;
        $tiene_iva  = trim($fila[11]);
        $stock_min  = is_numeric($fila[12]) ? intval($fila[12]) : null;
        $stock_max  = is_numeric($fila[13]) ? intval($fila[13]) : null;
        $estado     = trim($fila[14]);

        $lineOk = true;
        $msgs = [];

        if (!preg_match($regexCodigo, $codigo)) {
            $lineOk = false;
             $msgs[] = "formato de código inválido ($codigo)";
        }
        if (!preg_match($regexNombre, $nombre)) {
            $lineOk = false;
            $msgs[] = "formato de nombre inválido ($nombre)";
        }
        if (!preg_match($regexDescr, $descripcion)) {
            $lineOk = false;
            $msgs[] = "formato de descripción inválido ($descripcion)";
        }

        if ($codigo === '') { $lineOk = false; $msgs[] = "codigo vacío"; }
        if ($nombre === '') { $lineOk = false; $msgs[] = "nombre vacío"; }

        if ($precio_ac === null || $precio_ac < 0) { $lineOk = false; $msgs[] = "precio_venta_ac inválido"; }
        if ($precio_an === null || $precio_an < 0) { $lineOk = false; $msgs[] = "precio_venta_an inválido"; }
        if ($costo === null || $costo < 0) { $lineOk = false; $msgs[] = "costo_venta inválido"; }

        if ($existencia === null || $existencia < 0) { $lineOk = false; $msgs[] = "existencia inválida"; }
        if ($stock_min === null || $stock_min < 0) { $lineOk = false; $msgs[] = "stock_minimo inválido"; }
        if ($stock_max === null || $stock_max < 0) { $lineOk = false; $msgs[] = "stock_maximo inválido"; }

        if (!empty($validUnidades) && $unidad !== null && !in_array($unidad, $validUnidades, true)) {
            $lineOk = false;
            $msgs[] = "unidad no existe (id: $unidad)";
        }
        if (!empty($validCategorias) && $categoria !== null && !in_array($categoria, $validCategorias, true)) {
            $lineOk = false;
            $msgs[] = "categoria no existe (id: $categoria)";
        }

            if (!$lineOk) {
                $errores++;
                if ($logFile === null) {
                    $logPath = $logDir . '/errores_' . basename($rutaArchivo) . '_' . date('Ymd_His') . '.log';
                    $logFile = fopen($logPath, 'a');
                }
                $detalleLinea = "Archivo: " . basename($rutaArchivo) .
                    " | Línea $total | Errores: " . implode("; ", $msgs) .
                    " | Datos: " . implode(",", $fila) . "\n";
                fwrite($logFile, $detalleLinea);
                $erroresDetalle[] = "Línea $total: " . implode("; ", $msgs);
                continue;
            }

        try {
            $stmtInsert->execute([
                ":codigo" => $codigo,
                ":nombre" => $nombre,
                ":descripcion" => $descripcion,
                ":categoria" => $categoria,
                ":unidad" => $unidad,
                ":existencia" => $existencia,
                ":bodega" => $bodega,
                ":precio_ac" => $precio_ac,
                ":precio_an" => $precio_an,
                ":costo" => $costo,
                ":margen" => $margen,
                ":tiene_iva" => $tiene_iva,
                ":stock_min" => $stock_min,
                ":stock_max" => $stock_max,
                ":estado" => $estado,
            ]);
            $insertados++;
            }catch (PDOException $e) {
                $errores++;
                $msg = "Línea $total: error BD: " . $e->getMessage();
                fwrite($logFile, $msg . "\n");
                $erroresDetalle[] = $msg;
        }
    } 

    fclose($archivo);
    if ($logFile !== null) {
        fclose($logFile);
    }

    $detalle = "";
    if (!empty($erroresDetalle)) {
        $detalle = implode("\n", array_slice($erroresDetalle, 0, 2000));
    }

        try {
        $stmtAudit = $conn->prepare("INSERT INTO auditoria (nombre_archivo, fecha_carga, registros_insertados, registros_fallidos, total_registros, detalle_error) VALUES (:nombre, CONVERT_TZ(NOW(), '+00:00', '-05:00'), :ins, :fail, :total, :detalle)");
        $stmtAudit->execute([
            ":nombre" => basename($rutaArchivo),
            ":ins" => $insertados,
            ":fail" => $errores,
            ":total" => $total,
            ":detalle" => $detalle
        ]);
    } catch (PDOException $e) {
        $erroresDetalle[] = "Error al registrar auditoría: " . $e->getMessage();
    }

    if ($errores > 0) {
        $conn->rollBack();
    } else {
        $conn->commit();
    }
    $exito = ($errores === 0);

    return [
        "ok" => true,
        "archivo" => basename($rutaArchivo),
        "insertados" => $insertados,
        "errores" => $errores,
        "total" => $total,
        "erroresDetalle" => $erroresDetalle
    ];

}

    public function enviarCorreo($nombreArchivo, $insertados, $errores, $total, $fecha, $exito, $tiempo, $memoria) {
        $mail = new PHPMailer(true);
        $mail->CharSet = 'UTF-8';

        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = $this->cfg['correo_manda'];
            $mail->Password = $this->cfg['contrasena_aplicacion'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom($this->cfg['correo_manda'], 'SophyFarm - Importación de datos');
            $mail->addAddress($this->cfg['correo_recibe'], 'Administrador');



            $mail->isHTML(true);
            $mail->Subject = ($exito ? "Carga exitosa" : "Carga fallida") . " - {$nombreArchivo}";
            $color = $exito ? "#2ecc71" : "#e74c3c";
            $mensaje = $exito
                ? "El archivo se procesó correctamente sin errores."
                : "El proceso de carga fue cancelado debido a errores en los datos.";

            $mail->Body = "
            <div style='font-family:Arial, sans-serif; border:1px solid #ddd; border-radius:8px; padding:20px; max-width:500px;'>
                <h2 style='color:{$color}; margin-top:0;'>
                    " . ($exito ? "✔ Carga exitosa" : "⚠ Carga fallida") . "
                </h2>
                <p><strong>Archivo:</strong> {$nombreArchivo}</p>
                <p><strong>Total registros:</strong> {$total}</p>
                <p><strong>Insertados:</strong> {$insertados}</p>
                <p><strong>Errores:</strong> {$errores}</p>
                <p><strong>Hora del proceso:</strong> {$fecha}</p>
                <p><strong>Duración:</strong> {$tiempo} segundos</p>
                <p><strong>Memoria usada:</strong> {$memoria} MB</p>
                <hr>
                <p>{$mensaje}</p>
                <p style='font-size:12px; color:#777;'>SophyFarm © " . date("Y") . "</p>
            </div>";

            $mail->send();
            echo " Correo enviado correctamente.<br>";
        } catch (Exception $e) {
        echo "No se pudo enviar el correo.<br>";
        echo "ErrorInfo: " . $mail->ErrorInfo . "<br>";
        echo "Exception: " . $e->getMessage() . "<br>";
    }
    }

    public function enviarWHATSAPP($mensaje, $tiempo, $memoria) {
        $apikey = $this->cfg['apikey_telefono'];
        $phone  = $this->cfg['numero_telefono'];

        $mensaje .= "\nDuración: {$tiempo}s\nMemoria usada: {$memoria}MB";

        $url = "https://api.callmebot.com/whatsapp.php?phone={$phone}&text=" . urlencode($mensaje) . "&apikey={$apikey}";

        try {
            $response = file_get_contents($url);
            if (strpos($response, 'Message queued') !== false) {
                echo "Mensaje de WhatsApp enviado correctamente.<br>";
            } else {
                echo "No se pudo confirmar el envío del mensaje. Respuesta: $response<br>";
            }
        } catch (Exception $e) {
            echo "Error al enviar mensaje WhatsApp: " . $e->getMessage() . "<br>";
        }
    }
}
?>
