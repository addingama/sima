<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\BankFee;
use App\Models\Disbursement;
use App\Models\OperationalLiability;
use App\Models\Receipt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    /** Pemetaan kunci tipe -> kelas model yang boleh dilampiri. */
    private const TYPES = [
        'receipt' => Receipt::class,
        'disbursement' => Disbursement::class,
        'bank_fee' => BankFee::class,
        'liability' => OperationalLiability::class,
    ];

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'attachable_type' => ['required', 'in:'.implode(',', array_keys(self::TYPES))],
            'attachable_id' => ['required', 'integer'],
        ]);

        $model = $this->resolve($data['attachable_type'], (int) $data['attachable_id']);

        return response()->json($model->attachments()->with('uploader:id,name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'attachable_type' => ['required', 'in:'.implode(',', array_keys(self::TYPES))],
            'attachable_id' => ['required', 'integer'],
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $model = $this->resolve($data['attachable_type'], (int) $data['attachable_id']);

        $file = $request->file('file');
        $path = $file->store('attachments/'.$data['attachable_type'], 'local');

        $attachment = $model->attachments()->create([
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'title' => $data['title'] ?? null,
            'uploaded_by' => $request->user()->id,
        ]);

        return response()->json($attachment, 201);
    }

    public function download(Attachment $attachment): StreamedResponse
    {
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404, 'Berkas tidak ditemukan.');

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    public function destroy(Attachment $attachment): JsonResponse
    {
        // Lampiran bukan transaksi keuangan; boleh dihapus. File fisik ikut dihapus.
        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();

        return response()->json(['message' => 'Lampiran dihapus.']);
    }

    private function resolve(string $type, int $id): Model
    {
        /** @var class-string<Model> $class */
        $class = self::TYPES[$type];

        return $class::findOrFail($id);
    }
}
