<?php

// ---- Convert file size to format ----
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}


// ---- Utility: Extract metadata using ExifTool ----
function getExifMetadata($filePath) {
    $cmd = "exiftool -json " . escapeshellarg($filePath);
    $output = shell_exec($cmd);
    $parsed = json_decode($output, true);
    return $parsed[0] ?? null;
}

// ---- Utility: Extract metadata using ImageMagick ----
function getImageMagickMetadata($filePath) {
    $cmd = "identify -verbose " . escapeshellarg($filePath);
    $output = shell_exec($cmd);
    return $output ?: null;
}

// ---- Generic Metadata Extractor ----
function extractMetadata($filePath, $ext) {
    $metadata = null; // Initialize metadata as null
    switch ($ext) {
        case 'jpg':
        case 'jpeg':
        case 'png':
        case 'tiff':
        case 'bmp':
            $metadata = @exif_read_data($filePath, 'IFD0', true); // For common image formats
            break;
        case 'webp':
        case 'avif':
        case 'heic':
        case 'heif':
        case 'raw':
            $metadata = getExifMetadata($filePath); // Use ExifTool for more complex formats
            break;
        default:
            // fallback for other image types or document formats
            $metadata = getExifMetadata($filePath); // still try exiftool
            break;
    }
    return $metadata;
}
function cleanWithExiftool($inputPath, $outputPath) {
    // Use ExifTool to strip metadata and save cleaned version
    $cmd = "exiftool -all= -overwrite_original -out " . escapeshellarg($outputPath) . " " . escapeshellarg($inputPath);
    shell_exec($cmd);

    // Sometimes exiftool doesn't overwrite properly, fallback to copying
    if (!file_exists($outputPath)) {
        copy($inputPath, $outputPath);
    }
}

function anonymizeImage($inputPath, $outputPath, $ext = '') {
    $ext = strtolower($ext);

    // Handle formats supported by GD
    if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
        removeImageMetadata($inputPath, $outputPath);
        removeWatermark($outputPath, $outputPath);
        recompressImage($outputPath, $outputPath);
    }
    // Handle WebP, HEIC, AVIF, TIFF, etc.
    elseif (in_array($ext, ['webp', 'avif', 'heic', 'heif', 'tiff', 'bmp'])) {
        cleanWithExiftool($inputPath, $outputPath);
        removeWatermark($outputPath, $outputPath); // Optional, still run blur
    } else {
        error_log("Unsupported format for cleaning: $ext");
        copy($inputPath, $outputPath); // fallback: just copy
    }
}

function removeImageMetadata($filePath, $outputPath) {
    $imageType = exif_imagetype($filePath);

    if ($imageType == IMAGETYPE_JPEG) {
        $img = imagecreatefromjpeg($filePath);

        // This is for if image rotates while removing metadata
        $exif = @exif_read_data($filePath);
        if (!empty($exif['Orientation'])) {
            $orientation = $exif['Orientation'];

            switch ($orientation) {
                case 3:
                    $img = imagerotate($img, 180, 0);
                    break;
                case 6:
                    $img = imagerotate($img, -90, 0);
                    break;
                case 8:
                    $img = imagerotate($img, 90, 0);
                    break;
                // Orientation 1 = correct, so do nothing
            }
        }

        imagejpeg($img, $outputPath, 100); // 100 for returning max image
        imagedestroy($img);

    } elseif ($imageType == IMAGETYPE_PNG) {
        $img = imagecreatefrompng($filePath);
        imagepng($img, $outputPath);
        imagedestroy($img);
    }
}

// ---- Function to remove watermark (optional) ----
function removeWatermark($inputPath, $outputPath) {
    // Apply blur to the image to attempt watermark removal
    $cmd = "convert $inputPath -blur 0x8 $outputPath";
    shell_exec($cmd);
}

// ---- Function to recompress image (remove extra data) ----
function recompressImage($inputPath, $outputPath) {
    $imageType = exif_imagetype($inputPath);

    if ($imageType == IMAGETYPE_JPEG) {
        $image = imagecreatefromjpeg($inputPath);
        if ($image !== false) {
            imagejpeg($image, $outputPath, 90);
            imagedestroy($image);
        }
    } elseif ($imageType == IMAGETYPE_PNG) {
        $image = imagecreatefrompng($inputPath);
        if ($image !== false) {
            imagepng($image, $outputPath, 9); // Max compression for PNG
            imagedestroy($image);
        }
    } else {
        // Optional: handle unsupported formats more gracefully
        error_log("Unsupported image type for recompression: $inputPath");
    }
}

// ---- Generate a summary of metadata changes ----
function generateMetadataSummary($original, $cleaned) {
    $summary = "Original Metadata: \n" . print_r($original, true) . "\n\n";
    $summary .= "Cleaned Metadata: \n" . print_r($cleaned, true) . "\n\n";

    // Comparing metadata, highlighting differences
    $removedMetadata = array_diff_recursive($original, $cleaned);
    $summary .= "Removed Metadata:\n" . print_r($removedMetadata, true) . "\n\n";

    return $summary;
}

