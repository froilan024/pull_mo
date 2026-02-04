<?php
session_start();
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$userId = $_SESSION['user_id'];
$fileId = isset($_GET['file_id']) ? (int)$_GET['file_id'] : 0;

if (!$fileId) {
    http_response_code(400);
    echo json_encode(['error' => 'file_id required']);
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM files WHERE id = ? AND user_id = ?');
$stmt->execute([$fileId, $userId]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$file) {
    http_response_code(404);
    echo json_encode(['error' => 'File not found or not owned by you']);
    exit;
}

// Check summaries table first
$stmt = $pdo->prepare('SELECT summary FROM summaries WHERE file_id = ? LIMIT 1');
$stmt->execute([$fileId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row && !empty(trim($row['summary'])) && strtolower(trim($row['summary'])) !== 'placeholder summary') {
    echo json_encode(['ok' => true, 'source' => 'summary', 'text' => $row['summary']]);
    exit;
}

// fallback: try to extract from file (txt, docx, pptx). We'll implement lightweight extraction similar to upload_and_summarize.
$filePath = __DIR__ . '/../' . $file['path'];
$ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

$extracted = '';
if ($ext === 'txt') {
    $extracted = file_get_contents($filePath);
} elseif ($ext === 'docx') {
    // extract docx text
    $zip = new ZipArchive();
    if ($zip->open($filePath) === true) {
        $index = $zip->locateName('word/document.xml');
        if ($index !== false) {
            $data = $zip->getFromIndex($index);
            $xml = new DOMDocument();
            @$xml->loadXML($data);
            $textNodes = $xml->getElementsByTagName('t');
            $arr = [];
            foreach ($textNodes as $node) {
                $arr[] = $node->nodeValue;
            }
            $extracted = implode("\n", $arr);
        }
        $zip->close();
    }
} elseif ($ext === 'pptx') {
    $zip = new ZipArchive();
    if ($zip->open($filePath) === true) {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (strpos($name, 'ppt/slides/slide') !== false && substr($name, -4) === '.xml') {
                $data = $zip->getFromIndex($i);
                $xml = new DOMDocument();
                @$xml->loadXML($data);
                $textNodes = $xml->getElementsByTagName('t');
                foreach ($textNodes as $node) {
                    $extracted .= $node->nodeValue . "\n";
                }
            }
        }
        $zip->close();
    }
}

if (empty(trim($extracted))) {
    echo json_encode(['ok' => true, 'source' => 'extraction', 'text' => '(no extracted text found)']);
} else {
    echo json_encode(['ok' => true, 'source' => 'extraction', 'text' => $extracted]);
}

?>
