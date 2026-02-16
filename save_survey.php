<?php
header('Content-Type: application/json');
// Desactivar impresión de errores en el output para no romper el JSON
error_reporting(E_ALL);
ini_set('display_errors', 0); 

$csvFilePath = 'todas_las_encuestas.csv';
$lockFilePath = 'survey.lock';

// Definición de secciones y preguntas (DEBE COINCIDIR con tu JS para generar columnas ordenadas)
$sections = [
    "Capital Humano", "Capacitación", "Seguridad Vial", "Despacho", 
    "Monitoreo", "Tráfico", "Mantenimiento", "Liquidaciones", 
    "Área Médica", "Area comercial"
];
$totalPreguntas = 86;

// --- FUNCIONES AUXILIARES ---
function json_output($success, $message, $extras = []) {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extras));
    exit;
}

// 1. Leer entrada
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if ($data === null) {
    json_output(false, 'Error al decodificar JSON recibido.');
}

if (empty($data['empresa'])) {
    json_output(false, 'El nombre de la empresa es obligatorio.');
}

// 2. Bloqueo de archivo para evitar escritura simultánea
$lockFileHandle = fopen($lockFilePath, 'w');
if (!$lockFileHandle || !flock($lockFileHandle, LOCK_EX)) {
    json_output(false, 'El sistema está ocupado, intenta de nuevo en unos segundos.');
}