// ---- Recursive function to compare arrays (metadata comparison) ----
function array_diff_recursive($array1, $array2) {
    $diff = [];
    foreach ($array1 as $key => $value) {
        if (!array_key_exists($key, $array2)) {
            $diff[$key] = $value;
        } elseif (is_array($value)) {
            $newDiff = array_diff_recursive($value, $array2[$key]);
            if ($newDiff) {
                $diff[$key] = $newDiff;
            }
        } elseif ($value != $array2[$key]) {
            $diff[$key] = $value;
        }
    }
    return $diff;
}
echo "<button onclick='toggleAllDescriptions()' style='margin-bottom: 10px; padding: 6px 10px; font-size: 14px; cursor: pointer;'>🛈 Show/Hide All Descriptions</button>";
function displayMetadataAsHtml($metadata, $level = 0) {
    if (!is_array($metadata)) {
        return "<p>" . htmlspecialchars((string)$metadata) . "</p>";
    }

    $explanations = [
        'FileName' => 'The name of the uploaded file.',
        'FileSize' => 'The total size of the file.',
        'FileDateTime' => 'The last modified time of the file.',
        'FileType' => 'Numerical code representing the file type (e.g., 2 = JPEG).',
        'MimeType' => 'Media type used for describing the file format.',
        'SectionsFound' => 'Parts of the image where metadata was found (e.g., IFD0, EXIF, GPS).',
        'Orientation' => 'Specifies how the image should be displayed.',
        'Width' => 'Width of the image in pixels.',
        'Height' => 'Height of the image in pixels.',
        'IsColor' => 'Indicates if the image is in color (1 = yes).',
        'ByteOrderMotorola' => 'Defines how multi-byte data is stored.',
    ];

    $html = "";

    if ($level === 0) {
        $html .= "<div class='table-wrapper'>";
    }

    $html .= "<table class='metadata-table'>";

    // Only show headers in nested levels
    if ($level > 0) {
        $html .= "<tr>
                    <th style='padding:6px;'>Key</th>
                    <th style='padding:6px;'>Value</th>
                    <th class='description-column' style='padding:6px; display: none;'>Description</th>
                  </tr>";
    }

    foreach ($metadata as $key => $value) {
        $desc = isset($explanations[$key]) ? $explanations[$key] : '';
        $html .= "<tr>";

        $html .= "<td style='padding:6px; font-weight:bold; background:#f9f9f9;'>" . htmlspecialchars($key) . "</td>";

        if (is_array($value)) {
            $html .= "<td colspan='2'>" . displayMetadataAsHtml($value, $level + 1) . "</td>";
        } else {
            if (strtolower($key) === 'filesize') {
                $value = formatBytes((int)$value);
            } elseif (strtolower($key) === 'filedatetime') {
                $value = date("F j, Y, g:i a", (int)$value);
            }

            $html .= "<td style='padding:6px;'>" . htmlspecialchars((string)$value) . "</td>";
            $html .= "<td class='description-column' style='padding:6px; display:none;'>
                        <div class='description-box' style='font-style: italic; color: #666; font-size: 14px;'>" . htmlspecialchars($desc) . "</div>
                      </td>";
        }

        $html .= "</tr>";
    }

    $html .= "</table>";

    if ($level === 0) {
        $html .= "</div>"; // close table-wrapper
    }

    return $html;
}




