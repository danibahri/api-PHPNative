<?php
header('Content-Type: application/json');
require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$id = null;

if (preg_match('/\/mahasiswa\/(\d+)/', $path, $matches)) {
    $id = $matches[1];
}

switch ($method) {
    case 'GET':
        if ($id) {
            // Get mahasiswa by ID
            $sql = "SELECT * FROM mahasiswa WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result->fetch_assoc();
            
            echo json_encode($data ?? ['message' => 'Data not found']);
        } else {
            // Get all mahasiswa
            $sql = "SELECT * FROM mahasiswa";
            $result = $conn->query($sql);
            $data = $result->fetch_all(MYSQLI_ASSOC);
            
            echo json_encode($data);
        }
        break;

    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        $sql = "INSERT INTO mahasiswa (name, nim) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $data['name'], $data['nim']);
        
        if ($stmt->execute()) {
            echo json_encode([
                'message' => 'Data created successfully',
                'id' => $conn->insert_id
            ]);
        } else {
            http_response_code(400);
            echo json_encode(['message' => 'Failed to create data']);
        }
        break;

    case 'PUT':
        if ($id) {
            $data = json_decode(file_get_contents('php://input'), true);
            $sql = "UPDATE mahasiswa SET name = ?, nim = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssi", $data['name'], $data['nim'], $id);
            
            if ($stmt->execute()) {
                echo json_encode(['message' => 'Data updated successfully']);
            } else {
                http_response_code(400);
                echo json_encode(['message' => 'Failed to update data']);
            }
        }
        break;

    case 'DELETE':
        if ($id) {
            $sql = "DELETE FROM mahasiswa WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                echo json_encode(['message' => 'Data deleted successfully']);
            } else {
                http_response_code(400);
                echo json_encode(['message' => 'Failed to delete data']);
            }
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['message' => 'Method not allowed']);
        break;
}

$conn->close();
?>