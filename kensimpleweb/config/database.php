<?php
function getPDO() {
    $host = 'sql305.infinityfree.com';
    $dbname = 'if0_40212869_student_tracker';
    $username = 'if0_40212869';
    $password = 'dUKPK3Qw566';
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        throw new Exception('Connection failed: ' . $e->getMessage());
    }
}
?>