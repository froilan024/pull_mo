
<?php
// Ensure we return clean JSON and hide notices/warnings that would break JSON parsing on the client
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../db.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    // flush any buffered output and return JSON
    @ob_end_clean();
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    @ob_end_clean();
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$userId = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
$fileId = isset($input['file_id']) ? (int)$input['file_id'] : 0;

if (!$fileId) {
    http_response_code(400);
    @ob_end_clean();
    echo json_encode(['error' => 'file_id required']);
    exit;
}

try {
    // Verify ownership
    $stmt = $pdo->prepare('SELECT * FROM files WHERE id = ? AND user_id = ?');
    $stmt->execute([$fileId, $userId]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$file) {
        http_response_code(404);
        @ob_end_clean();
        echo json_encode(['error' => 'File not found or not owned by you']);
        exit;
    }

    $pdo->beginTransaction();

    // delete summaries
    $stmt = $pdo->prepare('DELETE FROM summaries WHERE file_id = ?');
    $stmt->execute([$fileId]);

    // delete quizzes (if any)
    $stmt = $pdo->prepare('DELETE FROM quizzes WHERE file_id = ?');
    $stmt->execute([$fileId]);

    // delete file record
    $stmt = $pdo->prepare('DELETE FROM files WHERE id = ?');
    $stmt->execute([$fileId]);

    // delete file on disk if exists
    $uploadPath = __DIR__ . '/../' . $file['path'];
    if (file_exists($uploadPath)) {
        @unlink($uploadPath);
    }

    $pdo->commit();

    // Clean any buffered output (warnings, etc) and return JSON
    @ob_end_clean();
    echo json_encode(['ok' => true]);
    exit;
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    @ob_end_clean();
    http_response_code(500);
    echo json_encode(['error' => 'Failed to delete file', 'detail' => $e->getMessage()]);
    exit;
}

