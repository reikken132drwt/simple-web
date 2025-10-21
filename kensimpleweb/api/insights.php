<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../config/database.php';

try {
    $pdo = getPDO();
    
    // Get top performing student this month using subquery
    $stmt = $pdo->query("
        SELECT name, (math + science + english + history) / 4 as average 
        FROM students 
        WHERE (math + science + english + history) / 4 = (
            SELECT MAX((math + science + english + history) / 4) 
            FROM students
        )
        LIMIT 1
    ");
    $topStudent = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get subject with lowest average score using subquery
    $stmt = $pdo->query("
        SELECT 'math' as subject, AVG(math) as average FROM students
        UNION ALL
        SELECT 'science' as subject, AVG(science) as average FROM students
        UNION ALL
        SELECT 'english' as subject, AVG(english) as average FROM students
        UNION ALL
        SELECT 'history' as subject, AVG(history) as average FROM students
        ORDER BY average ASC
        LIMIT 1
    ");
    $lowestSubject = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'topStudent' => $topStudent ? $topStudent['name'] . ' (' . round($topStudent['average'], 1) . '%)' : 'No data',
        'lowestSubject' => $lowestSubject ? ucfirst($lowestSubject['subject']) . ' (' . round($lowestSubject['average'], 1) . '%)' : 'No data'
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>