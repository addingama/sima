<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\User;
use App\Policies\Concerns\ChecksSimaPermission;

class AttachmentPolicy
{
    use ChecksSimaPermission;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'attachment.view');
    }

    public function view(User $user, Attachment $attachment): bool
    {
        return $this->allows($user, 'attachment.view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'attachment.manage');
    }

    public function delete(User $user, Attachment $attachment): bool
    {
        return $this->allows($user, 'attachment.manage');
    }

    /** Alias untuk unduhan berkas. */
    public function download(User $user, Attachment $attachment): bool
    {
        return $this->view($user, $attachment);
    }
}