// ---- Start handling the upload ----
if (isset($_FILES['image'])) {
    $uploadDir = 'uploads/';
    $cleanedDir = 'cleaned/';
    $logDir = 'logs/';
    $file = $_FILES['image'];

    // Check if image is uploaded successfully
    if ($file['error'] !== UPLOAD_ERR_OK) {
        die("Upload failed.");
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    // ✅ SUPPORTS all formats
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'tiff', 'bmp', 'webp', 'avif', 'heic', 'heif'])) {
        die("Only JPG, PNG  files are supported.");
    }

    $fileName = uniqid() . "." . $ext;
    $filePath = $uploadDir . $fileName;
    move_uploaded_file($file['tmp_name'], $filePath);

    // ---- Get file size and last modified date ----
    $fileSize = filesize($filePath); // Get file size in bytes
    $fileSizeFormatted = formatBytes($fileSize); // Convert file size to format

    $fileDateTime = filemtime($filePath); // Get last modified time
    $fileDateTimeFormatted = date("F j, Y, g:i a", $fileDateTime); // Format date to 
    // ---- Show and log metadata for image files ----
    $originalMetadata = [];
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'tiff', 'bmp', 'webp', 'avif', 'heic', 'heif'])) {
        $originalMetadata = extractMetadata($filePath, $ext) ?: ["No metadata found"];
    
        echo "<h3>📸 Original Metadata:</h3>";
        echo displayMetadataAsHtml($originalMetadata);

    } else {
        echo "<h3>📄 Uploaded File:</h3><p>$fileName</p>";
    }

    // ---- Log original metadata to a log file ----
    $logOriginalMetadata = "File: $fileName\n";
    $logOriginalMetadata .= "Size: $fileSizeFormatted\n";  // file size
    $logOriginalMetadata .= "Last Modified: $fileDateTimeFormatted\n\n";  // date
    $logOriginalMetadata .= print_r($originalMetadata, true);
    $logOriginalMetadata .= "\n\n";
    
    file_put_contents($logDir . 'original_metadata.txt', $logOriginalMetadata, FILE_APPEND);

    // ---- Clean the file ----
    $cleanPath = $cleanedDir . $fileName;

    anonymizeImage($filePath, $cleanPath, $ext);


    // ---- Log cleaned metadata ----
    $cleanedMetadata = [];
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'tiff', 'bmp', 'webp', 'avif', 'heic', 'heif'])) {
        $cleanedMetadata = extractMetadata($cleanPath, $ext) ?: ["No metadata found"];
    
    } 

    // Update the metadata with values
    $cleanedMetadata['FILE']['FileDateTime'] = $fileDateTimeFormatted; // date
    $cleanedMetadata['FILE']['FileSize'] = $fileSizeFormatted; // size

    $logCleanedMetadata = "File: $fileName\n";
    $logCleanedMetadata .= "Size: $fileSizeFormatted\n";  // file size
    $logCleanedMetadata .= "Last Modified: $fileDateTimeFormatted\n\n";  // date
    $logCleanedMetadata .= print_r($cleanedMetadata, true);
    $logCleanedMetadata .= "\n\n";
    
    file_put_contents($logDir . 'cleaned_metadata.txt', $logCleanedMetadata, FILE_APPEND);

    // ---- Create Summary of changes in updated_metadata.txt ----
    $summary = generateMetadataSummary($originalMetadata, $cleanedMetadata);

    file_put_contents($logDir . 'updated_metadata.txt', "File: $fileName\n", FILE_APPEND);
    file_put_contents($logDir . 'updated_metadata.txt', $summary, FILE_APPEND);
    file_put_contents($logDir . 'updated_metadata.txt', "\n\n", FILE_APPEND);

    $cleanedFileSize = filesize($cleanPath);
    $cleanedFileSizeFormatted = formatBytes($cleanedFileSize);
    
    // Convert metadata arrays to flat string for easier check
    $originalMetadataContent = is_array($originalMetadata) ? implode('', array_map('json_encode', $originalMetadata)) : '';
    
    // ✅ CASE: No metadata & cleaning increased size → don't show size comparison
    if ($cleanedFileSize > $fileSize && stripos($originalMetadataContent, 'No metadata found') !== false) {
        echo "<div style='color: green; border: 1px solid green; padding: 10px; margin-top: 10px;'>";
        echo "<strong>✅ Your image is already clean!</strong><br>";
        echo "No metadata was found in your original image, and processing further would have increased the file size.<br>";
        echo "<strong>Recommendation:</strong> Keep your original image — no changes are needed.";
        echo "</div>";
    
        // Remove cleaned file if you don't want to keep it
        if (file_exists($cleanPath)) {
            unlink($cleanPath);
        }
    
    } else {
        // file size comparison
        echo "<h4> File Size Comparison:</h4>";
        echo "<p><strong>Before:</strong> $fileSizeFormatted</p>";
        echo "<p><strong>After:</strong> $cleanedFileSizeFormatted</p>";
    
        if ($cleanedFileSize > $fileSize) {
            //  Metadata existed, but size increased
            echo "<div style='color: darkorange; border: 1px solid orange; padding: 10px; margin-top: 10px;'>";
            echo "<strong> Note:</strong> The cleaned file is <strong>larger</strong> than the original.<br>";
            echo "This can happen due to <em>image recompression</em> or <em>format re-encoding</em> after removing metadata.<br><br>";
            echo "<strong>Recommendations:</strong><br>";
            echo "- If <strong>file size</strong> is your priority, use the <em>original image</em>.<br>";
            echo "- If <strong>metadata removal</strong> is important, use the cleaned file.<br><br>";
            echo "<a href='$filePath' download>⬇️ Download Original File</a><br>";
            echo "<a href='$cleanPath' download>⬇️ Download Cleaned File</a>";
            echo "</div>";
        } else {
            // ✅ Normal case: cleaned is smaller
            echo "<h3>✅ Metadata Removed!</h3>";
            
if (in_array($ext, ['jpg', 'jpeg', 'png', 'tiff', 'bmp', 'webp', 'avif', 'heic', 'heif'])) {
    echo "<div class='cleaned-preview-box'>";
    echo "<div class='image-container'>";
    echo "<img src='$cleanPath' alt='Cleaned Image'>";
    echo "</div>";
    echo "</div>";
}

echo "<a href='$cleanPath' download class='download-btn'>⬇️ Download Cleaned File</a>";
        }
    }
}?>
