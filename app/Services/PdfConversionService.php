<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use PhpOffice\PhpWord\Settings as WordSettings;
use PhpOffice\PhpSpreadsheet\IOFactory as SpreadsheetIOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Pdf\Mpdf as SpreadsheetMpdf;
use PhpOffice\PhpSpreadsheet\Writer\Pdf\Tcpdf as SpreadsheetTcpdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class PdfConversionService
{
    /**
     * Conversion method preference
     * Options: 'libreoffice', 'native', 'dompdf'
     */
    private string $conversionMethod = 'native';

    /**
     * Constructor - auto-detect best conversion method
     */
    public function __construct()
    {
        // Auto-detect best available method
        if ($this->isLibreOfficeAvailable()) {
            $this->conversionMethod = 'libreoffice';
        } elseif (class_exists(\Mpdf\Mpdf::class) || class_exists(\TCPDF::class)) {
            $this->conversionMethod = 'native';
        } else {
            $this->conversionMethod = 'dompdf';
        }
    }

    /**
     * Set conversion method manually
     *
     * @param string $method 'libreoffice', 'native', or 'dompdf'
     */
    public function setConversionMethod(string $method): void
    {
        $this->conversionMethod = $method;
    }

    /**
     * Convert various file types to PDF
     *
     * @param string $sourcePath Full path to the source file
     * @param string $fileType File type (word, excel, image)
     * @param string $hashName Unique hash name for the file
     * @return string Path to the generated PDF
     */
    public function convertToPdf(string $sourcePath, string $fileType, string $hashName): string
    {
        $pdfPath = 'files/pdfs/' . $hashName . '.pdf';
        $fullPdfPath = Storage::disk('public')->path($pdfPath);

        // Ensure the directory exists
        $pdfDir = dirname($fullPdfPath);
        if (!file_exists($pdfDir)) {
            mkdir($pdfDir, 0755, true);
        }

        switch ($fileType) {
            case 'word':
                $this->convertWordToPdf($sourcePath, $fullPdfPath);
                break;
            case 'excel':
                $this->convertExcelToPdf($sourcePath, $fullPdfPath);
                break;
            case 'image':
                $this->convertImageToPdf($sourcePath, $fullPdfPath);
                break;
            default:
                throw new \Exception("Unsupported file type: {$fileType}");
        }

        return $pdfPath;
    }

    /**
     * Convert Word document to PDF with layout preservation
     */
    private function convertWordToPdf(string $sourcePath, string $pdfPath): void
    {
        try {
            // Try conversion methods in order of quality
            if ($this->conversionMethod === 'libreoffice' && $this->isLibreOfficeAvailable()) {
                $this->convertWithLibreOffice($sourcePath, $pdfPath);
                return;
            }

            // Use native PDF writer (better than HTML intermediate)
            if ($this->conversionMethod === 'native' || $this->conversionMethod === 'libreoffice') {
                try {
                    $this->convertWordWithNativePdf($sourcePath, $pdfPath);
                    return;
                } catch (\Exception $e) {
                    Log::warning("Native Word PDF conversion failed, falling back to HTML method: " . $e->getMessage());
                }
            }

            // Fallback to improved HTML method
            $this->convertWordWithHtml($sourcePath, $pdfPath);

        } catch (\Exception $e) {
            throw new \Exception("Failed to convert Word document to PDF: " . $e->getMessage());
        }
    }

    /**
     * Convert Word to PDF using native PDF writer (TCPDF/mPDF)
     */
    private function convertWordWithNativePdf(string $sourcePath, string $pdfPath): void
    {
        // Set PDF renderer to use mPDF or TCPDF
        $rendererName = WordSettings::PDF_RENDERER_MPDF;
        $rendererLibraryPath = base_path('vendor/mpdf/mpdf');

        // Check if mPDF exists, otherwise use TCPDF
        if (!is_dir($rendererLibraryPath)) {
            $rendererName = WordSettings::PDF_RENDERER_TCPDF;
            $rendererLibraryPath = base_path('vendor/tecnickcom/tcpdf');
        }

        if (!WordSettings::setPdfRenderer($rendererName, $rendererLibraryPath)) {
            throw new \Exception("Could not set PDF renderer for PhpWord");
        }

        $phpWord = WordIOFactory::load($sourcePath);

        // Create PDF writer with preserved formatting
        $pdfWriter = WordIOFactory::createWriter($phpWord, 'PDF');
        $pdfWriter->save($pdfPath);
    }

    /**
     * Convert Word to PDF using HTML (improved method with less CSS override)
     */
    private function convertWordWithHtml(string $sourcePath, string $pdfPath): void
    {
        $tempHtml = null;
        try {
            $phpWord = WordIOFactory::load($sourcePath);

            $htmlWriter = WordIOFactory::createWriter($phpWord, 'HTML');
            $tempHtml = tempnam(sys_get_temp_dir(), 'word_') . '.html';
            $htmlWriter->save($tempHtml);

            $html = file_get_contents($tempHtml);

            // Minimal CSS wrapper to preserve original styles
            $styledHtml = $this->wrapHtmlMinimal($html);
            $this->generatePdfFromHtml($styledHtml, $pdfPath);

        } finally {
            if ($tempHtml && file_exists($tempHtml)) {
                unlink($tempHtml);
            }
        }
    }

    /**
     * Minimal HTML wrapper that preserves original styles
     * (doesn't override the styles from PhpOffice libraries)
     */
    private function wrapHtmlMinimal(string $html): string
    {
        // Extract any existing <style> tags from the HTML
        preg_match_all('/<style[^>]*>(.*?)<\/style>/is', $html, $styleMatches);
        $existingStyles = implode("\n", $styleMatches[0]);

        // Remove existing html/head/body tags if present
        $html = preg_replace('/<\/?html[^>]*>/i', '', $html);
        $html = preg_replace('/<head[^>]*>.*?<\/head>/is', '', $html);
        $html = preg_replace('/<\/?body[^>]*>/i', '', $html);
        $html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);

        // Minimal CSS that doesn't override original formatting
        $minimalCss = '
        <style>
            * { box-sizing: border-box; }
            body {
                margin: 15px;
                font-size: 11pt;
            }
            img { max-width: 100%; height: auto; }
            @page { margin: 1cm; }
        </style>';

        $wrappedHtml = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    ' . $minimalCss . '
    ' . $existingStyles . '
</head>
<body>' . $html . '</body>
</html>';

        return $wrappedHtml;
    }

    /**
     * Convert Excel spreadsheet to PDF with layout preservation
     */
    private function convertExcelToPdf(string $sourcePath, string $pdfPath): void
    {
        try {
            // Try conversion methods in order of quality
            if ($this->conversionMethod === 'libreoffice' && $this->isLibreOfficeAvailable()) {
                $this->convertWithLibreOffice($sourcePath, $pdfPath);
                return;
            }

            // Use native PDF writer (MUCH better than HTML intermediate for Excel)
            if ($this->conversionMethod === 'native' || $this->conversionMethod === 'libreoffice') {
                try {
                    $this->convertExcelWithNativePdf($sourcePath, $pdfPath);
                    return;
                } catch (\Exception $e) {
                    Log::warning("Native Excel PDF conversion failed, falling back to HTML method: " . $e->getMessage());
                }
            }

            // Fallback to HTML method
            $this->convertExcelWithHtml($sourcePath, $pdfPath);

        } catch (\Exception $e) {
            throw new \Exception("Failed to convert Excel document to PDF: " . $e->getMessage());
        }
    }

    /**
     * Convert Excel to PDF using native PDF writer (mPDF/TCPDF) - PRESERVES LAYOUT
     */
    private function convertExcelWithNativePdf(string $sourcePath, string $pdfPath): void
    {
        $spreadsheet = SpreadsheetIOFactory::load($sourcePath);

        // Process all sheets or just active sheet
        $activeSheetIndex = 0;
        $spreadsheet->setActiveSheetIndex($activeSheetIndex);
        $sheet = $spreadsheet->getActiveSheet();

        // Set page setup to preserve layout
        $sheet->getPageSetup()
            ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4)
            ->setFitToPage(true)
            ->setFitToWidth(1)
            ->setFitToHeight(0); // Allow multiple pages vertically

        // Set print area to actual used range to avoid blank pages
        $highestRow = $sheet->getHighestDataRow();
        $highestColumn = $sheet->getHighestDataColumn();
        $sheet->getPageSetup()->setPrintArea("A1:{$highestColumn}{$highestRow}");

        // Preserve column widths and row heights
        $sheet->getPageSetup()->setHorizontalCentered(false);
        $sheet->getPageSetup()->setVerticalCentered(false);

        // Use Mpdf (better layout handling) or TCPDF as fallback
        $rendererLibraryPath = base_path('vendor/mpdf/mpdf');
        if (is_dir($rendererLibraryPath)) {
            \PhpOffice\PhpSpreadsheet\IOFactory::registerWriter('Pdf', SpreadsheetMpdf::class);
        } else {
            $rendererLibraryPath = base_path('vendor/tecnickcom/tcpdf');
            \PhpOffice\PhpSpreadsheet\IOFactory::registerWriter('Pdf', SpreadsheetTcpdf::class);
        }

        // Increase memory for large spreadsheets
        $originalMemoryLimit = ini_get('memory_limit');
        ini_set('memory_limit', '512M');

        try {
            // Create PDF writer
            $writer = SpreadsheetIOFactory::createWriter($spreadsheet, 'Pdf');

            // Configure writer to preserve formatting
            $writer->setPreCalculateFormulas(true);

            // Save to PDF
            $writer->save($pdfPath);
        } finally {
            ini_set('memory_limit', $originalMemoryLimit);
        }
    }

    /**
     * Convert Excel to PDF using HTML (fallback method)
     */
    private function convertExcelWithHtml(string $sourcePath, string $pdfPath): void
    {
        $tempHtml = null;
        try {
            $spreadsheet = SpreadsheetIOFactory::load($sourcePath);
            $spreadsheet->setActiveSheetIndex(0);

            $htmlWriter = SpreadsheetIOFactory::createWriter($spreadsheet, 'Html');
            $tempHtml = tempnam(sys_get_temp_dir(), 'excel_') . '.html';
            $htmlWriter->save($tempHtml);

            $html = file_get_contents($tempHtml);

            // Minimal CSS wrapper to preserve original styles
            $styledHtml = $this->wrapHtmlMinimal($html);
            $this->generatePdfFromHtml($styledHtml, $pdfPath, 'landscape');

        } finally {
            if ($tempHtml && file_exists($tempHtml)) {
                unlink($tempHtml);
            }
        }
    }

    /**
     * Convert using LibreOffice headless (BEST quality - preserves exact layout)
     */
    private function convertWithLibreOffice(string $sourcePath, string $pdfPath): void
    {
        $command = sprintf(
            'soffice --headless --convert-to pdf --outdir %s %s 2>&1',
            escapeshellarg(dirname($pdfPath)),
            escapeshellarg($sourcePath)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \Exception("LibreOffice conversion failed: " . implode("\n", $output));
        }

        // LibreOffice creates PDF with same base name
        $generatedPdf = dirname($pdfPath) . '/' . pathinfo($sourcePath, PATHINFO_FILENAME) . '.pdf';

        if (file_exists($generatedPdf) && $generatedPdf !== $pdfPath) {
            rename($generatedPdf, $pdfPath);
        }

        if (!file_exists($pdfPath)) {
            throw new \Exception("LibreOffice did not generate PDF file");
        }
    }

    /**
     * Check if LibreOffice is available
     */
    private function isLibreOfficeAvailable(): bool
    {
        exec('which soffice 2>&1', $output, $returnCode);
        return $returnCode === 0;
    }

    /**
     * Convert image to PDF
     */
    private function convertImageToPdf(string $sourcePath, string $pdfPath): void
    {
        try {
            $imageInfo = getimagesize($sourcePath);

            if (!$imageInfo) {
                throw new \Exception("Invalid image file");
            }

            $width = $imageInfo[0];
            $height = $imageInfo[1];
            $mimeType = $imageInfo['mime'];

            // Determine orientation based on image dimensions
            $orientation = ($width > $height) ? 'landscape' : 'portrait';

            // Optimize image size for PDF - reduce memory usage
            $optimizedImage = $this->optimizeImageForPdf($sourcePath, $mimeType, $width, $height);

            $html = '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <style>
                    * { margin: 0; padding: 0; }
                    html, body {
                        width: 100%;
                        height: 100%;
                    }
                    body {
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        padding: 10px;
                    }
                    img {
                        max-width: 100%;
                        max-height: 100%;
                        height: auto;
                        width: auto;
                        display: block;
                    }
                </style>
            </head>
            <body>
                <img src="data:' . $mimeType . ';base64,' . $optimizedImage . '" alt="Image" />
            </body>
            </html>';

            $this->generatePdfFromHtml($html, $pdfPath, $orientation);
        } catch (\Exception $e) {
            throw new \Exception("Failed to convert image to PDF: " . $e->getMessage());
        }
    }

    /**
     * Optimize image for PDF conversion to avoid memory issues
     */
    private function optimizeImageForPdf(string $sourcePath, string $mimeType, int $width, int $height): string
    {
        // Max dimensions to prevent memory issues
        $maxWidth = 2000;
        $maxHeight = 2000;

        // If image is small enough, just encode it
        if ($width <= $maxWidth && $height <= $maxHeight) {
            return base64_encode(file_get_contents($sourcePath));
        }

        // Calculate new dimensions while maintaining aspect ratio
        $ratio = min($maxWidth / $width, $maxHeight / $height);
        $newWidth = intval($width * $ratio);
        $newHeight = intval($height * $ratio);

        // Create new image resource
        $sourceImage = null;
        switch ($mimeType) {
            case 'image/jpeg':
                $sourceImage = @imagecreatefromjpeg($sourcePath);
                break;
            case 'image/png':
                $sourceImage = @imagecreatefrompng($sourcePath);
                break;
            case 'image/gif':
                $sourceImage = @imagecreatefromgif($sourcePath);
                break;
            case 'image/webp':
                $sourceImage = @imagecreatefromwebp($sourcePath);
                break;
            case 'image/bmp':
            case 'image/x-ms-bmp':
                $sourceImage = @imagecreatefrombmp($sourcePath);
                break;
        }

        // If we can't create resource, return original
        if (!$sourceImage) {
            return base64_encode(file_get_contents($sourcePath));
        }

        // Create resized image
        $resizedImage = imagecreatetruecolor($newWidth, $newHeight);

        // Preserve transparency for PNG and GIF
        if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
            imagealphablending($resizedImage, false);
            imagesavealpha($resizedImage, true);
            $transparent = imagecolorallocatealpha($resizedImage, 255, 255, 255, 127);
            imagefilledrectangle($resizedImage, 0, 0, $newWidth, $newHeight, $transparent);
        }

        // Resize
        imagecopyresampled($resizedImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        // Output to buffer
        ob_start();
        switch ($mimeType) {
            case 'image/jpeg':
                imagejpeg($resizedImage, null, 90);
                break;
            case 'image/png':
                imagepng($resizedImage, null, 8);
                break;
            case 'image/gif':
                imagegif($resizedImage);
                break;
            case 'image/webp':
                imagewebp($resizedImage, null, 90);
                break;
            default:
                imagejpeg($resizedImage, null, 90);
        }
        $imageData = ob_get_clean();

        // Free memory
        imagedestroy($sourceImage);
        imagedestroy($resizedImage);

        return base64_encode($imageData);
    }

    /**
     * Generate PDF from HTML using Dompdf with optimized settings
     */
    private function generatePdfFromHtml(string $html, string $pdfPath, string $orientation = 'portrait'): void
    {
        try {
            $options = new Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $options->set('defaultFont', 'Arial');
            $options->set('isFontSubsettingEnabled', true);
            $options->set('defaultMediaType', 'print');
            $options->set('isPhpEnabled', false);

            // Enable better image handling
            $options->set('isImageEnabled', true);
            $options->set('chroot', realpath(base_path()));

            // Set DPI for better quality
            $options->set('dpi', 150);

            // Enable better font rendering
            $options->set('fontHeightRatio', 1.1);

            $dompdf = new Dompdf($options);

            // Set memory limit for large conversions
            $originalMemoryLimit = ini_get('memory_limit');
            ini_set('memory_limit', '512M');

            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', $orientation);
            $dompdf->render();

            $output = $dompdf->output();

            // Validate PDF output
            if (empty($output) || strlen($output) < 100) {
                throw new \Exception("Generated PDF is empty or corrupted");
            }

            file_put_contents($pdfPath, $output);

            // Restore original memory limit
            ini_set('memory_limit', $originalMemoryLimit);

            // Verify file was created successfully
            if (!file_exists($pdfPath) || filesize($pdfPath) < 100) {
                throw new \Exception("Failed to write PDF file");
            }
        } catch (\Exception $e) {
            // Restore memory limit on error
            if (isset($originalMemoryLimit)) {
                ini_set('memory_limit', $originalMemoryLimit);
            }
            throw new \Exception("PDF generation failed: " . $e->getMessage());
        }
    }

    /**
     * Determine file type from extension
     */
    public function getFileType(string $extension): ?string
    {
        $extension = strtolower($extension);

        $pdfExtensions = ['pdf'];
        $wordExtensions = ['doc', 'docx', 'rtf', 'odt'];
        $excelExtensions = ['xls', 'xlsx', 'csv', 'ods'];
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];

        if (in_array($extension, $pdfExtensions)) {
            return 'pdf';
        } elseif (in_array($extension, $wordExtensions)) {
            return 'word';
        } elseif (in_array($extension, $excelExtensions)) {
            return 'excel';
        } elseif (in_array($extension, $imageExtensions)) {
            return 'image';
        }

        return null;
    }
}
