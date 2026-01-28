<?php

namespace Tests\Feature;

use App\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function testFirstIndexRoute()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function testSecondIndexRoute()
    {
        $response = $this->get('/documents');

        $response->assertStatus(200);
    }

    public function testImageUpload()
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('test-image.jpg');

        $response = $this->post(route('documents.create'), [
            'document' => $file
        ]);

        $response->assertRedirect(route('documents.index'));
        $response->assertSessionHas('success');

        $document = Document::where('name', 'test-image.jpg')->first();

        $this->assertNotNull($document);
        $this->assertEquals('image', $document->file_type);

        Storage::disk('public')->assertExists($document->path);
        Storage::disk('public')->assertExists($document->pdf_path);
    }

    public function testDocumentDestroy()
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('test-image.jpg');

        $this->post(route('documents.create'), [
            'document' => $file
        ]);

        $document = Document::where('name', 'test-image.jpg')->firstOrFail();

        $documentPath = $document->path;
        $pdfPath = $document->pdf_path;

        $response = $this->delete(route('documents.destroy', $document));

        $response->assertRedirect(route('documents.index'));

        $this->assertDatabaseMissing('documents', [
            'id' => $document->id
        ]);

        Storage::disk('public')->assertMissing($documentPath);
        Storage::disk('public')->assertMissing($pdfPath);
    }

    public function testInvalidFileTypeRejected()
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->create('document.txt', 100);

        $response = $this->post(route('documents.create'), [
            'document' => $file
        ]);

        $response->assertSessionHasErrors('document');
    }
}
