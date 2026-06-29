<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\User;
use App\Policies\Concerns\AuthorizesAttachable;
use App\Policies\Concerns\ChecksSimaPermission;
use Illuminate\Database\Eloquent\Model;

class AttachmentPolicy
{
    use AuthorizesAttachable, ChecksSimaPermission;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'attachment.view');
    }

    public function view(User $user, Attachment $attachment): bool
    {
        $parent = $attachment->attachable;

        return $parent instanceof Model
            && $this->allows($user, 'attachment.view')
            && $this->canViewAttachable($user, $parent);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'attachment.manage');
    }

    public function upload(User $user, Model $attachable): bool
    {
        return $this->allows($user, 'attachment.manage')
            && $this->canViewAttachable($user, $attachable);
    }

    public function delete(User $user, Attachment $attachment): bool
    {
        return $this->view($user, $attachment)
            && $this->allows($user, 'attachment.manage');
    }

    public function download(User $user, Attachment $attachment): bool
    {
        return $this->view($user, $attachment);
    }
}
