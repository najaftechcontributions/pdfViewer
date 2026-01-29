<?php

namespace App\Http\Controllers;

use App\Document;
use App\Http\Requests\CreateDocumentRequest;
use App\Services\PdfConversionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Document Controller
 *
 * Handles document upload, PDF conversion, and viewing.
 * Uses PdfConversionService for converting Word, Excel, and Image files to PDF.
 */
class DocumentsController extends Controller
{
    private PdfConversionService $pdfConversionService;

    public function __construct(PdfConversionService $pdfConversionService)
    {
        $this->pdfConversionService = $pdfConversionService;
    }

    /**
     * Display document list view
     */
    public function index(): View
    {
        $documents = Document::latest()->simplePaginate(20);
        return view('documents')->with('documents', $documents);
    }

    /**
     * Upload and convert document to PDF
     *
     * Supports: Word (.doc, .docx), Excel (.xls, .xlsx), Images (.jpg, .png, etc.)
     * Uses image-based conversion for Word files for best design preservation
     */
    public function create(CreateDocumentRequest $request): RedirectResponse
    {
        try {
            $file = $request->file('document');
            $fileName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();

            // Auto-detect file type from extension
            $fileType = $this->pdfConversionService->getFileType($extension);

            if (!$fileType) {
                return redirect(route('documents.index'))
                    ->with('error', 'Unsupported file type: ' . $extension);
            }

            // Generate unique hash name
            $hashName = substr($file->hashName(), 0, -strlen($extension) - 1);

            // Store original file
            $documentPath = $file->storeAs(
                'files/documents',
                $hashName . '.' . $extension,
                'public'
            );

            // Convert to PDF using image-based method for better design preservation
            if ($fileType === 'pdf') {
                $pdfPath = $documentPath;
            } else {
                $sourcePath = Storage::disk('public')->path($documentPath);
                $pdfPath = $this->pdfConversionService->convertToPdf($sourcePath, $fileType, $hashName);
            }

            // Save document record
            Document::create([
                'name' => $fileName,
                'path' => $documentPath,
                'pdf_path' => $pdfPath,
                'file_type' => $fileType,
            ]);

            return redirect(route('documents.index'))
                ->with('success', 'Document uploaded and converted to PDF successfully!');

        } catch (\Exception $e) {
            return redirect(route('documents.index'))
                ->with('error', 'Conversion failed: ' . $e->getMessage());
        }
    }

    /**
     * Delete document and its files
     */
    public function destroy(Document $document): RedirectResponse
    {
        $document->delete();
        return redirect(route('documents.index'))
            ->with('success', 'Document deleted successfully!');
    }
}
