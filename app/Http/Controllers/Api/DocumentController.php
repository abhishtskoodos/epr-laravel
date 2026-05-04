<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TransitionRequest;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'documentable_type' => ['required', 'string'],
            'documentable_id' => ['required', 'integer'],
            'category' => ['required', 'string', 'max:64'],
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
        ]);

        $file = $request->file('file');
        $checksum = hash_file('sha256', $file->getRealPath());

        // Duplicate detection — reject identical (same parent + same category + same checksum)
        $dup = Document::where('documentable_type', $data['documentable_type'])
            ->where('documentable_id', $data['documentable_id'])
            ->where('category', $data['category'])
            ->where('checksum_sha256', $checksum)
            ->exists();
        abort_if($dup, 409, 'Identical document already uploaded.');

        $path = $file->store('documents/'.now()->format('Y/m'), 'local');

        $doc = Document::create([
            'documentable_type' => $data['documentable_type'],
            'documentable_id' => $data['documentable_id'],
            'category' => $data['category'],
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'checksum_sha256' => $checksum,
            'uploaded_by' => $request->user()->id,
        ]);

        return response()->json($doc, 201);
    }

    public function show(Document $document): JsonResponse
    {
        $url = Storage::disk('local')->temporaryUrl($document->path, now()->addMinutes(5));

        return response()->json([
            'document' => $document,
            'signed_url' => $url,
            'expires_in' => 300,
        ]);
    }

    public function transition(TransitionRequest $request, Document $document): JsonResponse
    {
        abort_unless($request->user()?->hasAnyRole(['admin', 'finance', 'inspector']), 403);

        $document->update([
            'status' => $request->validated('to'),
            'verified_at' => $request->validated('to') === 'verified' ? now() : null,
            'verified_by' => $request->user()->id,
            'remarks' => $request->validated('remarks'),
        ]);

        return response()->json($document->fresh());
    }
}
