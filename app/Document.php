<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Document extends Model
{
    protected $fillable = ['name', 'path', 'pdf_path', 'file_type'];

    public static function boot(): void
    {
        parent::boot();

        static::deleting(function (Document $document) {
            // Delete original file
            if ($document->path) {
                Storage::disk('public')->delete($document->path);
            }

            // Delete PDF version
            if ($document->pdf_path) {
                Storage::disk('public')->delete($document->pdf_path);
            }
        });
    }

    public function getOriginalFileUrl(): string
    {
        return Storage::url($this->path);
    }

    public function getPdfUrl(): string
    {
        return Storage::url($this->pdf_path);
    }

    public function getFileIcon(): string
    {
        $icons = [
            'word' => '📄',
            'excel' => '📊',
            'image' => '🖼️',
        ];

        return $icons[$this->file_type] ?? '📎';
    }
}
