<?php
// API sencilla para guardar/leer los "modelos" (nesting de corte) del
// Optimizador de Corte MDF en tu propia base de datos MySQL.
//
// Acciones (parámetro "action"):
//   GET  ?action=list                -> lista { nombre, actualizado_en } de todos los modelos
//   GET  ?action=get&name=XXX        -> devuelve un modelo completo (items)
//   POST { action:"save", name, items }   -> crea o actualiza un modelo
//   POST { action:"delete", name }        -> elimina un modelo

header('Access-Control-Allow-Origin: *'); // si quieres restringirlo, cambia * por tu dominio exacto
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$config = require __DIR__ . '/config.php';

try {
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4",
        $config['user'],
        $config['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo conectar a la base de datos. Revisa config.php']);
    exit;
}

$rawBody = file_get_contents('php://input');
$jsonBody = json_decode($rawBody, true);
$action = $_GET['action'] ?? (is_array($jsonBody) ? ($jsonBody['action'] ?? '') : '');

switch ($action) {

    case 'list':
        $stmt = $pdo->query("SELECT nombre, actualizado_en FROM modelos ORDER BY nombre ASC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'get':
        $nombre = $_GET['name'] ?? '';
        $stmt = $pdo->prepare("SELECT nombre, items_json, maquinado_json, actualizado_en FROM modelos WHERE nombre = ?");
        $stmt->execute([$nombre]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            http_response_code(404);
            echo json_encode(['error' => 'Modelo no encontrado']);
            break;
        }
        echo json_encode([
            'nombre'          => $row['nombre'],
            'items'           => json_decode($row['items_json']),
            'maquinado'       => $row['maquinado_json'] ? json_decode($row['maquinado_json']) : new stdClass(),
            'actualizado_en'  => $row['actualizado_en'],
        ]);
        break;

    case 'save':
        $input  = is_array($jsonBody) ? $jsonBody : [];
        $nombre = trim($input['name'] ?? '');
        $items  = $input['items'] ?? null;
        $maquinado = $input['maquinado'] ?? [];
        if ($nombre === '' || !is_array($items)) {
            http_response_code(400);
            echo json_encode(['error' => 'Faltan datos (name / items)']);
            break;
        }
        $itemsJson = json_encode($items);
        $maquinadoJson = json_encode($maquinado);
        $stmt = $pdo->prepare("
            INSERT INTO modelos (nombre, items_json, maquinado_json, actualizado_en)
            VALUES (:nombre, :items, :maquinado, NOW())
            ON DUPLICATE KEY UPDATE items_json = VALUES(items_json), maquinado_json = VALUES(maquinado_json), actualizado_en = NOW()
        ");
        $stmt->execute(['nombre' => $nombre, 'items' => $itemsJson, 'maquinado' => $maquinadoJson]);
        echo json_encode(['ok' => true]);
        break;

    case 'delete':
        $input  = is_array($jsonBody) ? $jsonBody : [];
        $nombre = trim($input['name'] ?? '');
        if ($nombre === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Falta name']);
            break;
        }
        $stmt = $pdo->prepare("DELETE FROM modelos WHERE nombre = ?");
        $stmt->execute([$nombre]);
        echo json_encode(['ok' => true]);
        break;

    // ===== Datos genéricos: ordenes, bitacora_enchapado, bloques, fotos =====
    // Todos comparten la tabla app_data (tipo + clave + valor_json).

    case 'list_data':
        $tipo = $_GET['tipo'] ?? '';
        if ($tipo === '') { http_response_code(400); echo json_encode(['error' => 'Falta tipo']); break; }
        $stmt = $pdo->prepare("SELECT clave, valor_json, actualizado_en FROM app_data WHERE tipo = ? ORDER BY actualizado_en ASC");
        $stmt->execute([$tipo]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(array_map(function($r){
            return ['clave' => $r['clave'], 'valor' => json_decode($r['valor_json']), 'actualizado_en' => $r['actualizado_en']];
        }, $rows));
        break;

    case 'get_data':
        $tipo = $_GET['tipo'] ?? '';
        $clave = $_GET['clave'] ?? '';
        if ($tipo === '' || $clave === '') { http_response_code(400); echo json_encode(['error' => 'Falta tipo/clave']); break; }
        $stmt = $pdo->prepare("SELECT valor_json, actualizado_en FROM app_data WHERE tipo = ? AND clave = ?");
        $stmt->execute([$tipo, $clave]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { http_response_code(404); echo json_encode(['error' => 'No encontrado']); break; }
        echo json_encode(['valor' => json_decode($row['valor_json']), 'actualizado_en' => $row['actualizado_en']]);
        break;

    case 'save_data':
        $input = is_array($jsonBody) ? $jsonBody : [];
        $tipo  = trim($input['tipo'] ?? '');
        $clave = trim($input['clave'] ?? '');
        $valor = array_key_exists('valor', $input) ? $input['valor'] : null;
        if ($tipo === '' || $clave === '') { http_response_code(400); echo json_encode(['error' => 'Falta tipo/clave']); break; }
        $valorJson = json_encode($valor);
        $stmt = $pdo->prepare("
            INSERT INTO app_data (tipo, clave, valor_json, actualizado_en)
            VALUES (:tipo, :clave, :valor, NOW())
            ON DUPLICATE KEY UPDATE valor_json = VALUES(valor_json), actualizado_en = NOW()
        ");
        $stmt->execute(['tipo' => $tipo, 'clave' => $clave, 'valor' => $valorJson]);
        echo json_encode(['ok' => true]);
        break;

    case 'delete_data':
        $input = is_array($jsonBody) ? $jsonBody : [];
        $tipo  = trim($input['tipo'] ?? '');
        $clave = trim($input['clave'] ?? '');
        if ($tipo === '' || $clave === '') { http_response_code(400); echo json_encode(['error' => 'Falta tipo/clave']); break; }
        $stmt = $pdo->prepare("DELETE FROM app_data WHERE tipo = ? AND clave = ?");
        $stmt->execute([$tipo, $clave]);
        echo json_encode(['ok' => true]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Acción no reconocida']);
}
