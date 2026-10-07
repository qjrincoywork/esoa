<?php

namespace App\Exports;

use App\Enums\CredentialAccess;
use App\Enums\CredentialStatus;
use App\Enums\UserCredentialReport;
use App\Enums\UserType;
use App\Helpers\CommonHelper;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The credential reports behind the users list's export menu ({@see UserCredentialReport}).
 *
 * Both run over the list's own filtered query, so a report holds exactly what the
 * filtered list shows. No password is ever exported — only where each user's
 * credentials stand; the plain text is never stored to begin with.
 */
class UserCredentialReportExporter extends HtmlSpreadsheetExporter
{
    /** Users are read in pages so relations can be eager-loaded (a cursor drops them). */
    private const CHUNK = 500;

    /**
     * Stream the given report over the filtered user query.
     */
    public function download(string $report, Builder $query): StreamedResponse
    {
        $query->with(['userDetail.department', 'roles:id,name']);
        $filename = UserCredentialReport::filePrefix($report) . '_' . now()->format('Y-m-d_His') . '.xls';

        return $report === UserCredentialReport::ACCESS
            ? $this->accessReport($query, $filename)
            : $this->credentialsReport($query, $filename);
    }

    /**
     * Every user with their full credential state, one row each.
     */
    private function credentialsReport(Builder $query, string $filename): StreamedResponse
    {
        $headers = [
            'Username', 'Email', 'Full Name', 'Type', 'Department', 'Roles', 'Account Status',
            'Credential Status', 'Credentials Sent', 'Temporary Password Expires',
            'Password Updated', 'Last Login', 'Accessed Credentials',
        ];

        return $this->stream($filename, 'User Credentials', $headers, function () use ($query) {
            foreach ($query->lazy(self::CHUNK) as $user) {
                echo $this->row([
                    ...$this->identity($user),
                    $user->roles->pluck('name')->join(', '),
                    $this->accountStatus($user),
                    CredentialStatus::label($user->credentialStatus()),
                    CommonHelper::formatDate($user->credentials_sent_at, true),
                    CommonHelper::formatDate($user->temporary_password_expires_at, true),
                    CommonHelper::formatDate($user->password_changed_at, true),
                    CommonHelper::formatDate($user->last_login_at, true),
                    $user->credentials_sent_at === null ? 'N/A' : ($user->hasAccessedCredentials() ? 'Yes' : 'No'),
                ]);
            }
        });
    }

    /**
     * Users who were sent credentials, in two sections: who has accessed them and who
     * has not yet. Users never sent any are left out — "not accessed" means nothing for
     * them. Each section heading carries its count, so the comparison reads at a glance.
     */
    private function accessReport(Builder $query, string $filename): StreamedResponse
    {
        $headers = [
            'Username', 'Email', 'Full Name', 'Type', 'Department', 'Credential Status',
            'Credentials Sent', 'Last Login', 'Days Since Sent',
        ];
        $columns = count($headers);

        return $this->stream($filename, 'Credential Access', $headers, function () use ($query, $columns) {
            foreach ([CredentialAccess::ACCESSED, CredentialAccess::NOT_ACCESSED] as $access) {
                $section = (clone $query)->credentialAccess($access);

                echo $this->sectionRow(CredentialAccess::label($access) . ' (' . (clone $section)->count() . ')', $columns);

                foreach ($section->lazy(self::CHUNK) as $user) {
                    echo $this->row([
                        ...$this->identity($user),
                        CredentialStatus::label($user->credentialStatus()),
                        CommonHelper::formatDate($user->credentials_sent_at, true),
                        CommonHelper::formatDate($user->last_login_at, true),
                        (int) $user->credentials_sent_at->diffInDays(now()),
                    ]);
                }
            }
        });
    }

    /**
     * The columns that identify a user, shared by both reports.
     *
     * @return list<string|null>
     */
    private function identity(User $user): array
    {
        $detail = $user->userDetail;

        return [
            $user->username,
            $user->email,
            $detail?->full_name,
            $detail?->type !== null ? UserType::label((int) $detail->type) : null,
            $detail?->department?->name,
        ];
    }

    private function accountStatus(User $user): string
    {
        return match (true) {
            $user->deleted_at !== null => 'Deleted',
            (bool) $user->is_active => 'Active',
            default => 'Inactive',
        };
    }
}
