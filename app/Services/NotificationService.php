<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;

class NotificationService
{
    /**
     * Create a notification for a user in the custom `notifications` table.
     */
    public static function send(
        User $user,
        string $type,
        string $title,
        ?string $content = null,
        ?array $data = null
    ): Notification {
        return Notification::create([
            'user_id' => $user->id,
            'type'    => $type,
            'title'   => $title,
            'content' => $content,
            'data'    => $data,
            'is_read' => false,
        ]);
    }
}
