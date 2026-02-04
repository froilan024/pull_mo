<?php
session_start();
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

try {
    // Fetch all files for the logged-in user
    $stmt = $pdo->prepare('SELECT id, original_name, filename, uploaded_at FROM files WHERE user_id = ? ORDER BY uploaded_at DESC');
    $stmt->execute([$_SESSION['user_id']]);
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['files' => $files]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
