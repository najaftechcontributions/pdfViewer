# PDF Conversion Service - Standalone Usage Guide

This guide explains how to use the `PdfConversionService` class in any PHP application (Laravel, Symfony, standalone, etc.).

## Features

- **Word to PDF**: Converts via HTML → Images → PDF for **best design preservation**
- **Excel to PDF**: Preserves formatting, column widths, and styling
- **Image to PDF**: Optimizes and converts images to PDF
- **Standalone**: Works with or without Laravel
- **Flexible**: Multiple conversion methods with automatic fallback

## Requirements

### Required PHP Extensions
- PHP 7.4 or higher
- `gd` or `imagick` extension for image handling
- `zip` extension for Word/Excel files
- `xml` extension

### Required Composer Packages

```bash
composer require phpoffice/phpword
composer require phpoffice/phpspreadsheet
composer require dompdf/dompdf
composer require spatie/browsershot
```

### Optional (for better quality)
```bash
# For best quality PDF generation
composer require mpdf/mpdf
# OR
composer require tecnickcom/tcpdf

# For Browsershot (image-based conversion)
npm install -g puppeteer
```

### Optional: LibreOffice
For best quality Word/Excel conversions, install LibreOffice:
- Ubuntu/Debian: `sudo apt-get install libreoffice`
- macOS: `brew install libreoffice`
- Windows: Download from https://www.libreoffice.org/

## Basic Usage

### 1. Copy the Converter Class

Copy `app/Services/PdfConversionService.php` to your project.

### 2. Simple Conversion

```php
<?php

require 'vendor/autoload.php';

use App\Services\PdfConversionService;

// Create converter instance
$converter = new PdfConversionService();

// Convert a file
$pdfPath = $converter->convert(
    '/path/to/document.docx',  // Source file
    '/path/to/output.pdf'      // Output PDF path
);

echo "PDF created: " . $pdfPath;
```

### 3. With Options

```php
<?php

use App\Services\PdfConversionService;

// Create converter with options
$converter = new PdfConversionService([
    'method' => 'image-based',  // Preferred conversion method
    'logging' => true,           // Enable logging
    'logger' => function($message, $level) {
        // Custom logger
        error_log("[$level] $message");
    }
]);

// Convert with explicit file type
$pdfPath = $converter->convert(
    '/path/to/document.docx',
    '/path/to/output.pdf',
    'word'  // Optional: specify file type
);
```

## Conversion Methods

The service supports multiple conversion methods (in order of quality for Word files):

1. **image-based** (Default, Best quality)
   - Word → HTML → Images → PDF
   - Best design preservation
   - Requires Browsershot & Puppeteer

2. **libreoffice**
   - Uses LibreOffice headless mode
   - Excellent quality
   - Requires LibreOffice installed

3. **native**
   - Uses PhpWord/PhpSpreadsheet native PDF writers
   - Good quality
   - Requires mpdf or tcpdf

4. **dompdf**
   - Fallback option
   - Basic quality
   - No additional dependencies

## Supported File Types

### Word Documents
- `.doc` - Microsoft Word 97-2003
- `.docx` - Microsoft Word 2007+
- `.rtf` - Rich Text Format
- `.odt` - OpenDocument Text

### Excel Spreadsheets
- `.xls` - Microsoft Excel 97-2003
- `.xlsx` - Microsoft Excel 2007+
- `.csv` - Comma Separated Values
- `.ods` - OpenDocument Spreadsheet

### Images
- `.jpg`, `.jpeg` - JPEG images
- `.png` - PNG images
- `.gif` - GIF images
- `.bmp` - Bitmap images
- `.webp` - WebP images

## Advanced Usage

### Auto-detect File Type

```php
$converter = new PdfConversionService();

// File type is automatically detected from extension
$pdfPath = $converter->convert(
    '/path/to/document.docx',
    '/path/to/output.pdf'
    // No need to specify 'word' - auto-detected!
);
```

### Disable Logging

```php
$converter = new PdfConversionService([
    'logging' => false
]);
```

### Custom Logger Integration

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$log = new Logger('converter');
$log->pushHandler(new StreamHandler('conversion.log', Logger::WARNING));

$converter = new PdfConversionService([
    'logger' => function($message, $level) use ($log) {
        $log->$level($message);
    }
]);
```

### Set Conversion Method

```php
$converter = new PdfConversionService();

// Force a specific conversion method
$converter->setConversionMethod('libreoffice');

$pdfPath = $converter->convert(
    '/path/to/document.docx',
    '/path/to/output.pdf'
);
```

## Laravel Integration

If using with Laravel, the service integrates seamlessly:

```php
use App\Services\PdfConversionService;
use Illuminate\Support\Facades\Storage;

$converter = new PdfConversionService();

$file = $request->file('document');
$sourcePath = Storage::disk('public')->path($file->store('documents'));

// Option 1: Use standalone convert() method
$pdfPath = $converter->convert(
    $sourcePath,
    storage_path('app/public/pdfs/output.pdf')
);

// Option 2: Use Laravel-specific convertToPdf() method
$pdfPath = $converter->convertToPdf(
    $sourcePath,
    'word',
    'unique-hash-name'
);
```

## Troubleshooting

### "Image-based conversion failed"
- Install Puppeteer: `npm install -g puppeteer`
- Or use a different method: `$converter->setConversionMethod('libreoffice');`

### "LibreOffice conversion failed"
- Ensure LibreOffice is installed and `soffice` is in PATH
- Check permissions: LibreOffice needs write access to temp directory

### "Memory limit exhausted"
- Increase PHP memory limit in php.ini: `memory_limit = 512M`
- Or set in code: `ini_set('memory_limit', '512M');`

### Large file conversions are slow
- This is normal for image-based conversion (best quality)
- For faster conversion, use: `$converter->setConversionMethod('native');`
- Or use LibreOffice for good balance of speed and quality

## Example: Batch Conversion

```php
<?php

use App\Services\PdfConversionService;

$converter = new PdfConversionService();

$files = [
    '/path/to/document1.docx',
    '/path/to/spreadsheet.xlsx',
    '/path/to/image.jpg'
];

foreach ($files as $file) {
    $outputPath = str_replace(
        pathinfo($file, PATHINFO_EXTENSION),
        'pdf',
        $file
    );
    
    try {
        $converter->convert($file, $outputPath);
        echo "✓ Converted: " . basename($file) . "\n";
    } catch (\Exception $e) {
        echo "✗ Failed: " . basename($file) . " - " . $e->getMessage() . "\n";
    }
}
```

## License

This converter service is provided as-is for use in your applications.
