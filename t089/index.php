<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Untuk menangani preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

// Log untuk debugging
// error_log("Request received: " . $_SERVER['REQUEST_METHOD'] . " " . $_SERVER['REQUEST_URI']);

// Validasi token terlebih dahulu
$auth = new Auth();
$tokenValidation = $auth->validateToken();

// Log hasil validasi token
// error_log("Token validation result: " . print_r($tokenValidation, true));

if (!$tokenValidation['success']) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => $tokenValidation['message']
    ]);
    exit;
}

$database = new Database();
$conn = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];

// Perbaikan base path sesuai struktur direktori
$basePath = '/t089';  // Langsung set base path sesuai struktur
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = substr($requestUri, strlen($basePath)) ?: '';
$path = trim($path, '/');

$id = null;

// Perbaikan regex untuk menghilangkan leading slash
if (preg_match('/^mahasiswa\/(\d+)$/', $path, $matches)) {
    $id = $matches[1];
}

// var_dump($path, $id); exit;

try {
    switch ($method) {
        case 'GET':
            if ($id) {
                $sql = "SELECT * FROM mahasiswa WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$id]);
                $data = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($data) {
                    echo json_encode($data);
                } else {
                    http_response_code(404);
                    echo json_encode(['message' => 'Data tidak ditemukan']);
                }
            } else {
                $sql = "SELECT * FROM mahasiswa";
                $stmt = $conn->query($sql);
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode($data);
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['name']) || empty($data['nim'])) {
                http_response_code(400);
                echo json_encode(['message' => 'Nama dan NIM tidak boleh kosong']);
                break;
            }
            $sql = "INSERT INTO mahasiswa (name, nim) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$data['name'], $data['nim']]);
            
            echo json_encode([
                'message' => 'Data berhasil ditambahkan',
                'id' => $conn->lastInsertId()
            ]);
            break;

        case 'PUT':
            if (!$id) {
                http_response_code(400);
                echo json_encode(['message' => 'ID tidak boleh kosong']);
                break;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['name']) || empty($data['nim'])) {
                http_response_code(400);
                echo json_encode(['message' => 'Nama dan NIM tidak boleh kosong']);
                break;
            }
            $sql = "UPDATE mahasiswa SET name = ?, nim = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$data['name'], $data['nim'], $id]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode(['message' => 'Data berhasil diupdate']);
            } else {
                http_response_code(404);
                echo json_encode(['message' => 'Data tidak ditemukan']);
            }
            break;

        case 'DELETE':
            if (!$id) {
                http_response_code(400);
                echo json_encode(['message' => 'ID tidak boleh kosong']);
                break;
            }
            
            $sql = "DELETE FROM mahasiswa WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$id]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode(['message' => 'Data berhasil dihapus']);
            } else {
                http_response_code(404);
                echo json_encode(['message' => 'Data tidak ditemukan']);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['message' => 'Method tidak diizinkan']);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Server error: ' . $e->getMessage()]);
}