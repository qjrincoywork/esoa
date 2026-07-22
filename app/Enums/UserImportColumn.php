<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * Canonical column keys for the bulk user-import spreadsheet.
 *
 * Single source of truth for the template header, the client-side parser and
 * the server-side importer, so the sheet contract lives in exactly one place.
 * Values match the lowercase header text expected in the uploaded file.
 *
 * Note: there is intentionally no username column — the username is derived
 * from the email's local part during import (see UserBulkImportService).
 */
final class UserImportColumn extends Enum
{
    public const FIRST_NAME = 'first_name';

    public const LAST_NAME = 'last_name';

    public const CITIZENSHIP = 'citizenship';

    public const CIVIL_STATUS = 'civil_status';

    public const GENDER = 'gender';

    public const TYPE = 'type';

    public const EMAIL = 'email';

    public const ROLE = 'role';

    public const ACCOUNT_CODE = 'account_code';

    public const BRANCH_CODE = 'branch_code';

    /**
     * The columns in the exact order they should appear in the template header.
     *
     * @return list<string>
     */
    public static function ordered(): array
    {
        return [
            self::FIRST_NAME,
            self::LAST_NAME,
            self::CITIZENSHIP,
            self::CIVIL_STATUS,
            self::GENDER,
            self::TYPE,
            self::EMAIL,
            self::ROLE,
            self::ACCOUNT_CODE,
            self::BRANCH_CODE,
        ];
    }

    /**
     * Columns that must be present and non-empty for every row.
     *
     * account_code/branch_code are optional (only account/group/broker admins
     * carry them) and role is optional, so they are intentionally excluded.
     *
     * @return list<string>
     */
    public static function required(): array
    {
        return [
            self::FIRST_NAME,
            self::LAST_NAME,
            self::CITIZENSHIP,
            self::CIVIL_STATUS,
            self::GENDER,
            self::TYPE,
            self::EMAIL,
        ];
    }
}
