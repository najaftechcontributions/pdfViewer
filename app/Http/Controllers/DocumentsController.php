<?php

namespace App\Http\Controllers;

use App\Document;
use App\Http\Requests\CreateDocumentRequest;
use App\Services\PdfConversionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class DocumentsController extends Controller
{
    private PdfConversionService $pdfConversionService;

    public function __construct(PdfConversionService $pdfConversionService)
    {
        $this->pdfConversionService = $pdfConversionService;
    }

    public function index(): View
    {
        $documents = Document::latest()->simplePaginate(20);

        return view('documents')->with('documents', $documents);
    }

    public function create(CreateDocumentRequest $request): RedirectResponse
    {
        try {
            $file = $request->file('document');
            $fileName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();

            // Determine file type
            $fileType = $this->pdfConversionService->getFileType($extension);

            if (!$fileType) {
                return redirect(route('documents.index'))
                    ->with('error', 'Unsupported file type');
            }

            // Generate unique hash name
            $hashName = substr($file->hashName(), 0, -strlen($extension) - 1);

            // Store original file
            $documentPath = $file->storeAs(
                'files/documents',
                $hashName . '.' . $extension,
                'public'
            );

            // Convert to PDF (skip if already PDF)
            if ($fileType === 'pdf') {
                // For PDF files, use the same path for both original and PDF
                $pdfPath = $documentPath;
            } else {
                // Convert other file types to PDF
                $sourcePath = Storage::disk('public')->path($documentPath);
                $pdfPath = $this->pdfConversionService->convertToPdf($sourcePath, $fileType, $hashName);
            }

            // Create document record
            $document = Document::create([
                'name' => $fileName,
                'path' => $documentPath,
                'pdf_path' => $pdfPath,
                'file_type' => $fileType,
            ]);

            return redirect(route('documents.index'))
                ->with('success', 'Document uploaded and converted successfully!');

        } catch (\Exception $e) {
            return redirect(route('documents.index'))
                ->with('error', 'Error uploading document: ' . $e->getMessage());
        }
    }

    public function destroy(Document $document): RedirectResponse
    {
        $document->delete();

        return redirect(route('documents.index'))
            ->with('success', 'Document deleted successfully!');
    }
}
