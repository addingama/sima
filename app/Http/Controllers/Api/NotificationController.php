<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\MarkNotificationsReadRequest;
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
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->ok(
            $this->notifications->listFor($user)->map(fn (DatabaseNotification $notification) => $this->payload($notification))->values(),
            null,
            ['unread_count' => $this->notifications->unreadCount($user)],
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