try {
    // 3. Generar o recuperar ID
    $surveyId = $data['surveyId'] ?? null;
    $isUpdate = false;
    
    if (empty($surveyId)) {
        // Generar ID único: PRQ_Fecha_Empresa
        $cleanName = substr(preg_replace("/[^a-zA-Z0-9]/", "", $data['empresa']), 0, 5);
        $surveyId = 'PRQ_' . date('Ymd_His') . '_' . strtoupper($cleanName);
    } else {
        $isUpdate = true;
    }

    // 4. DEFINIR CABECERAS DEL CSV (Orden estricto)
    $headers = [
        'SurveyID', 'Timestamp', 'Status', 
        // Sección 1
        'Empresa', 'descripcionEmpresa', 'FechaEntrevista', 'numeroPoliza',
        'Certificaciones', 'PrincipalesClientes', 'TipoMercancia', 'riesgosViales','oportunidadesMejora',
        // Sección 2
        'estadosServicio', // Nuevo
        'numConductores', 'numTalleres', 'kmRecorridosMes', 'viajesAnio',
        'lesionadosMes', //'lesionadosAnio', 'muertosMes', 'muertosAnio',
        'estadoMatriz', 'municipioMatriz', 'UbicacionesAdicionales',
        // Siniestralidad (Nuevos)
        'fechaConsultaSiniestralidad', 'frecuenciaSiniestralidad', 'severidadSiniestralidad', 'tablaSiniestros',
        'comentariosFinales'
    ];

    // Cabeceras dinámicas para Entrevistados
    foreach ($sections as $section) {
        $key = preg_replace('/[^a-zA-Z0-9_]/', '', str_replace(' ', '_', $section));
        $headers[] = 'Entrevistado_' . $key;
        $headers[] = 'Puesto_' . $key;
        $headers[] = 'Antiguedad_' . $key;
    }

    $headers[] = 'SeccionesDesactivadas';

    // Cabeceras dinámicas para Preguntas
    for ($i = 1; $i <= $totalPreguntas; $i++) {
        $headers[] = 'Pregunta_' . $i;
        $headers[] = 'Recomendacion_' . $i;
    }

    // 5. MAPEAR DATOS AL ARRAY PLANO
    $row = [];
    $row['SurveyID'] = $surveyId;
    $row['Timestamp'] = date('c');
    $row['Status'] = $data['status'] ?? 'pendiente';
    
    // Campos directos
    $row['Empresa'] = $data['empresa'] ?? '';
    $row['descripcionEmpresa'] = $data['descripcionEmpresa'] ?? '';
    $row['FechaEntrevista'] = $data['fechaEntrevista'] ?? '';
    $row['numeroPoliza'] = $data['numeroPoliza'] ?? '';
    $row['Certificaciones'] = $data['certificaciones'] ?? '';
    $row['PrincipalesClientes'] = $data['principalesClientes'] ?? '';
    $row['TipoMercancia'] = $data['tipoMercancia'] ?? '';
    
    $row['estadosServicio'] = $data['estadosServicio'] ?? '';
    $row['numConductores'] = $data['numConductores'] ?? '';
    $row['numTalleres'] = $data['numTalleres'] ?? '';
    $row['kmRecorridosMes'] = $data['kmRecorridosMes'] ?? '';
    $row['viajesAnio'] = $data['viajesAnio'] ?? '';
    $row['lesionadosMes'] = $data['lesionadosMes'] ?? '';
    //$row['lesionadosAnio'] = $data['lesionadosAnio'] ?? '';
    //$row['muertosMes'] = $data['muertosMes'] ?? '';
    //$row['muertosAnio'] = $data['muertosAnio'] ?? '';
    $row['estadoMatriz'] = $data['estadoMatriz'] ?? '';
    $row['municipioMatriz'] = $data['municipioMatriz'] ?? '';
    $row['UbicacionesAdicionales'] = $data['ubicacionesAdicionales'] ?? '';

    // Siniestralidad
    $row['fechaConsultaSiniestralidad'] = $data['fechaConsultaSiniestralidad'] ?? '';
    $row['frecuenciaSiniestralidad'] = $data['frecuenciaSiniestralidad'] ?? '';
    $row['severidadSiniestralidad'] = $data['severidadSiniestralidad'] ?? '';
    // Importante: La tabla es un array, la guardamos como JSON String dentro del CSV
    $row['tablaSiniestros'] = isset($data['tablaSiniestros']) ? json_encode($data['tablaSiniestros'], JSON_UNESCAPED_UNICODE) : '[]';
    $row['comentariosFinales'] = $data['comentariosFinales']??'';
    // Entrevistados
    if (isset($data['seccionesInfo'])) {
        foreach ($data['seccionesInfo'] as $sec => $info) {
            $key = preg_replace('/[^a-zA-Z0-9_]/', '', str_replace(' ', '_', $sec));
            $row['Entrevistado_' . $key] = $info['entrevistado'] ?? '';
            $row['Puesto_' . $key] = $info['puesto'] ?? '';
            $row['Antiguedad_' . $key] = $info['antiguedad'] ?? '';
        }
    }

    $row['SeccionesDesactivadas'] = implode(',', $data['disabledSections'] ?? []);

    // Preguntas
    if (isset($data['respuestasCuestionario'])) {
        foreach ($data['respuestasCuestionario'] as $resp) {
            $row['Pregunta_' . $resp['contador']] = $resp['respuesta'] ?? '';
            $row['Recomendacion_' . $resp['contador']] = $resp['recomendacion'] ?? '';
        }
    }

    // 6. PREPARAR ESCRITURA (CSV)
    $finalRowData = [];
    foreach ($headers as $h) {
        $finalRowData[] = $row[$h] ?? '';
    }

    $tempFile = 'temp_' . uniqid() . '.csv';
    $handleWrite = fopen($tempFile, 'w');
    // Escribir BOM para que Excel abra bien los acentos
    fprintf($handleWrite, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Escribir encabezados
    fputcsv($handleWrite, $headers);

    $found = false;
    
    // Leer archivo existente si hay
    if (file_exists($csvFilePath)) {
        $handleRead = fopen($csvFilePath, 'r');
        if ($handleRead) {
            // Leer encabezados existentes (los descartamos porque usamos los nuevos definidos arriba)
            fgetcsv($handleRead); 
            
            while (($dataRow = fgetcsv($handleRead)) !== false) {
                // Si el CSV antiguo tiene menos columnas, rellenar
                // Pero lo más seguro es comparar por ID
                // Asumimos que la columna 0 es SurveyID
                if ($dataRow[0] == $surveyId) {
                    // Reemplazar fila
                    fputcsv($handleWrite, $finalRowData);
                    $found = true;
                } else {
                    // Copiar fila existente. 
                    // NOTA: Si cambiaron las columnas, esto podría desalinear datos viejos.
                    // Para producción idealmente se mapea por nombre de columna, pero esto es funcional para desarrollo.
                    
                    // Ajuste simple: Si las columnas no coinciden, rellenar con vacíos
                    while(count($dataRow) < count($headers)) { $dataRow[] = ''; }
                    fputcsv($handleWrite, $dataRow);
                }
            }
            fclose($handleRead);
        }
    }

    if (!$found) {
        fputcsv($handleWrite, $finalRowData);
    }

    fclose($handleWrite);

    // 7. REEMPLAZAR ARCHIVO
    if (rename($tempFile, $csvFilePath)) {
        json_output(true, 'Guardado correctamente.', ['surveyId' => $surveyId]);
    } else {
        unlink($tempFile);
        json_output(false, 'Error al guardar el archivo en disco.');
    }

} catch (Exception $e) {
    json_output(false, 'Excepción: ' . $e->getMessage());
} finally {
    flock($lockFileHandle, LOCK_UN);
    fclose($lockFileHandle);
    @unlink($lockFilePath);
}
?>