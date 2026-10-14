<?php

namespace App\Http\Resources;

use App\Enums\EmailNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Which email notifications a user receives, embedded in a user's list row.
 *
 * One entry per notification ({@see EmailNotification}), in the enum's order, so the
 * client renders and prefills the settings without knowing the list itself. Expects the
 * user's `notificationExclusions` loaded; the list query loads them.
 */
class UserEmailNotificationResource extends JsonResource
{
    /**
     * @return array<int, array{value:string,label:string,enabled:bool}>
     */
    public function toArray(Request $request): array
    {
        return array_map(
            fn (string $notification): array => [
                'value' => $notification,
                'label' => EmailNotification::label($notification),
                'enabled' => $this->receivesEmailNotification($notification),
            ],
            EmailNotification::getValues()
        );
    }
}
