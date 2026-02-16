<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

$csvFilePath = 'todas_las_encuestas.csv';

if (!file_exists($csvFilePath)) {
    echo json_encode([]);
    exit;
}

$surveys = [];
$handle = fopen($csvFilePath, 'r');

if ($handle) {
    // Leer la primera fila (encabezados)
    // Removemos el BOM si existe para que las claves del array sean limpias
    $headers = fgetcsv($handle);
    
    if ($headers) {
        // Limpiar BOM de la primera columna si existe (common UTF-8 issue)
        $headers[0] = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $headers[0]);

        while (($row = fgetcsv($handle)) !== false) {
            // Solo procesar si el número de columnas coincide
            // (o al menos ajustar para evitar errores)
            if (count($row) === count($headers)) {
                $item = array_combine($headers, $row);
                
                // Procesar campos especiales que guardamos como JSON string
                if (!empty($item['tablaSiniestros'])) {
                    $decoded = json_decode($item['tablaSiniestros'], true);
                    $item['tablaSiniestros'] = $decoded ?: []; 
                }
                
                // Solo nos interesan los pendientes (opcional, el frontend filtra también)
                // Pero es mejor enviar todo y que el frontend decida o filtrar aquí.
                // Tu frontend actual filtra por 'pendiente', así que enviamos todo.
                $surveys[] = $item;
            }
        }
    }
    fclose($handle);
}

echo json_encode($surveys);
?>