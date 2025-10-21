<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Database configuration - UPDATE THESE WITH YOUR CREDENTIALS
$host = 'localhost';
$dbname = 'student_tracker';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Get the action parameter
$action = $_GET['action'] ?? '';

// Get JSON input from request body
$input = json_decode(file_get_contents('php://input'), true);
if ($input === null) {
    $input = [];
}

// Merge with POST data for fallback
$requestData = array_merge($_POST, $input);

switch ($action) {
    case 'get_students':
        getStudents($pdo);
        break;
    case 'get_student':
        getStudent($pdo, $_GET['id'] ?? '');
        break;
    case 'add_student':
        addStudent($pdo, $requestData);
        break;
    case 'update_student':
        updateStudent($pdo, $requestData);
        break;
    case 'delete_student':
        deleteStudent($pdo, $requestData);
        break;
    case 'get_analytics':
        getAnalytics($pdo);
        break;
    case 'get_insights':
        getInsights($pdo);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

function getStudents($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM students ORDER BY date_added DESC");
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'students' => $students]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to fetch students']);
    }
}

function getStudent($pdo, $id) {
    try {
        if (empty($id)) {
            echo json_encode(['success' => false, 'message' => 'Student ID is required']);
            return;
        }
        
        $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
        $stmt->execute([$id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($student) {
            echo json_encode(['success' => true, 'student' => $student]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Student not found']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to fetch student']);
    }
}

function addStudent($pdo, $data) {
    try {
        $name = $data['name'] ?? '';
        $email = $data['email'] ?? '';
        $subject = $data['subject'] ?? '';
        $score = $data['score'] ?? '';
        
        // Validate required fields
        if (empty($name) || empty($email) || empty($subject) || empty($score)) {
            echo json_encode(['success' => false, 'message' => 'All fields are required']);
            return;
        }
        
        // Validate score range
        if (!is_numeric($score) || $score < 0 || $score > 100) {
            echo json_encode(['success' => false, 'message' => 'Score must be between 0 and 100']);
            return;
        }
        
        $stmt = $pdo->prepare("INSERT INTO students (name, email, subject, score) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $email, $subject, $score]);
        
        echo json_encode(['success' => true, 'message' => 'Student added successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to add student: ' . $e->getMessage()]);
    }
}

function updateStudent($pdo, $data) {
    try {
        $id = $data['id'] ?? '';
        $name = $data['name'] ?? '';
        $email = $data['email'] ?? '';
        $subject = $data['subject'] ?? '';
        $score = $data['score'] ?? '';
        
        // Validate required fields
        if (empty($id) || empty($name) || empty($email) || empty($subject) || empty($score)) {
            echo json_encode(['success' => false, 'message' => 'All fields are required']);
            return;
        }
        
        // Validate score range
        if (!is_numeric($score) || $score < 0 || $score > 100) {
            echo json_encode(['success' => false, 'message' => 'Score must be between 0 and 100']);
            return;
        }
        
        $stmt = $pdo->prepare("UPDATE students SET name = ?, email = ?, subject = ?, score = ? WHERE id = ?");
        $stmt->execute([$name, $email, $subject, $score, $id]);
        
        echo json_encode(['success' => true, 'message' => 'Student updated successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to update student']);
    }
}

function deleteStudent($pdo, $data) {
    try {
        $id = $data['id'] ?? '';
        
        if (empty($id)) {
            echo json_encode(['success' => false, 'message' => 'Student ID is required']);
            return;
        }
        
        $stmt = $pdo->prepare("DELETE FROM students WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true, 'message' => 'Student deleted successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to delete student']);
    }
}

function getAnalytics($pdo) {
    try {
        // Total students
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM students");
        $totalStudents = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Class average
        $stmt = $pdo->query("SELECT AVG(score) as average FROM students");
        $classAverage = round($stmt->fetch(PDO::FETCH_ASSOC)['average'], 2);
        
        // Subject averages
        $stmt = $pdo->query("SELECT subject, AVG(score) as average FROM students GROUP BY subject");
        $subjectData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $subjects = [];
        $subjectAverages = [];
        foreach ($subjectData as $row) {
            $subjects[] = $row['subject'];
            $subjectAverages[] = round($row['average'], 2);
        }
        
        // Monthly averages (last 6 months)
        $stmt = $pdo->query("
            SELECT 
                DATE_FORMAT(date_added, '%Y-%m') as month, 
                AVG(score) as average 
            FROM students 
            WHERE date_added >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(date_added, '%Y-%m')
            ORDER BY month
        ");
        $monthlyData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $months = [];
        $monthlyAverages = [];
        foreach ($monthlyData as $row) {
            $months[] = date('M Y', strtotime($row['month'] . '-01'));
            $monthlyAverages[] = round($row['average'], 2);
        }
        
        // Top 5 students by average score
        $stmt = $pdo->query("
            SELECT 
                name, 
                subject,
                AVG(score) as average_score 
            FROM students 
            GROUP BY name, subject 
            ORDER BY average_score DESC 
            LIMIT 5
        ");
        $topStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'analytics' => [
                'total_students' => $totalStudents,
                'class_average' => $classAverage,
                'subjects' => $subjects,
                'subject_averages' => $subjectAverages,
                'months' => $months,
                'monthly_averages' => $monthlyAverages,
                'top_students' => $topStudents
            ]
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to load analytics']);
    }
}

function getInsights($pdo) {
    try {
        // Top performing student this month
        $stmt = $pdo->query("
            SELECT name, AVG(score) as avg_score
            FROM students 
            WHERE DATE_FORMAT(date_added, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')
            GROUP BY name 
            ORDER BY avg_score DESC 
            LIMIT 1
        ");
        $topStudentMonth = $stmt->fetch(PDO::FETCH_ASSOC);
        $topStudentText = $topStudentMonth ? $topStudentMonth['name'] . ' (' . round($topStudentMonth['avg_score'], 2) . '%)' : 'No data';
        
        // Subject with lowest average score
        $stmt = $pdo->query("
            SELECT subject, AVG(score) as avg_score
            FROM students 
            GROUP BY subject 
            ORDER BY avg_score ASC 
            LIMIT 1
        ");
        $lowestSubject = $stmt->fetch(PDO::FETCH_ASSOC);
        $lowestSubjectText = $lowestSubject ? $lowestSubject['subject'] . ' (' . round($lowestSubject['avg_score'], 2) . '%)' : 'No data';
        
        // Most improved student
        $stmt = $pdo->query("
            SELECT 
                s1.name,
                (AVG(s1.score) - COALESCE(AVG(s2.score), 0)) as improvement
            FROM students s1
            LEFT JOIN students s2 ON s1.name = s2.name 
                AND DATE_FORMAT(s2.date_added, '%Y-%m') = DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH), '%Y-%m')
            WHERE DATE_FORMAT(s1.date_added, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')
            GROUP BY s1.name
            ORDER BY improvement DESC
            LIMIT 1
        ");
        $mostImproved = $stmt->fetch(PDO::FETCH_ASSOC);
        $mostImprovedText = $mostImproved ? $mostImproved['name'] . ' (+' . round($mostImproved['improvement'], 2) . '%)' : 'No data';
        
        // Subject with highest average score
        $stmt = $pdo->query("
            SELECT subject, AVG(score) as avg_score
            FROM students 
            GROUP BY subject 
            ORDER BY avg_score DESC 
            LIMIT 1
        ");
        $highestSubject = $stmt->fetch(PDO::FETCH_ASSOC);
        $highestSubjectText = $highestSubject ? $highestSubject['subject'] . ' (' . round($highestSubject['avg_score'], 2) . '%)' : 'No data';
        
        echo json_encode([
            'success' => true,
            'insights' => [
                'top_student_month' => $topStudentText,
                'lowest_avg_subject' => $lowestSubjectText,
                'most_improved' => $mostImprovedText,
                'highest_avg_subject' => $highestSubjectText
            ]
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to load insights']);
    }
}
?>