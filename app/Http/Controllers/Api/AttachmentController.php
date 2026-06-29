<?php

namespace App\Http\Controllers\Api;

use App\Domains\Audit\Services\AuditLogService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attachment\IndexAttachmentRequest;
use App\Http\Requests\Attachment\StoreAttachmentRequest;
use App\Http\Resources\AttachmentResource;
use App\Http\Responses\ApiResponse;
use App\Models\Attachment;
use App\Models\BankFee;
use App\Models\Disbursement;
use App\Models\OperationalLiability;
use App\Models\Receipt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;
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

    public function __construct(private readonly AuditLogService $audit) {}

    #[OA\Get(
        path: '/attachments',
        summary: 'Daftar lampiran',
        tags: ['Attachment'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function index(IndexAttachmentRequest $request): JsonResponse
    {
        $model = $this->resolve($request->attachableType(), $request->attachableId());
        $this->authorize('view', $model);

        return $this->collection(AttachmentResource::collection(
            $model->attachments()->with('uploader:id,name')->get()
        ));
    }

    #[OA\Post(
        path: '/attachments',
        summary: 'Unggah lampiran',
        tags: ['Attachment'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function store(StoreAttachmentRequest $request): JsonResponse
    {
        $model = $this->resolve($request->attachableType(), $request->attachableId());
        $this->authorize('view', $model);
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

        return $this->created(new AttachmentResource($attachment));
    }

    #[OA\Get(
        path: '/attachments/{attachment}/download',
        summary: 'Unduh lampiran',
        tags: ['Attachment'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'File stream')]
    )]
    public function download(Attachment $attachment): StreamedResponse
    {
        $this->authorize('download', $attachment);

        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404, 'Berkas tidak ditemukan.');

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    #[OA\Delete(
        path: '/attachments/{attachment}',
        summary: 'Hapus lampiran',
        tags: ['Attachment'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function destroy(Attachment $attachment): JsonResponse
    {
        $this->authorize('delete', $attachment);

        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();

        return ApiResponse::success(null, 'Lampiran dihapus.');
    }

    private function resolve(string $type, int $id): Model
    {
        /** @var class-string<Model> $class */
        $class = self::TYPES[$type];

        return $class::findOrFail($id);
    }
}
