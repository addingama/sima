<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\ListNotificationsRequest;
use App\Http\Requests\Notification\MarkNotificationsReadRequest;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\InAppNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use OpenApi\Attributes as OA;

class NotificationController extends Controller
{
    public function __construct(private readonly InAppNotificationService $notifications) {}

    #[OA\Get(
        path: '/notifications',
        summary: 'Daftar notifikasi in-app pengguna login',
        tags: ['Notification'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function index(ListNotificationsRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $status = $request->validated('status') ?? 'all';
        $page = (int) ($request->validated('page') ?? 1);
        $perPage = (int) ($request->validated('per_page') ?? 20);
        $paginator = $this->notifications->paginateFor($user, $status, $page, $perPage);

        return $this->ok(
            collect($paginator->items())->map(fn (DatabaseNotification $notification) => $this->payload($notification))->values(),
            null,
            [
                'unread_count' => $this->notifications->unreadCount($user),
                'pagination' => ApiResponse::lengthAwareMeta($paginator),
            ],
        );
    }

    #[OA\Post(
        path: '/notifications/{notification}/read',
        summary: 'Tandai satu notifikasi dibaca',
        tags: ['Notification'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function read(Request $request, string $notification): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->ok($this->payload($this->notifications->markRead($user, $notification)));
    }

    #[OA\Post(
        path: '/notifications/{notification}/unread',
        summary: 'Tandai satu notifikasi belum dibaca',
        tags: ['Notification'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function unread(Request $request, string $notification): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->ok($this->payload($this->notifications->markUnread($user, $notification)));
    }

    #[OA\Post(
        path: '/notifications/read-all',
        summary: 'Tandai notifikasi dibaca, opsional per kartu bantuan',
        tags: ['Notification'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function readAll(MarkNotificationsReadRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $grantId = $request->validated('grant_application_id');

        return $this->ok([
            'marked' => $this->notifications->markAllRead($user, $grantId !== null ? (int) $grantId : null),
        ]);
    }

    #[OA\Delete(
        path: '/notifications/read',
        summary: 'Hapus notifikasi yang sudah dibaca',
        tags: ['Notification'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function destroyRead(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->ok([
            'deleted' => $this->notifications->deleteRead($user),
        ]);
    }

    #[OA\Delete(
        path: '/notifications/{notification}',
        summary: 'Hapus satu notifikasi',
        tags: ['Notification'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function destroy(Request $request, string $notification): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->notifications->delete($user, $notification);

        return $this->ok(['deleted' => 1]);
    }

    /** @return array<string, mixed> */
    private function payload(DatabaseNotification $notification): array
    {
        $data = $notification->data;

        return [
            'id' => $notification->id,
            'kind' => $data['kind'] ?? null,
            'grant_application_id' => $data['grant_application_id'] ?? null,
            'application_number' => $data['application_number'] ?? null,
            'comment_id' => $data['comment_id'] ?? null,
            'actor_name' => $data['actor_name'] ?? null,
            'excerpt' => $data['excerpt'] ?? null,
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }
}
