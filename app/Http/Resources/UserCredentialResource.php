<?php

namespace App\Http\Resources;

use App\Enums\CredentialStatus;
use App\Helpers\CommonHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A user's credential lifecycle, embedded wherever a user is shown.
 *
 * One shape for the list row and the details pane, so both read the status the same
 * way: the value to filter and compare on, the label and badge colour to render
 * ({@see CredentialStatus}), and the dates behind it.
 */
class UserCredentialResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->credentialStatus();

        return [
            'status' => $status,
            'status_label' => CredentialStatus::label($status),
            'status_color' => CredentialStatus::color($status),
            'accessed' => $this->hasAccessedCredentials(),
            'sent_at' => CommonHelper::formatDate($this->credentials_sent_at, true),
            'temporary_expires_at' => CommonHelper::formatDate($this->temporary_password_expires_at, true),
            'password_changed_at' => CommonHelper::formatDate($this->password_changed_at, true),
            'last_login_at' => CommonHelper::formatDate($this->last_login_at, true),
            // The label above is for reading; this is for working out "3 days ago".
            'last_login_at_value' => $this->last_login_at?->toIso8601String(),
        ];
    }
}
