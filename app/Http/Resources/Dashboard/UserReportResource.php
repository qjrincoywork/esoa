<?php

namespace App\Http\Resources\Dashboard;

use App\Enums\DataScope;
use App\Enums\UserType;
use App\Helpers\CommonHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the per-user activity report (privileged staff only).
 *
 * The resource receives an activity row already merged with its {@see \App\Models\User}
 * (eager loaded with detail and roles by the service), so rendering a hundred rows costs
 * no additional queries.
 */
class UserReportResource extends JsonResource
{
    /**
     * Transform a merged activity row into the report payload.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->resource['user'] ?? null;
        $detail = $user?->userDetail;
        $billed = (float) ($this->resource['billed_amount'] ?? 0);
        $outstanding = (float) ($this->resource['outstanding_amount'] ?? 0);

        $fullName = trim(implode(' ', array_filter([
            $detail?->first_name,
            $detail?->last_name,
        ])));

        $scope = (int) ($this->resource['scope'] ?? DataScope::OWNED_RECORDS);

        return [
            'key' => 'user-' . $this->resource['user_id'],
            'user_id' => (int) $this->resource['user_id'],
            // How this row's invoices were attributed — an account admin uploads nothing,
            // so without this the reader cannot tell whose invoices they are looking at.
            'scope' => $scope,
            'scope_label' => DataScope::label($scope),
            'scope_description' => DataScope::description($scope),
            'username' => $user?->username ?? 'User #' . $this->resource['user_id'],
            'name' => $fullName !== '' ? $fullName : ($user?->username ?? '—'),
            'email' => $user?->email,
            'type_label' => $detail?->type !== null ? UserType::label((int) $detail->type) : '—',
            'roles' => $user ? $user->getRoleNames()->toArray() : [],
            'is_active' => (bool) ($user?->is_active ?? false),
            'invoice_count' => (int) ($this->resource['invoice_count'] ?? 0),
            'billed_amount' => $billed,
            'billed_formatted' => CommonHelper::formatMoney($billed),
            'outstanding_amount' => $outstanding,
            'outstanding_formatted' => CommonHelper::formatMoney($outstanding),
            'concern_count' => (int) ($this->resource['concern_count'] ?? 0),
            'payment_count' => (int) ($this->resource['payment_count'] ?? 0),
            'last_activity_at' => CommonHelper::formatDate($this->resource['last_activity_at'] ?? null, true),
        ];
    }
}
