# OCR Solution for PPTX Files with Images

## Problem
The quiz generator was only extracting text from slide XML, missing text that appears in images on the slides.

## Solution Implemented
Added **OCR (Optical Character Recognition)** using Hugging Face's TrOCR model to:
1. Extract slide text from PPTX XML (existing text)
2. Extract images from the PPTX file
3. Send images to HF TrOCR model for text recognition
4. Combine both sources for complete content

## How It Works

### Text Extraction Flow
```
Upload PPTX
    ↓
Extract XML text → "This is slide text"
    ↓
Extract images → image1.png, image2.png
    ↓
Send to HF TrOCR → "Text from image1", "Text from image2"
    ↓
Combine → "This is slide text Text from image1 Text from image2"
    ↓
Store in summaries table
    ↓
Use for quiz generation
```

### Files Modified

#### 1. `api/upload_and_summarize.php`
- Added `extractImageTextFromPPTX()` function
- Extracts images from `ppt/media/` folder in PPTX ZIP
- Sends base64-encoded images to HF TrOCR API
- Combines extracted text with XML text

#### 2. `api/generate_quiz.php`
- Added same OCR functions as fallback
- If stored summary is incomplete or placeholder, extracts directly from file
- Uses OCR to get image text when XML text is insufficient

## Supported Formats
- **PPTX**: Extracts from slides + images using OCR
- **DOCX**: Extracts from document XML
- **TXT/MD**: Direct file read

## How to Test

### Test 1: Upload PPTX with Images
1. Log in
2. Click "Generate Mock Quiz"
3. Upload a PPTX file with text AND images
4. Click "Upload File"
5. Check browser console (F12) for any errors
6. Select file and click "Generate Quiz"
7. Quiz should include content from both text and images

### Test 2: Monitor OCR Calls
1. Open DevTools (F12)
2. Go to Network tab
3. Generate quiz
4. Look for API calls to HF:
   - `api-inference.huggingface.co/models/microsoft/trocr-base-printed` (OCR calls)
   - `api-inference.huggingface.co/models/google/flan-t5-small` (Quiz generation)

## API Details

### Hugging Face Models Used

**TrOCR (Tesseract OCR)**
```
Model: microsoft/trocr-base-printed
Input: Base64-encoded image (PNG, JPEG, JPG)
Output: { "generated_text": "Recognized text from image" }
```

**Flan-T5 (Quiz Generation)**
```
Model: google/flan-t5-small
Input: Prompt + text content
Output: { "generated_text": "[{\"q\":\"...\", \"options\":[...], \"answer\":\"...\"}]" }
```

## Limitations & Notes

### Rate Limiting
- HF API free tier has rate limits
- Each PPTX can extract up to 10 images (configurable)
- If you hit rate limits, wait a few minutes and retry

### Image Requirements
- Images must contain readable text
- Handwriting may not be recognized well
- Very small text may be hard to extract
- Low-quality images may produce poor results

### File Size
- Extracting text from 10+ images + summarizing takes time
- Typical time: 10-30 seconds per file
- Very large PPTX files may timeout

### Fallback Behavior
- If HF API is down, uses only XML text
- If XML text is empty, shows error message
- Rule-based generator activates if HF quiz generation fails

## Troubleshooting

### No text extracted from images
- Images might be low quality
- Images might be just graphics (no text)
- Try uploading a different file

### Slow upload/generation
- HF servers might be busy
- Images are being processed (normal delay)
- Wait 30-60 seconds for response

### "No content found" error
- PPTX has no text in slides or images
- File might be corrupted
- Try uploading a different file

### Quiz has placeholder content
- Combined text extraction returned empty results
- File content is not readable
- Check that PPTX has actual text/readable images

## Future Improvements
- Add PDF OCR support using similar method
- Add configurable image count limit
- Add progress bar for image extraction
- Cache OCR results to avoid re-processing
- Support for handwriting recognition (Premium HF models)

## Configuration
To disable OCR (use only XML text):
1. Comment out the OCR function calls
2. Or set `HF_API_TOKEN` to empty string

To increase image limit:
- Modify `if ($imageCount >= 10) break;` to a higher number
- Note: This will increase processing time and API costs
