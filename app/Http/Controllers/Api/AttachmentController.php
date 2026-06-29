<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attachment\IndexAttachmentRequest;
use App\Http\Requests\Attachment\StoreAttachmentRequest;
use App\Http\Resources\AttachmentResource;
use App\Models\Attachment;
use App\Models\BankFee;
use App\Models\Disbursement;
use App\Models\OperationalLiability;
use App\Models\Receipt;
use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function __construct(private readonly AuditLogService $audit) {}

    /** Pemetaan kunci tipe -> kelas model yang boleh dilampiri. */
    private const TYPES = [
        'receipt' => Receipt::class,
        'disbursement' => Disbursement::class,
        'bank_fee' => BankFee::class,
        'liability' => OperationalLiability::class,
    ];

    public function index(IndexAttachmentRequest $request): JsonResponse
    {
        $model = $this->resolve($request->attachableType(), $request->attachableId());
        $this->authorize('view', $model);

        return AttachmentResource::collection(
            $model->attachments()->with('uploader:id,name')->get()
        )->response();
    }

    public function store(StoreAttachmentRequest $request): JsonResponse
    {
        $model = $this->resolve($request->attachableType(), $request->attachableId());
        $this->authorize('create', Attachment::class);

        $file = $request->file('file');
        $path = $file->store('attachments/'.$request->attachableType(), 'local');

        $attachment = $model->attachments()->create([
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'title' => $request->validated('title'),
            'uploaded_by' => $request->user()->id,
        ]);

        $this->audit->log($model, 'attachment_uploaded', null, [
            'attachment_id' => $attachment->id,
            'original_name' => $attachment->original_name,
            'size' => $attachment->size,
        ], $request->user(), 'attachment');

        return (new AttachmentResource($attachment))
            ->response()
            ->setStatusCode(201);
    }

    public function download(Attachment $attachment): StreamedResponse
    {
        $this->authorize('download', $attachment);

        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404, 'Berkas tidak ditemukan.');

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    public function destroy(Attachment $attachment): JsonResponse
    {
        $this->authorize('delete', $attachment);

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
