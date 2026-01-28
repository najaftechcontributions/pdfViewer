<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules()
    {
        return [
            'document' => [
                'required',
                'file',
                'mimes:pdf,doc,docx,rtf,odt,xls,xlsx,csv,ods,jpg,jpeg,png,gif,bmp,webp',
                'max:20000' // 20MB max
            ],
        ];
    }

    public function messages()
    {
        return [
            'document.mimes' => 'Please upload a PDF, Word document, Excel spreadsheet, or image file.',
            'document.max' => 'File size must not exceed 20MB.',
        ];
    }
}
