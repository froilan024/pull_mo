<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// parse input JSON or POST
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$file_id = isset($input['file_id']) ? (int)$input['file_id'] : 0;
$count = isset($input['count']) ? (int)$input['count'] : 3;
$count = max(1, min(20, $count));
if (!$file_id) {
    http_response_code(400);
    echo json_encode(['error' => 'file_id required']);
    exit();
}

// verify file belongs to user (if files table exists)
try {
    $fstmt = $pdo->prepare('SELECT id, original_name FROM files WHERE id = ? AND user_id = ? LIMIT 1');
    $fstmt->execute([$file_id, $_SESSION['user_id']]);
    $frow = $fstmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $frow = false;
}
if (!$frow) {
    http_response_code(404);
    echo json_encode(['error' => 'file not found or you do not have permission']);
    exit();
}

// try to get an existing summary
$summary = '';
try {
    $sstmt = $pdo->prepare('SELECT summary FROM summaries WHERE file_id = ? ORDER BY created_at DESC LIMIT 1');
    $sstmt->execute([$file_id]);
    $srow = $sstmt->fetch(PDO::FETCH_ASSOC);
    if ($srow && !empty($srow['summary'])) $summary = $srow['summary'];
} catch (Exception $e) {
    // ignore
}

// If no summary or placeholder summary, try to extract from file directly
if (empty($summary) || strpos($summary, 'Summary placeholder') !== false || strpos($summary, 'No text extracted') !== false) {
    try {
        // Get file path
        $fstmt2 = $pdo->prepare('SELECT file_path FROM files WHERE id = ? LIMIT 1');
        $fstmt2->execute([$file_id]);
        $frow2 = $fstmt2->fetch(PDO::FETCH_ASSOC);
        if ($frow2 && file_exists($frow2['file_path'])) {
            $ext = strtolower(pathinfo($frow2['file_path'], PATHINFO_EXTENSION));
            $extracted = '';
            
            if ($ext === 'pptx') {
                $extracted = extractTextFromPPTX($frow2['file_path']);
                // Also try OCR on images if HF token available
                if (empty($extracted) || strlen($extracted) < 100) {
                    $imageText = extractImageTextFromPPTX($frow2['file_path'], $hf_token);
                    if (!empty($imageText)) {
                        $extracted .= ' ' . $imageText;
                    }
                }
            } elseif ($ext === 'docx') {
                $extracted = extractTextFromDOCX($frow2['file_path']);
            } elseif (in_array($ext, ['txt', 'md'])) {
                $extracted = file_get_contents($frow2['file_path']);
            }
            
            if (!empty($extracted)) {
                $summary = $extracted;
            }
        }
    } catch (Exception $e) {
        // ignore
    }
}

if (empty($summary)) {
    // no summary available — return an informative message
    echo json_encode(['error' => 'No content found for this file. Please try uploading a valid file with text content.']);
    exit();
}

