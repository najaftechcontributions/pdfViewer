<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <script src="{{ asset('js/app.js') }}"></script>
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    <title>Document Manager</title>
</head>
<body>

<!-- PDF Viewer Modal -->
<div class="modal fade" id="pdf-viewer-modal">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">PDF Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <object id="pdf-viewer" type="application/pdf" data="" width="100%" height="100%">
                    <p>Unable to display PDF. <a id="pdf-download-link" href="">Download PDF</a></p>
                </object>
            </div>
        </div>
    </div>
</div>

<div class="page-wrapper">
    <div class="container">
        <!-- Header Section -->
        <header class="page-header">
            <h1 class="page-title">📁 Document Manager</h1>
            <p class="page-subtitle">Upload PDF, Word, Excel, or Image files</p>
        </header>

        <!-- Upload Section -->
        <div class="upload-section">
            <form id="upload-form" action="{{ route('documents.create') }}" enctype="multipart/form-data" method="post">
                @csrf
                <div class="upload-area">
                    <div class="upload-icon">☁️</div>
                    <h3 class="upload-title">Drop your file here or click to browse</h3>
                    <p class="upload-description">Supports: PDF, Word, Excel, Images (JPG, PNG, GIF, etc.)</p>
                    <p class="upload-size">Maximum file size: 20MB</p>
                    <input
                        type="file"
                        name="document"
                        id="file-input"
                        accept=".pdf,.doc,.docx,.rtf,.odt,.xls,.xlsx,.csv,.ods,.jpg,.jpeg,.png,.gif,.bmp,.webp"
                        required
                    >
                    <button type="button" class="upload-button" id="choose-file-btn">
                        Choose File
                    </button>
                </div>
            </form>

            @if(session('success'))
                <div class="alert alert-success">
                    ✓ {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-error">
                    ✗ {{ session('error') }}
                </div>
            @endif

            @error('document')
                <div class="alert alert-error">
                    ✗ {{ $message }}
                </div>
            @enderror
        </div>

        <!-- Documents Grid -->
        @if(count($documents) == 0)
            <div class="empty-state">
                <div class="empty-icon">📂</div>
                <h2 class="empty-title">No documents yet</h2>
                <p class="empty-description">Upload your first document to get started</p>
            </div>
        @else
            <div class="documents-grid">
                @foreach($documents as $document)
                    <div class="document-card">
                        <div class="document-type-badge">
                            {{ $document->getFileIcon() }}
                        </div>

                        <form class="delete-form" action="{{ route('documents.destroy', $document) }}" method="post" onsubmit="return confirm('Are you sure you want to delete this document?')">
                            @csrf
                            @method('delete')
                            <button type="submit" class="delete-button" title="Delete document">×</button>
                        </form>

                        <div class="document-preview">
                            <div class="file-type-icon">{{ $document->getFileIcon() }}</div>
                            <div class="document-info">
                                <p class="file-type-label">{{ strtoupper($document->file_type) }}</p>
                            </div>
                        </div>

                        <div class="document-details">
                            <h3 class="document-name" title="{{ $document->name }}">{{ $document->name }}</h3>
                            <p class="document-meta">Uploaded {{ $document->created_at->diffForHumans() }}</p>
                        </div>

                        <div class="document-actions">
                            <a href="{{ $document->getOriginalFileUrl() }}"
                               class="action-button original-button"
                               download="{{ $document->name }}"
                               title="Download original file">
                                <span class="action-icon">📥</span>
                                <span class="action-label">Original</span>
                            </a>

                            <button
                                class="action-button pdf-button"
                                data-bs-toggle="modal"
                                data-bs-target="#pdf-viewer-modal"
                                data-pdf-url="{{ $document->getPdfUrl() }}"
                                title="View PDF">
                                <span class="action-icon">📄</span>
                                <span class="action-label">View PDF</span>
                            </button>

                            <a href="{{ $document->getPdfUrl() }}"
                               class="action-button download-button"
                               download="{{ pathinfo($document->name, PATHINFO_FILENAME) }}.pdf"
                               title="Download PDF">
                                <span class="action-icon">⬇️</span>
                                <span class="action-label">PDF</span>
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Pagination -->
            <div class="pagination-wrapper">
                {{ $documents->links() }}
            </div>
        @endif
    </div>
</div>

<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        padding: 20px 0;
    }

    .page-wrapper {
        max-width: 1400px;
        margin: 0 auto;
        padding: 0 20px;
    }

    .container {
        width: 100%;
    }

    /* Header */
    .page-header {
        text-align: center;
        margin-bottom: 40px;
        color: white;
    }

    .page-title {
        font-size: 2.5rem;
        font-weight: 700;
        margin-bottom: 10px;
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
    }

    .page-subtitle {
        font-size: 1.1rem;
        opacity: 0.95;
    }

    /* Upload Section */
    .upload-section {
        margin-bottom: 40px;
    }

    .upload-area {
        background: white;
        border-radius: 16px;
        padding: 60px 40px;
        text-align: center;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
        border: 3px dashed #e0e0e0;
    }

    .upload-area:hover {
        border-color: #667eea;
        transform: translateY(-2px);
        box-shadow: 0 15px 50px rgba(0, 0, 0, 0.15);
    }

    .upload-area.drag-over {
        border-color: #764ba2;
        background: #f8f5ff;
        transform: scale(1.02);
        box-shadow: 0 20px 60px rgba(118, 75, 162, 0.3);
    }

    .upload-icon {
        font-size: 4rem;
        margin-bottom: 20px;
    }

    .upload-title {
        font-size: 1.5rem;
        font-weight: 600;
        color: #333;
        margin-bottom: 10px;
    }

    .upload-description {
        color: #666;
        font-size: 1rem;
        margin-bottom: 5px;
    }

    .upload-size {
        color: #999;
        font-size: 0.9rem;
        margin-bottom: 20px;
    }

    #file-input {
        display: none;
    }

    .upload-button {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        padding: 14px 40px;
        font-size: 1.1rem;
        font-weight: 600;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    }

    .upload-button:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
    }

    /* Alerts */
    .alert {
        padding: 15px 20px;
        border-radius: 8px;
        margin-top: 20px;
        font-weight: 500;
    }

    .alert-success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .alert-error {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 80px 20px;
        color: white;
    }

    .empty-icon {
        font-size: 6rem;
        margin-bottom: 20px;
        opacity: 0.8;
    }

    .empty-title {
        font-size: 2rem;
        font-weight: 600;
        margin-bottom: 10px;
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
    }

    .empty-description {
        font-size: 1.1rem;
        opacity: 0.9;
    }

    /* Documents Grid */
    .documents-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 24px;
        margin-bottom: 40px;
    }

    .document-card {
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
        position: relative;
    }

    .document-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    }

    .document-type-badge {
        position: absolute;
        top: 12px;
        right: 12px;
        font-size: 1.5rem;
        background: rgba(255, 255, 255, 0.95);
        padding: 8px;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        z-index: 2;
    }

    .delete-form {
        position: absolute;
        top: 12px;
        left: 12px;
        z-index: 2;
    }

    .delete-button {
        background: rgba(220, 53, 69, 0.95);
        color: white;
        border: none;
        width: 32px;
        height: 32px;
        border-radius: 6px;
        font-size: 1.5rem;
        line-height: 1;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0;
    }

    .delete-button:hover {
        background: rgba(200, 35, 51, 1);
        transform: scale(1.1);
    }

    .document-preview {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        height: 180px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        position: relative;
    }

    .file-type-icon {
        font-size: 4rem;
        margin-bottom: 10px;
    }

    .file-type-label {
        background: rgba(255, 255, 255, 0.95);
        color: #667eea;
        padding: 6px 16px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.85rem;
        letter-spacing: 0.5px;
    }

    .document-details {
        padding: 16px;
        background: #f8f9fa;
    }

    .document-name {
        font-size: 1rem;
        font-weight: 600;
        color: #333;
        margin-bottom: 6px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .document-meta {
        font-size: 0.85rem;
        color: #666;
    }

    .document-actions {
        display: flex;
        border-top: 1px solid #e9ecef;
    }

    .action-button {
        flex: 1;
        padding: 12px 8px;
        background: white;
        border: none;
        border-right: 1px solid #e9ecef;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 4px;
        text-decoration: none;
        color: inherit;
    }

    .action-button:last-child {
        border-right: none;
    }

    .action-button:hover {
        background: #f8f9fa;
    }

    .action-icon {
        font-size: 1.25rem;
    }

    .action-label {
        font-size: 0.75rem;
        font-weight: 500;
        color: #666;
    }

    .original-button:hover {
        background: #e7f3ff;
    }

    .pdf-button:hover {
        background: #fff3e0;
    }

    .download-button:hover {
        background: #e8f5e9;
    }

    /* Pagination */
    .pagination-wrapper {
        display: flex;
        justify-content: center;
        margin-top: 40px;
    }

    /* Modal */
    .modal-fullscreen .modal-body {
        padding: 0;
    }

    .modal-header {
        border-bottom: 1px solid #dee2e6;
        padding: 1rem 1.5rem;
    }

    .modal-title {
        font-weight: 600;
        font-size: 1.25rem;
    }

    #pdf-viewer {
        height: calc(100vh - 120px);
    }

    /* Responsive */
    @media (max-width: 768px) {
        .page-title {
            font-size: 2rem;
        }

        .page-subtitle {
            font-size: 1rem;
        }

        .upload-area {
            padding: 40px 20px;
        }

        .upload-title {
            font-size: 1.2rem;
        }

        .documents-grid {
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 16px;
        }

        .action-label {
            font-size: 0.7rem;
        }
    }

    @media (max-width: 480px) {
        .documents-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Choose file button handler
        const chooseFileBtn = document.getElementById('choose-file-btn');
        const fileInput = document.getElementById('file-input');
        const uploadArea = document.querySelector('.upload-area');

        if (chooseFileBtn && fileInput) {
            chooseFileBtn.addEventListener('click', function() {
                fileInput.click();
            });
        }

        // Auto-submit form when file is selected
        if (fileInput) {
            fileInput.addEventListener('change', function() {
                if (this.files.length > 0) {
                    document.getElementById('upload-form').submit();
                }
            });
        }

        // Drag and drop handlers
        if (uploadArea && fileInput) {
            // Prevent default drag behaviors
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                uploadArea.addEventListener(eventName, preventDefaults, false);
                document.body.addEventListener(eventName, preventDefaults, false);
            });

            // Highlight upload area when dragging over it
            ['dragenter', 'dragover'].forEach(eventName => {
                uploadArea.addEventListener(eventName, function() {
                    uploadArea.classList.add('drag-over');
                }, false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                uploadArea.addEventListener(eventName, function() {
                    uploadArea.classList.remove('drag-over');
                }, false);
            });

            // Handle dropped files
            uploadArea.addEventListener('drop', function(e) {
                const dt = e.dataTransfer;
                const files = dt.files;

                if (files.length > 0) {
                    // Set the files to the file input
                    fileInput.files = files;

                    // Auto-submit the form
                    document.getElementById('upload-form').submit();
                }
            }, false);
        }

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        // PDF viewer modal handler
        const pdfModalElement = document.getElementById('pdf-viewer-modal');
        if (pdfModalElement) {
            const pdfModal = new bootstrap.Modal(pdfModalElement, {
                backdrop: true,
                keyboard: true,
                focus: true
            });

            pdfModalElement.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const pdfUrl = button.getAttribute('data-pdf-url');
                const pdfViewer = document.getElementById('pdf-viewer');
                const pdfDownloadLink = document.getElementById('pdf-download-link');

                if (pdfViewer) {
                    pdfViewer.setAttribute('data', pdfUrl);
                }
                if (pdfDownloadLink) {
                    pdfDownloadLink.setAttribute('href', pdfUrl);
                }
            });

            // Clear PDF and remove aria-hidden when modal closes to fix accessibility issue
            pdfModalElement.addEventListener('hidden.bs.modal', function () {
                const pdfViewer = document.getElementById('pdf-viewer');
                if (pdfViewer) {
                    pdfViewer.setAttribute('data', '');
                }
                // Remove aria-hidden to prevent accessibility warning
                pdfModalElement.removeAttribute('aria-hidden');
            });

            // Ensure focus is properly managed before hiding
            pdfModalElement.addEventListener('hide.bs.modal', function () {
                const closeBtn = pdfModalElement.querySelector('.btn-close');
                if (closeBtn && document.activeElement === closeBtn) {
                    closeBtn.blur();
                }
            });
        }
    });
</script>

</body>
</html>
