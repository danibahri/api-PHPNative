<?php
header('Content-Type: application/json');
require_once 'db.php';

$database = new Database();
$conn = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$id = null;

if (preg_match('/\/mahasiswa\/(\d+)/', $path, $matches)) {
    $id = $matches[1];
}

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
                    echo json_encode(['message' => 'Data not found']);
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
            $sql = "INSERT INTO mahasiswa (name, nim) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$data['name'], $data['nim']]);
            
            echo json_encode([
                'message' => 'Data created successfully',
                'id' => $conn->lastInsertId()
            ]);
            break;

        case 'PUT':
            if (!$id) {
                http_response_code(400);
                echo json_encode(['message' => 'ID required']);
                break;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $sql = "UPDATE mahasiswa SET name = ?, nim = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$data['name'], $data['nim'], $id]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode(['message' => 'Data updated successfully']);
            } else {
                http_response_code(404);
                echo json_encode(['message' => 'Data not found']);
            }
            break;

        case 'DELETE':
            if (!$id) {
                http_response_code(400);
                echo json_encode(['message' => 'ID required']);
                break;
            }
            
            $sql = "DELETE FROM mahasiswa WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$id]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode(['message' => 'Data deleted successfully']);
            } else {
                http_response_code(404);
                echo json_encode(['message' => 'Data not found']);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['message' => 'Method not allowed']);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Server error: ' . $e->getMessage()]);
}

?>