// Helper functions for text extraction
function extractTextFromPPTX($filePath) {
    $text = '';
    try {
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) return '';
        
        $slideIndex = 1;
        while (true) {
            $slideFile = "ppt/slides/slide" . $slideIndex . ".xml";
            if ($zip->locateName($slideFile) === false) break;
            
            $xmlString = $zip->getFromName($slideFile);
            if ($xmlString === false) break;
            
            $xmlString = preg_replace('/<[a-z]+:([^>]*)>/i', '<\\1>', $xmlString);
            $xmlString = preg_replace('/<\/[a-z]+:([^>]*)>/i', '</\\1>', $xmlString);
            
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

function extractTextFromDOCX($filePath) {
    $text = '';
    try {
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) return '';
        
        $xmlString = $zip->getFromName('word/document.xml');
        if ($xmlString === false) {
            $zip->close();
            return '';
        }
        
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

function extractImageTextFromPPTX($filePath, $hfToken) {
    $text = '';
    if (empty($hfToken)) return '';
    
    try {
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) return '';
        
        $imageCount = 0;
        for ($i = 1; $i <= 100; $i++) {
            $imageData = null;
            
            // Try different image formats
            foreach (['png', 'jpeg', 'jpg'] as $format) {
                $imagePath = "ppt/media/image$i.$format";
                $imageData = $zip->getFromName($imagePath);
                if ($imageData !== false && !empty($imageData)) break;
            }
            
            if ($imageData !== false && !empty($imageData)) {
                $base64Image = base64_encode($imageData);
                $payload = ['inputs' => $base64Image];
                
                $model = 'microsoft/trocr-base-printed';
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
                    if (is_array($decoded)) {
                        if (isset($decoded['generated_text'])) {
                            $text .= $decoded['generated_text'] . ' ';
                        } elseif (isset($decoded[0]['generated_text'])) {
                            $text .= $decoded[0]['generated_text'] . ' ';
                        }
                    }
                }
                
                $imageCount++;
                if ($imageCount >= 10) break;
            }
        }
        
        $zip->close();
        return trim($text);
    } catch (Exception $e) {
        return '';
    }
}

// prepare prompt for HF model or fallback
$hf_token = defined('HF_API_TOKEN') ? HF_API_TOKEN : '';
$questions = [];

if (!empty($hf_token)) {
    // Use Hugging Face text-generation (Flan-T5) to produce JSON-formatted questions.
    $model = 'google/flan-t5-small'; // lightweight; change if you prefer another model
    // Stronger, more explicit prompt to improve output quality and force valid JSON
    $prompt = "You are an educational content generator.\n";
    $prompt .= "Generate exactly {$count} high-quality multiple-choice questions (A-D) from the text below.\n";
    $prompt .= "For each question provide: 'q' (the question text), 'options' (array of 4 answer choices), and 'answer' (the correct choice letter A-D).\n";
    $prompt .= "Return ONLY a JSON array, for example: [{\"q\":\"...\",\"options\":[\"..\",\"..\",\"..\",\"..\"],\"answer\":\"A\"}, ...] with no extra commentary.\n\n";
    $prompt .= "Text:\n" . $summary;

    $payload = ['inputs' => $prompt, 'options' => ['wait_for_model' => true]];
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
    $err = curl_error($ch);
    curl_close($ch);

    if ($resp === false || $code >= 400) {
        // hf call failed — fall back to rule-based
        $questions = null;
    } else {
        $decoded = json_decode($resp, true);
        // HF text generation often returns an array of {generated_text: "..."}
        $text = '';
        if (is_array($decoded) && isset($decoded[0]['generated_text'])) {
            $text = $decoded[0]['generated_text'];
        } elseif (is_string($resp)) {
            $text = $resp;
        }
        // try to extract JSON from text
        $start = strpos($text, '[');
        $end = strrpos($text, ']');
        if ($start !== false && $end !== false && $end > $start) {
            $jsonPart = substr($text, $start, $end - $start + 1);
            $maybe = json_decode($jsonPart, true);
            if (is_array($maybe)) {
                $questions = $maybe;
            } else {
                $questions = null;
            }
        } else {
            $questions = null;
        }
    }
}

if ($questions === null) {
    // fallback rule-based generator: pick top sentences as stems and generate simple choices
    // naive approach: split summary into sentences and craft simple distractors
    $sents = preg_split('/(?<=[.!?])\s+/', strip_tags($summary));
    $sents = array_values(array_filter(array_map('trim', $sents)));
    for ($i = 0; $i < $count; $i++) {
        $stem = $sents[$i] ?? ($sents[0] ?? 'Read the document and identify the main idea.');
        // create options by taking the stem and making small variations
        $correct = $stem;
        $opts = [$correct, 'Review the introduction', 'Skip to the conclusion', 'Check references'];
        shuffle($opts);
        $answer = 'A';
        // find correct letter
        foreach ($opts as $k => $v) if ($v === $correct) { $answer = chr(65 + $k); }
        $questions[] = ['q' => $stem, 'options' => $opts, 'answer' => $answer];
    }
}

// return JSON
echo json_encode(['file_id' => $file_id, 'file_name' => $frow['original_name'] ?? '', 'questions' => $questions]);

exit();
