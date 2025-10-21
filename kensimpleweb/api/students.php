<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

require_once '../config/database.php';

$action = $_GET['action'] ?? '';

try {
    $pdo = getPDO();
    
    switch($action) {
        case 'getAll':
            getAllStudents($pdo);
            break;
        case 'add':
            addStudent($pdo);
            break;
        case 'update':
            updateStudent($pdo);
            break;
        case 'delete':
            deleteStudent($pdo);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function getAllStudents($pdo) {
    $stmt = $pdo->query("SELECT * FROM students ORDER BY name");
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($students);
}

function addStudent($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!validateStudentData($input)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid student data']);
        return;
    }
    
    $stmt = $pdo->prepare("INSERT INTO students (name, math, science, english, history) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $input['name'],
        $input['math'],
        $input['science'],
        $input['english'],
        $input['history']
    ]);
    
    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
}

function updateStudent($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!validateStudentData($input) || !isset($input['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid student data']);
        return;
    }
    
    $stmt = $pdo->prepare("UPDATE students SET name = ?, math = ?, science = ?, english = ?, history = ? WHERE id = ?");
    $stmt->execute([
        $input['name'],
        $input['math'],
        $input['science'],
        $input['english'],
        $input['history'],
        $input['id']
    ]);
    
    echo json_encode(['success' => true]);
}

function deleteStudent($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Student ID required']);
        return;
    }
    
    $stmt = $pdo->prepare("DELETE FROM students WHERE id = ?");
    $stmt->execute([$input['id']]);
    
    echo json_encode(['success' => true]);
}

function validateStudentData($data) {
    return isset($data['name']) && 
           isset($data['math']) && 
           isset($data['science']) && 
           isset($data['english']) && 
           isset($data['history']);
}
?>