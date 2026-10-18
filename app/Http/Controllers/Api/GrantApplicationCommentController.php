<?php

namespace App\Http\Controllers\Api;

use App\Domains\Grant\Services\GrantApplicationCommentService;
use App\Http\Controllers\Controller;
use App\Http\Requests\GrantApplication\StoreGrantApplicationCommentRequest;
use App\Http\Requests\GrantApplication\UpdateGrantApplicationCommentRequest;
use App\Http\Resources\GrantApplicationCommentResource;
use App\Models\GrantApplication;
use App\Models\GrantApplicationComment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class GrantApplicationCommentController extends Controller
{
    public function __construct(private readonly GrantApplicationCommentService $comments) {}

    #[OA\Get(
        path: '/grant-applications/{grant_application}/comments',
        summary: 'Daftar komentar pengajuan bantuan',
        tags: ['GrantApplication'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function index(Request $request, GrantApplication $grantApplication): JsonResponse
    {
        $this->authorize('view', $grantApplication);

        $perPage = min(100, max(1, $request->integer('per_page', 50)));
        $page = max(1, $request->integer('page', 1));

        return $this->collection(GrantApplicationCommentResource::collection(
            $this->comments->paginate($grantApplication, $perPage, $page)
        ));
    }

    #[OA\Post(
        path: '/grant-applications/{grant_application}/comments',
        summary: 'Tambah komentar pengajuan bantuan',
        tags: ['GrantApplication'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function store(StoreGrantApplicationCommentRequest $request, GrantApplication $grantApplication): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $comment = $this->comments->create($grantApplication, $request->validated('body'), $actor);

        return $this->created(new GrantApplicationCommentResource($comment));
    }

    #[OA\Put(
        path: '/grant-applications/{grant_application}/comments/{comment}',
        summary: 'Ubah komentar sendiri',
        tags: ['GrantApplication'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function update(
        UpdateGrantApplicationCommentRequest $request,
        GrantApplication $grantApplication,
        GrantApplicationComment $comment,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $updated = $this->comments->update($grantApplication, $comment, $request->validated('body'), $actor);

        return $this->resource(new GrantApplicationCommentResource($updated));
    }

    #[OA\Delete(
        path: '/grant-applications/{grant_application}/comments/{comment}',
        summary: 'Hapus komentar (penulis atau admin)',
        tags: ['GrantApplication'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function destroy(
        Request $request,
        GrantApplication $grantApplication,
        GrantApplicationComment $comment,
    ): JsonResponse {
        $this->authorize('deleteComment', [$grantApplication, $comment]);

        /** @var User $actor */
        $actor = $request->user();
        $this->comments->delete($grantApplication, $comment, $actor);

        return $this->message('Komentar dihapus.');
    }
}
