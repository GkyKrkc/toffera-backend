<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

// app/Http/Controllers/Agent/PortfolioDocumentController.php

class PortfolioDocumentController extends Controller
{
    // GET /agent/portfolio/{item} — documents with() ile zaten gelsin
    // (PortfolioItemController@show içinde $item->load('images', 'documents'))

    // POST /agent/portfolio/{item}/documents
    public function store(Request $request, PortfolioItem $item)
    {
        $this->authorize('update', $item);

        $request->validate([
            'documents'   => 'required|array|max:10',
            'documents.*' => 'file|mimes:pdf,jpg,jpeg,png,webp,heic,doc,docx,xls,xlsx|max:10240',
        ]);

        $uploaded = [];
        foreach ($request->file('documents') as $file) {
            $maxKb = str_starts_with($file->getMimeType(), 'image/') ? 5120 : 10240;
            if ($file->getSize() / 1024 > $maxKb) continue; // ikinci katman kontrol

            $path = $file->store("portfolio/docs/{$item->id}", 'public');

            $doc = $item->documents()->create([
                'uploaded_by' => auth()->id(),
                'file_name'   => $file->getClientOriginalName(),
                'path'        => $path,
                'mime_type'   => $file->getMimeType(),
                'size'        => $file->getSize(),
            ]);

            $uploaded[] = $doc;
        }

        return response()->json(['documents' => $uploaded]);
    }

    // DELETE /agent/portfolio/{item}/documents/{document}
    public function destroy(PortfolioItem $item, PortfolioDocument $document)
    {
        $this->authorize('update', $item);

        Storage::disk('public')->delete($document->path);
        $document->delete();

        return response()->json(['message' => 'Belge silindi.']);
    }

    // POST /agent/portfolio/{item}/documents/bulk-delete
    public function bulkDestroy(Request $request, PortfolioItem $item)
    {
        $this->authorize('update', $item);

        $request->validate(['ids' => 'required|array']);

        $docs = $item->documents()->whereIn('id', $request->ids)->get();
        foreach ($docs as $doc) {
            Storage::disk('public')->delete($doc->path);
            $doc->delete();
        }

        return response()->json(['message' => 'Belgeler silindi.']);
    }
}
