<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

// Helper function to extract text from PPTX files (ZIP archive)
function extractTextFromPPTX($filePath) {
    $text = '';
    try {
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) return '';
        
        // PPTX stores slide content in ppt/slides/slideX.xml
        // Try to read slide files
        $slideIndex = 1;
        while (true) {
            $slideFile = "ppt/slides/slide" . $slideIndex . ".xml";
            if ($zip->locateName($slideFile) === false) {
                break;
            }
            
            $xmlString = $zip->getFromName($slideFile);
            if ($xmlString === false) break;
            
            // Remove namespaces for easier parsing
            $xmlString = preg_replace('/<[a-z]+:([^>]*)>/i', '<\\1>', $xmlString);
            $xmlString = preg_replace('/<\/[a-z]+:([^>]*)>/i', '</\\1>', $xmlString);
            
            // Extract text from <t> tags (text elements in PowerPoint)
            if (preg_match_all('/<t>([^<]*)<\/t>/', $xmlString, $matches)) {
                foreach ($matches[1] as $match) {
                    $cleaned = trim($match);
                    if (!empty($cleaned)) {
                        $text .= $cleaned . ' ';
                    }
                }
            }
            
            $slideIndex++;
        }
        
        $zip->close();
        return trim($text);
    } catch (Exception $e) {
        return '';
    }
}

// Helper function to extract images from PPTX and send to HF for OCR
function extractImageTextFromPPTX($filePath, $hfToken) {
    $text = '';
    if (empty($hfToken)) return '';
    
    try {
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) return '';
        
        // Get all image files from ppt/media/
        $imageCount = 0;
        for ($i = 1; $i <= 100; $i++) {
            $imagePath = "ppt/media/image$i.png";
            $imageData = $zip->getFromName($imagePath);
            
            if ($imageData === false) {
                // Try JPEG
                $imagePath = "ppt/media/image$i.jpeg";
                $imageData = $zip->getFromName($imagePath);
            }
            
            if ($imageData === false) {
                // Try JPG
                $imagePath = "ppt/media/image$i.jpg";
                $imageData = $zip->getFromName($imagePath);
            }
            
            if ($imageData !== false && !empty($imageData)) {
                // Send to HF for OCR using Vision Transformer
                $base64Image = base64_encode($imageData);
                $payload = [
                    'inputs' => $base64Image
                ];
                
                // Use Tesseract-based model or Vision model
                $model = 'microsoft/trocr-base-printed'; // OCR model
                $ch = curl_init('https://api-inference.huggingface.co/models/' . $model);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Authorization: Bearer ' . $hfToken,
                    'Content-Type: application/json'
                ]);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                
                $resp = curl_exec($ch);
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($resp !== false && $code < 400) {
                    $decoded = json_decode($resp, true);
                    // TrOCR returns text in generated_text or 0_generated_text
                    if (is_array($decoded)) {
                        if (isset($decoded['generated_text'])) {
                            $text .= $decoded['generated_text'] . ' ';
                        } elseif (isset($decoded[0]['generated_text'])) {
                            $text .= $decoded[0]['generated_text'] . ' ';
                        }
                    }
                }
                
                $imageCount++;
                if ($imageCount >= 10) break; // Limit to first 10 images to avoid overload
            }
        }
        
        $zip->close();
        return trim($text);
    } catch (Exception $e) {
        return '';
    }
}

// Helper function to extract text from DOCX files (ZIP archive)
function extractTextFromDOCX($filePath) {
    $text = '';
    try {
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) return '';
        
        // DOCX stores content in word/document.xml
        $xmlString = $zip->getFromName('word/document.xml');
        if ($xmlString === false) {
            $zip->close();
            return '';
        }
        
        // Extract text from XML - looks for <w:t> tags
        if (preg_match_all('/<w:t[^>]*>([^<]*)<\/w:t>/', $xmlString, $matches)) {
            foreach ($matches[1] as $match) {
                $text .= $match . ' ';
            }
        }
        $zip->close();
        return trim($text);
    } catch (Exception $e) {
        return '';
    }
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

if (empty($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded (field name=file)']);
    exit();
}

$f = $_FILES['file'];
if ($f['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'Upload error: '.$f['error']]);
    exit();
}

$uploadsDir = __DIR__ . '/../uploads';
if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0755, true);

$original = basename($f['name']);
$ext = pathinfo($original, PATHINFO_EXTENSION);
$safeName = uniqid('file_', true) . ($ext ? '.' . $ext : '');
$target = $uploadsDir . '/' . $safeName;

if (!move_uploaded_file($f['tmp_name'], $target)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to move uploaded file']);
    exit();
}

// Insert file record
try {
    $ins = $pdo->prepare('INSERT INTO files (user_id, filename, original_name, file_path, file_type, file_size) VALUES (?, ?, ?, ?, ?, ?)');
    $ins->execute([$userId, $safeName, $original, $target, $f['type'], (int)$f['size']]);
    $fileId = $pdo->lastInsertId();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB error inserting file: '.$e->getMessage()]);
    exit();
}

// Attempt to summarise: if HF token exists, try to read text (for plain text) or send a prompt
$summaryText = '';
$hf_token = defined('HF_API_TOKEN') ? HF_API_TOKEN : '';
$extractedText = '';

// Extract text from various file types
if (in_array($ext, ['txt','md','text'])) {
    $extractedText = @file_get_contents($target);
} elseif ($ext === 'pptx') {
    // Extract text from PPTX (ZIP archive)
    $extractedText = extractTextFromPPTX($target);
    // Also extract text from images in PPTX using HF OCR
    if (!empty($hf_token)) {
        $imageText = extractImageTextFromPPTX($target, $hf_token);
        if (!empty($imageText)) {
            $extractedText .= ' ' . $imageText;
        }
    }
} elseif ($ext === 'docx') {
    // Extract text from DOCX (ZIP archive)
    $extractedText = extractTextFromDOCX($target);
}

if (!empty($extractedText)) {
    $summaryText = $extractedText;
}

if (empty($summaryText) && !empty($hf_token)) {
    // generate a short summary prompt from filename (best-effort)
    $model = 'sshleifer/distilbart-cnn-12-6';
    $rawText = "File: $original. Please summarize or analyze this document based on its filename.";
    $summaryText = $rawText;

    if (!empty($hf_token)) {
        $payload = ['inputs' => $rawText, 'options' => ['wait_for_model' => true]];
        $ch = curl_init('https://api-inference.huggingface.co/models/' . $model);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $hf_token,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($resp !== false && $code < 400) {
            $decoded = json_decode($resp, true);
            if (isset($decoded[0]['summary_text'])) $summaryText = $decoded[0]['summary_text'];
            elseif (isset($decoded[0]['generated_text'])) $summaryText = $decoded[0]['generated_text'];
        }
    }
}

if (empty($summaryText)) {
    $summaryText = 'Summary placeholder for ' . $original . '. (No text extracted)';
}

// Store summary
try {
    $sins = $pdo->prepare('INSERT INTO summaries (file_id, user_id, title, summary) VALUES (?, ?, ?, ?)');
    $sins->execute([$fileId, $userId, $original, $summaryText]);
} catch (Exception $e) {
    // not fatal
}

echo json_encode(['file_id' => (int)$fileId, 'original_name' => $original, 'summary' => $summaryText]);
exit();
