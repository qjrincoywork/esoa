<?php

namespace App\Enums;

use BenSampo\Enum\Enum;
use Illuminate\Contracts\Auth\Authenticatable;

final class SoaStatus extends Enum
{
    public const UNPAID = 1;
    public const ENDORSED = 2;
    public const PAID = 3;
    public const DISPUTED = 4;

    /**
     * The roles whose settable statuses are the ones named in
     * `vc.allowed_soa_status_for_account_branch_admin`.
     *
     * Every other role gets the complement of that list, so the two audiences never
     * overlap and between them cover every status.
     *
     * Deliberately a method and not a constant: this class's constants *are* its
     * values — {@see getValues()} reflects over them without regard to visibility, so
     * even a private constant here would be handed out as a fifth status.
     *
     * @return list<string>
     */
    private static function accountSideRoles(): array
    {
        return ['account_branch_admin', 'group_account_admin'];
    }

    /**
     * Map an SOA status value to its human-readable label.
     *
     * @param int $value
     * @return string
     */
    public static function label($value): string
    {
        return match ($value) {
            self::UNPAID => 'Unpaid',
            self::ENDORSED => 'Endorsed',
            self::PAID => 'Paid',
            self::DISPUTED => 'Disputed',
        };
    }

    /**
     * Return the Tailwind CSS badge classes (padding/rounding/background/text/border) for the given SOA status.
     *
     * @param int $value
     * @return string
     */
    public static function color($value): string
    {
        return match ($value) {
            self::UNPAID => 'p-3 rounded-lg bg-red-500/20 text-red-500 border-red-500/30',
            self::ENDORSED => 'p-3 rounded-lg bg-yellow-500/20 text-yellow-500 border-yellow-500/30',
            self::PAID => 'p-3 rounded-lg bg-green-500/20 text-green-500 border-green-500/30',
            self::DISPUTED => 'p-3 rounded-lg bg-blue-500/20 text-blue-500 border-blue-500/30',
        };
    }

    /**
     * Map an SOA status to a chart tone token.
     *
     * Charts must not reuse the badge utility classes from {@see color()} (those carry
     * layout and only resolve inside Tailwind); they key on a tone token instead, which
     * the front end resolves to the themed `--viz-status-*` custom properties. Each
     * token mirrors the Tailwind 500 accent of {@see color()} so the donut, the badge
     * and the status filter stay aligned.
     *
     * @param int $value
     * @return string One of: status-unpaid, status-endorsed, status-paid, status-disputed.
     */
    public static function tone($value): string
    {
        return match ($value) {
            self::UNPAID => 'status-unpaid',
            self::ENDORSED => 'status-endorsed',
            self::PAID => 'status-paid',
            self::DISPUTED => 'status-disputed',
            default => 'status-unpaid',
        };
    }

    /**
     * Return all SOA statuses as {value, name} option arrays for select inputs.
     *
     * @return array<array{value:int,name:string}>
     */
    public static function list(): array
    {
        return [
            ['value' => self::UNPAID, 'name' => self::label(self::UNPAID)],
            ['value' => self::ENDORSED, 'name' => self::label(self::ENDORSED)],
            ['value' => self::PAID, 'name' => self::label(self::PAID)],
            ['value' => self::DISPUTED, 'name' => self::label(self::DISPUTED)],
        ];
    }

    /**
     * The status values the given user is allowed to set on an invoice.
     *
     * Account/branch and group-account admins may set exactly the statuses listed in
     * `vc.allowed_soa_status_for_account_branch_admin`; every other role may set the
     * complement of that list.
     *
     * This is the one definition of that split. {@see \App\Rules\SoaStatusIsValid}
     * enforces it and the forms are built from it, so a form can no longer offer a
     * value the validator is certain to refuse — which is what made a whole batch
     * upload fail when the template was filled in from the options the form showed.
     *
     * A null user (console, queued work) is treated as the non-account side, matching
     * the rule's own behaviour for anyone who is not an account-side admin.
     *
     * @return list<int>
     */
    public static function assignableValues(?Authenticatable $user): array
    {
        $accountSideOnly = array_map(
            'intval',
            (array) config('vc.allowed_soa_status_for_account_branch_admin', []),
        );

        $isAccountSide = $user !== null
            && method_exists($user, 'hasAnyRole')
            && $user->hasAnyRole(self::accountSideRoles());

        // Account-side admins keep the reserved statuses; everyone else keeps the rest.
        return array_values(array_filter(
            array_map('intval', self::getValues()),
            static fn (int $value): bool => in_array($value, $accountSideOnly, true) === $isAccountSide,
        ));
    }

    /**
     * The statuses the given user may set, as {value, name} options for a select.
     *
     * The counterpart to {@see list()}, which stays unfiltered because a filter on the
     * listing page must still be able to search for a status the viewer cannot assign.
     *
     * @return array<array{value:int,name:string}>
     */
    public static function assignableList(?Authenticatable $user): array
    {
        return array_values(array_map(
            static fn (int $value): array => ['value' => $value, 'name' => self::label($value)],
            self::assignableValues($user),
        ));
    }
}
