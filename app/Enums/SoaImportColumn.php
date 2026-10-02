<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * Canonical column keys for the batch billing-invoice upload spreadsheet.
 *
 * Single source of truth for the template header, the client-side parser and the
 * server-side importer, so the sheet contract lives in exactly one place. Values
 * match the lowercase header text expected in the uploaded file.
 *
 * The columns deliberately mirror the fields of {@see \App\Http\Requests\Soa\CreateRequest},
 * which validates every row: the two attachment columns carry a *file name* that is
 * matched against the separately uploaded attachments, and the remaining columns
 * carry the billing details verbatim.
 */
final class SoaImportColumn extends Enum
{
    public const SOA_NUMBER = 'soa_number';

    public const ACCOUNT_TYPE = 'account_type';

    public const ACCOUNT_CODE = 'account_code';

    public const BRANCH_CODE = 'branch_code';

    public const BILL_TYPE = 'bill_type';

    public const STATUS = 'status';

    public const DUE_DATE = 'due_date';

    public const PERIOD_DATE_FROM = 'period_date_from';

    public const PERIOD_DATE_TO = 'period_date_to';

    public const CONTRACT_DATE_FROM = 'contract_date_from';

    public const CONTRACT_DATE_TO = 'contract_date_to';

    public const BILLING_DATE = 'billing_date';

    public const AMOUNT = 'amount';

    public const FILE_PDF = 'file_pdf';

    public const FILE_XLS = 'file_xls';

    /**
     * The columns in the exact order they should appear in the template header.
     *
     * @return list<string>
     */
    public static function ordered(): array
    {
        return [
            self::SOA_NUMBER,
            self::ACCOUNT_TYPE,
            self::ACCOUNT_CODE,
            self::BRANCH_CODE,
            self::BILL_TYPE,
            self::STATUS,
            self::DUE_DATE,
            self::PERIOD_DATE_FROM,
            self::PERIOD_DATE_TO,
            self::CONTRACT_DATE_FROM,
            self::CONTRACT_DATE_TO,
            self::BILLING_DATE,
            self::AMOUNT,
            self::FILE_PDF,
            self::FILE_XLS,
        ];
    }

    /**
     * Columns that must be present in the header and non-empty for every row.
     *
     * branch_code and the contract dates are nullable, and file_xls is required only
     * when the bill type is not ECU (see {@see \App\Http\Requests\Soa\CreateRequest}),
     * so those are intentionally excluded — the per-row rules have the final say.
     *
     * account_type is excluded too, for a different reason: the application derives it
     * from the account code ({@see \App\Enums\AccountType::fromAccountCode}) and stores
     * the derived value whatever the sheet says. Demanding a cell whose contents are
     * then overwritten only gives the uploader a way to be wrong, so it is optional —
     * supplied, it is checked against the code; left blank, it is simply derived.
     *
     * @return list<string>
     */
    public static function required(): array
    {
        return [
            self::SOA_NUMBER,
            self::ACCOUNT_CODE,
            self::BILL_TYPE,
            self::STATUS,
            self::DUE_DATE,
            self::PERIOD_DATE_FROM,
            self::PERIOD_DATE_TO,
            self::BILLING_DATE,
            self::AMOUNT,
            self::FILE_PDF,
        ];
    }

    /**
     * Columns whose cell holds a file name resolved against the uploaded attachments.
     *
     * @return list<string>
     */
    public static function attachments(): array
    {
        return [
            self::FILE_PDF,
            self::FILE_XLS,
        ];
    }

    /**
     * Columns holding a date, which the importer normalises before validating.
     *
     * @return list<string>
     */
    public static function dates(): array
    {
        return [
            self::DUE_DATE,
            self::PERIOD_DATE_FROM,
            self::PERIOD_DATE_TO,
            self::CONTRACT_DATE_FROM,
            self::CONTRACT_DATE_TO,
            self::BILLING_DATE,
        ];
    }
}
