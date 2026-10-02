<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\AuditEvent;
use App\Enums\AuditLogName;
use App\Enums\SoaImportColumn as Col;
use App\Helpers\CommonHelper;
use App\Http\Requests\Soa\CreateRequest;
use App\Mail\NewBillingInvoiceUploaded;
use App\Models\Soa;
use App\Models\User;
use App\Rules\IsDataExists;
use App\Rules\IsServerDataExists;
use App\Support\AuditContext;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

/**
 * Orchestrates a batch billing-invoice upload from parsed spreadsheet rows.
 *
 * Every row is validated against the exact rules {@see CreateRequest} applies to a
 * single upload — the rule set is read from that class rather than restated here, so
 * the two can never drift — with the attachment columns resolved from the separately
 * uploaded files first, so the `file` / `mimes` / `max` rules run against real uploads
 * just as they do on the form.
 *
 * This service only ever sees one request's worth of rows: a manifest too large for
 * PHP's own `post_max_size` / `max_file_uploads` is split client-side into several
 * requests to {@see \App\Http\Controllers\SoaController::batchStore()}, each handled
 * by its own call to {@see import()}. "All-or-nothing" below is therefore scoped to
 * one such request/chunk, not the manifest as a whole — the client is what decides
 * whether a failed chunk stops the rest of the batch.
 *
 * The import is all-or-nothing by default: validation runs over the whole request
 * first and a single failing row stops it, so nothing from that request is written.
 * A caller may opt into skipping failing rows instead ({@see import()}'s
 * `$skipErrors`), in which case the rows that pass are stored and the ones that fail
 * are reported back rather than blocking the rest of the request. Either way, only
 * rows that pass are ever persisted, inside one transaction.
 */
class SoaBatchImportService
{
    /**
     * @var array<string,UploadedFile> "{pdf|xls}:{lower(base file name)}" => uploaded attachment
     *
     * Keyed by type plus base name rather than the full file name: a cell names an
     * attachment without its extension ({@see resolveAttachments}), and a `.pdf` and a
     * `.xlsx` sharing the same base name — the normal case for one invoice — must stay
     * distinct rather than colliding on the same key.
     */
    private array $attachments = [];

    /** @var array<string,int> lower(soa number) => the row that first used it */
    private array $seenSoaNumbers = [];

    /** @var array<string,int> same composite key as $attachments => the row that first claimed it */
    private array $claimedAttachments = [];

    /** @var array<string,\Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string> */
    private array $rules = [];

    /** @var array<string,string> */
    private array $ruleMessages = [];

    /**
     * Attachments already written to the billing disk that a failed import removed again.
     *
     * Recorded so the failure entry can say whether anything reached the share, which is
     * the first question asked when an upload dies part-way through.
     */
    private int $discardedAttachments = 0;

    /**
     * @param  Soa  $soa  Prototype used to delegate invoice persistence.
     */
    public function __construct(protected Soa $soa) {}

    /**
     * Validate and import the given rows, returning a summary of the outcome.
     *
     * Every way this can end leaves an entry in the audit trail: uploaded, rejected,
     * partially uploaded, or — here — failed outright. An upload that dies on an
     * exception is the one a user is most likely to ask about afterwards, and without
     * this it would be the only outcome that left no trace of having happened.
     *
     * @param  array<int,array<string,mixed>>  $rows
     * @param  array<int,UploadedFile>  $attachments
     * @param  bool  $skipErrors  When true, rows that pass are saved and rows that fail are
     *                            reported back instead of the whole file being refused.
     * @return array{total:int,created:int,failed:int,errors:list<array{row:int,soa_number:?string,messages:list<string>}>}
     */
    public function import(array $rows, array $attachments, User $user, bool $skipErrors = false): array
    {
        $this->discardedAttachments = 0;

        try {
            return $this->runImport($rows, $attachments, $user, $skipErrors);
        } catch (\Throwable $e) {
            $this->recordFailure(count($rows), count($attachments), $user, $e);

            // The caller still decides what the failure means; this only records it.
            throw $e;
        }
    }

    /**
     * The import itself. See {@see import()}, which wraps this to audit a failure.
     *
     * @param  array<int,array<string,mixed>>  $rows
     * @param  array<int,UploadedFile>  $attachments
     * @return array{total:int,created:int,failed:int,errors:list<array{row:int,soa_number:?string,messages:list<string>}>}
     */
    private function runImport(array $rows, array $attachments, User $user, bool $skipErrors): array
    {
        // Read the single-upload contract once; it is the same for every row.
        $createRequest = new CreateRequest();
        $this->rules = $createRequest->rules();
        $this->ruleMessages = $createRequest->messages();

        $this->attachments = $this->indexAttachments($attachments);
        $this->seenSoaNumbers = [];
        $this->claimedAttachments = [];

        // Normalise everything up front: the bulk lookups below need to see every
        // value in the file before the first row is validated.
        $normalized = array_map(
            fn ($rawRow) => $this->normalizeRow(is_array($rawRow) ? $rawRow : []),
            array_values($rows),
        );

        $this->primeLookups($normalized, $user);

        $errors = [];
        $prepared = [];

        foreach ($normalized as $index => $row) {
            $line = $this->lineNumber($row, $index);

            [$rowErrors, $resolved] = $this->validateRow($row, $line, $user);

            if ($rowErrors !== null) {
                $errors[] = $rowErrors;

                continue;
            }

            $prepared[] = $resolved;
        }

        // All-or-nothing unless the caller asked to skip failing rows: one rejected
        // row then stops the whole file, so nothing is half-imported.
        if ($errors !== [] && ! $skipErrors) {
            $this->recordRejection($normalized, $errors, count($attachments), $user);

            return [
                'total' => count($normalized),
                'created' => 0,
                'failed' => count($errors),
                'errors' => $errors,
            ];
        }

        $created = $this->persist($prepared);

        if ($created !== []) {
            $this->recordUpload($created, count($attachments), $user);

            // Notifications are sent only once the batch is committed, and one
            // failure must not undo an import that already succeeded.
            $this->notify($created, $user);
        }

        if ($errors !== []) {
            $this->recordSkipped($normalized, $errors, count($created), $user);
        }

        return [
            'total' => count($normalized),
            'created' => count($created),
            'failed' => count($errors),
            'errors' => $errors,
        ];
    }

    /**
     * How many failing rows an audit entry spells out before summarising the rest.
     *
     * A rejected batch can fail on every row it has; writing thousands of them into one
     * `properties` blob would make the entry unreadable and the column enormous. The
     * first hundred are enough to see what went wrong, and the count says how much more
     * there was.
     */
    private const AUDIT_ERROR_LIMIT = 100;

    /**
     * How many invoice numbers a successful entry lists before summarising the rest.
     */
    private const AUDIT_NUMBER_LIMIT = 100;

    /**
     * Record a successful batch upload in the audit trail.
     *
     * Each invoice already logs its own `created` entry, and the batch middleware ties
     * them to one `batch_uuid` — but read back, a thousand of those say nothing about
     * the action that produced them. This is the entry that does: who uploaded, how
     * many, and against how many attachments.
     *
     * @param  list<Soa>  $created
     */
    private function recordUpload(array $created, int $attachmentCount, User $user): void
    {
        $numbers = array_map(static fn (Soa $soa): string => (string) $soa->soa_number, $created);

        $this->writeAudit(
            AuditEvent::BATCH_UPLOADED,
            sprintf(
                'Billing invoice batch upload: %d %s uploaded',
                count($created),
                count($created) === 1 ? 'invoice' : 'invoices',
            ),
            array_filter([
                'uploaded' => count($created),
                'attachments_received' => $attachmentCount,
                'billing_invoices' => $this->summariseList($numbers, self::AUDIT_NUMBER_LIMIT),
            ], static fn ($value): bool => $value !== null),
            $user,
        );
    }

    /**
     * Record a rejected batch upload, with the reasons, in the audit trail.
     *
     * Nothing was written, so without this the attempt leaves no trace at all — and a
     * rejected upload is precisely what someone asks about afterwards. The row numbers
     * and messages are stored as they were shown to the uploader, so the entry answers
     * "what was wrong with the file I sent" without needing the file.
     *
     * @param  list<array<string,?string>>  $rows
     * @param  list<array{row:int,soa_number:?string,messages:list<string>}>  $errors
     */
    private function recordRejection(array $rows, array $errors, int $attachmentCount, User $user): void
    {
        $properties = [
            'rows_in_file' => count($rows),
            'rows_rejected' => count($errors),
            'attachments_received' => $attachmentCount,
        ];

        foreach (array_slice($errors, 0, self::AUDIT_ERROR_LIMIT) as $error) {
            $label = 'row_'.$error['row'].($error['soa_number'] !== null ? ' ('.$error['soa_number'].')' : '');
            $properties[$label] = implode(' ', $error['messages']);
        }

        if (count($errors) > self::AUDIT_ERROR_LIMIT) {
            $properties['…'] = sprintf('and %d further rejected rows', count($errors) - self::AUDIT_ERROR_LIMIT);
        }

        $this->writeAudit(
            AuditEvent::BATCH_REJECTED,
            sprintf(
                'Billing invoice batch upload rejected: %d of %d %s failed validation',
                count($errors),
                count($rows),
                count($rows) === 1 ? 'row' : 'rows',
            ),
            $properties,
            $user,
        );
    }

    /**
     * Record a batch upload where failing rows were skipped rather than blocking the rest.
     *
     * Unlike {@see recordRejection}, some rows here were actually saved — the message
     * and properties reflect that instead of implying nothing was written.
     *
     * @param  list<array<string,?string>>  $rows
     * @param  list<array{row:int,soa_number:?string,messages:list<string>}>  $errors
     */
    private function recordSkipped(array $rows, array $errors, int $createdCount, User $user): void
    {
        $properties = [
            'rows_in_file' => count($rows),
            'rows_uploaded' => $createdCount,
            'rows_skipped' => count($errors),
        ];

        foreach (array_slice($errors, 0, self::AUDIT_ERROR_LIMIT) as $error) {
            $label = 'row_'.$error['row'].($error['soa_number'] !== null ? ' ('.$error['soa_number'].')' : '');
            $properties[$label] = implode(' ', $error['messages']);
        }

        if (count($errors) > self::AUDIT_ERROR_LIMIT) {
            $properties['…'] = sprintf('and %d further skipped rows', count($errors) - self::AUDIT_ERROR_LIMIT);
        }

        $this->writeAudit(
            AuditEvent::BATCH_PARTIAL,
            sprintf(
                'Billing invoice batch upload: %d of %d %s uploaded, %d skipped due to validation errors',
                $createdCount,
                count($rows),
                count($rows) === 1 ? 'row' : 'rows',
                count($errors),
            ),
            $properties,
            $user,
        );
    }

    /**
     * Record a batch upload that died on an exception rather than a validation verdict.
     *
     * The other three outcomes are decisions this service made and can describe in full.
     * This one is a failure it did not expect, so the entry says what it can: how big the
     * upload was, whether anything had already reached the billing disk before it was
     * rolled back, and what the error actually was.
     *
     * The exception message is kept because it is what makes the entry worth reading —
     * "deadlock victim", "connection reset" — but trimmed, since a query exception
     * carries the whole statement and would otherwise dominate the record.
     */
    private function recordFailure(int $rowCount, int $attachmentCount, User $user, \Throwable $e): void
    {
        $this->writeAudit(
            AuditEvent::BATCH_FAILED,
            sprintf(
                'Billing invoice batch upload failed: %d %s could not be processed',
                $rowCount,
                $rowCount === 1 ? 'row' : 'rows',
            ),
            [
                'rows_in_file' => $rowCount,
                'attachments_received' => $attachmentCount,
                // Nothing was kept: the transaction rolled back and these were removed.
                'attachments_discarded' => $this->discardedAttachments,
                'error' => class_basename($e),
                'error_message' => Str::limit($e->getMessage(), 500),
            ],
            $user,
        );
    }

    /**
     * Write one batch-level entry to the billing-invoice audit channel.
     *
     * The summary goes under `attributes` because that is what the audit detail pane
     * renders as field-by-field rows; an entry shaped any other way would be stored but
     * never shown. Context is stamped the same way model events stamp theirs.
     *
     * Best-effort: the trail must never be the reason an upload fails, so a failure to
     * write it is logged and swallowed.
     *
     * @param  array<string,mixed>  $attributes
     */
    private function writeAudit(string $event, string $description, array $attributes, User $user): void
    {
        try {
            activity(AuditLogName::BILLING_INVOICE)
                ->causedBy($user)
                ->event($event)
                ->withProperties(array_filter([
                    'attributes' => $attributes,
                    'context' => AuditContext::forRequest(request()),
                ]))
                ->log($description);
        } catch (\Throwable $e) {
            Log::error('SoaBatchImportService: audit entry could not be written', [
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Render a list for the trail, keeping it to a readable length.
     *
     * @param  list<string>  $values
     */
    private function summariseList(array $values, int $limit): ?string
    {
        if ($values === []) {
            return null;
        }

        $shown = implode(', ', array_slice($values, 0, $limit));

        return count($values) > $limit
            ? $shown.sprintf(' … (+%d more)', count($values) - $limit)
            : $shown;
    }

    /**
     * Index the uploaded attachments by type plus their (lowercased) base file name.
     *
     * A cell names an attachment without its extension ({@see resolveAttachments}), so
     * the extension can never be the thing a mismatch turns on. Keying by type as well
     * as base name keeps a `.pdf` and an `.xlsx` that share a base name — the normal
     * case, since one invoice's PDF and Excel usually differ only by extension —
     * addressable independently instead of one silently shadowing the other.
     *
     * A name used twice within the same type keeps the first upload; the duplicate is
     * reported when a row asks for it only if the two genuinely differ, which the
     * uploader cannot act on anyway.
     *
     * @param  array<int,UploadedFile>  $attachments
     * @return array<string,UploadedFile>
     */
    private function indexAttachments(array $attachments): array
    {
        $indexed = [];

        foreach ($attachments as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $key = $this->attachmentCompositeKey($file->getClientOriginalName());

            if ($key === null || isset($indexed[$key])) {
                continue;
            }

            $indexed[$key] = $file;
        }

        return $indexed;
    }

    /**
     * The key an attachment is addressed by: its type plus its base file name.
     *
     * Null when the name's extension is neither a PDF nor an Excel file — such a file
     * can never satisfy `file_pdf` or `file_xls`, so it is never indexed or matched.
     */
    private function attachmentCompositeKey(string $name): ?string
    {
        $category = $this->attachmentCategory(pathinfo($name, PATHINFO_EXTENSION));

        if ($category === null) {
            return null;
        }

        $baseName = Str::lower(trim(pathinfo($name, PATHINFO_FILENAME)));

        return $baseName === '' ? null : "{$category}:{$baseName}";
    }

    /** Which family an extension belongs to, or null when it is neither PDF nor Excel. */
    private function attachmentCategory(string $extension): ?string
    {
        $extension = Str::lower($extension);

        return match (true) {
            $extension === 'pdf' => 'pdf',
            in_array($extension, ['xls', 'xlsx'], true) => 'xls',
            default => null,
        };
    }

    /**
     * Trim/normalise a raw row into a map keyed by canonical column keys.
     *
     * Header keys are lowercased and spaces collapsed to underscores so minor template
     * deviations ("SOA Number") still map onto the expected columns. Blank cells
     * collapse to null, and date cells are normalised so a sheet that was never
     * formatted as a date still imports.
     *
     * @param  array<string,mixed>  $rawRow
     * @return array<string,?string>
     */
    private function normalizeRow(array $rawRow): array
    {
        $normalized = [];

        foreach ($rawRow as $key => $value) {
            $canonical = Str::of((string) $key)->lower()->trim()->replace(' ', '_')->value();
            $stringValue = is_scalar($value) ? trim((string) $value) : '';
            $normalized[$canonical] = $stringValue === '' ? null : $stringValue;
        }

        // Ensure every known column exists so downstream reads are total.
        foreach (Col::ordered() as $column) {
            $normalized[$column] ??= null;
        }

        foreach (Col::dates() as $column) {
            $normalized[$column] = $this->normalizeDate($normalized[$column]);
        }

        // The account type is a single-letter code; case is not something to fail over.
        if ($normalized[Col::ACCOUNT_TYPE] !== null) {
            $normalized[Col::ACCOUNT_TYPE] = Str::upper($normalized[Col::ACCOUNT_TYPE]);
        }

        return $normalized;
    }

    /**
     * Convert an Excel serial date into an ISO date, leaving anything else untouched.
     *
     * A cell that was never formatted as a date exports as the number of days since
     * 1899-12-30, which no date rule would accept; translating it here means a
     * perfectly good spreadsheet is not rejected over a formatting detail.
     */
    private function normalizeDate(?string $value): ?string
    {
        if ($value === null || ! preg_match('/^\d{5}(\.\d+)?$/', $value)) {
            return $value;
        }

        return Carbon::create(1899, 12, 30)
            ->addDays((int) floor((float) $value))
            ->toDateString();
    }

    /**
     * Resolve every existence check in the rule set once, for the whole file.
     *
     * {@see CreateRequest} asks three questions that each cost a query: does this
     * account code exist on HMS, does this branch code, and is this SOA number still
     * free. Per row that is three round trips — and the SOA number check scans a table
     * with no index on that column — so a large batch would spend minutes on lookups
     * whose answers barely vary between rows.
     *
     * The rules are not replaced with something weaker: each is swapped for a check of
     * the same values, gathered in one query per column and reported with the same
     * message. What changes is how many times the database is asked, not what passes.
     *
     * @param  list<array<string,?string>>  $rows
     */
    private function primeLookups(array $rows, User $user): void
    {
        $injected = $this->injectedAttributes($user);

        foreach ($this->rules as $field => $fieldRules) {
            if (! is_array($fieldRules)) {
                continue;
            }

            foreach ($fieldRules as $index => $rule) {
                if ($rule instanceof IsServerDataExists) {
                    $column = $rule->column() ?? $field;
                    $existing = $this->existingValues(
                        DB::connection($rule->server()),
                        $rule->table(),
                        $column,
                        $this->distinctValues($rows, $field, $injected),
                        emptyOnFailure: true,
                    );

                    // Same verdict, same wording as the rule it stands in for.
                    $this->rules[$field][$index] = function (string $attribute, mixed $value, \Closure $fail) use ($existing, $column): void {
                        if (! isset($existing[Str::lower(trim((string) $value))])) {
                            $fail("The {$column} is invalid.");
                        }
                    };

                    continue;
                }

                if ($rule instanceof IsDataExists) {
                    // Always the same handful of ids — usually one, the uploader's —
                    // yet this rule re-reads the table list and the row for every line.
                    $existing = $this->existingValues(
                        DB::connection(),
                        $rule->table(),
                        'id',
                        $this->distinctValues($rows, $field, $injected),
                        emptyOnFailure: true,
                    );

                    $this->rules[$field][$index] = function (string $attribute, mixed $value, \Closure $fail) use ($existing): void {
                        if (! isset($existing[Str::lower(trim((string) $value))])) {
                            $fail("The {$attribute} is invalid.");
                        }
                    };

                    continue;
                }

                if ($rule instanceof Unique) {
                    // Unique renders itself as "unique:table,column"; read it rather
                    // than restating which table this rule happens to point at.
                    $parts = explode(',', Str::after((string) $rule, 'unique:'));
                    $table = trim($parts[0] ?? '');
                    $column = trim($parts[1] ?? $field);

                    if ($table === '') {
                        continue;
                    }

                    $taken = $this->existingValues(
                        DB::connection(),
                        $table,
                        $column,
                        $this->distinctValues($rows, $field, $injected),
                    );

                    $this->rules[$field][$index] = function (string $attribute, mixed $value, \Closure $fail) use ($taken, $field): void {
                        if (isset($taken[Str::lower(trim((string) $value))])) {
                            $fail(__('validation.unique', ['attribute' => str_replace('_', ' ', $field)]));
                        }
                    };
                }
            }
        }
    }

    /**
     * The key the client stamps each row with, carrying its line in the spreadsheet.
     *
     * Not a column of the template — it never reaches validation or the database.
     */
    private const LINE_KEY = '__row';

    /**
     * Which line of the uploaded spreadsheet a row came from.
     *
     * The client drops blank rows before sending, so a row's position in the payload
     * is not its position in the file — and on a sheet of thousands, "row 3847" is only
     * useful if it means row 3847 to the person who has the file open. The client
     * therefore stamps each row with where it actually came from; the index is a
     * fallback for a caller that did not.
     *
     * @param  array<string,?string>  $row
     */
    private function lineNumber(array $row, int $index): int
    {
        $line = $row[self::LINE_KEY] ?? null;

        return is_numeric($line) && (int) $line > 0 ? (int) $line : $index + 1;
    }

    /**
     * The fields the importer supplies itself rather than reading from the sheet.
     *
     * Mirrors {@see CreateRequest::prepareForValidation()}, which sets the same field
     * on the single-upload form. Defined once because two places need to agree on it:
     * the payload each row is validated as, and the value set the lookups are primed
     * with — a field primed from the sheet alone would be primed with nothing and then
     * reject every row.
     *
     * @return array<string,scalar>
     */
    private function injectedAttributes(User $user): array
    {
        return ['user_id' => $user->id];
    }

    /**
     * Every distinct non-empty value a column holds across the file.
     *
     * @param  list<array<string,?string>>  $rows
     * @param  array<string,scalar>  $injected  Values the importer adds to every row.
     * @return list<string>
     */
    private function distinctValues(array $rows, string $field, array $injected = []): array
    {
        $values = [];

        if (array_key_exists($field, $injected)) {
            $injectedValue = (string) $injected[$field];
            $values[Str::lower($injectedValue)] = $injectedValue;
        }

        foreach ($rows as $row) {
            $value = $row[$field] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            $values[Str::lower($value)] = $value;
        }

        return array_values($values);
    }

    /**
     * Which of the given values already exist in a table, as a lookup set.
     *
     * Chunked because SQL Server refuses a statement with more than 2100 bound
     * parameters, which a large batch would otherwise reach on its own.
     *
     * $emptyOnFailure keeps an existence check failing closed, the way the rules it
     * replaces do: an unreachable table means nothing can be confirmed to exist, so
     * every value is rejected. It must stay false for a uniqueness check, where an
     * empty set would mean "nothing is taken" and wave duplicates through.
     *
     * @param  \Illuminate\Database\ConnectionInterface  $connection
     * @param  list<string>  $values
     * @return array<string,true> lower(value) => true
     */
    private function existingValues(
        $connection,
        string $table,
        string $column,
        array $values,
        bool $emptyOnFailure = false,
    ): array {
        if ($values === []) {
            return [];
        }

        $found = [];

        try {
            foreach (array_chunk($values, 1000) as $chunk) {
                $rows = $connection->table($table)
                    ->whereIn($column, $chunk)
                    ->pluck($column);

                foreach ($rows as $value) {
                    $found[Str::lower(trim((string) $value))] = true;
                }
            }
        } catch (\Throwable $e) {
            if (! $emptyOnFailure) {
                throw $e;
            }

            Log::error('SoaBatchImportService: existence lookup failed; rejecting every value', [
                'table' => $table,
                'column' => $column,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        return $found;
    }

    /**
     * Validate one normalised row and resolve it into a persistable payload.
     *
     * @param  array<string,?string>  $row
     * @return array{0: ?array{row:int,soa_number:?string,messages:list<string>}, 1: ?array<string,mixed>}
     *                                                                                                     [errorEntry|null, resolvedRow|null] — exactly one is non-null.
     */
    private function validateRow(array $row, int $line, User $user): array
    {
        $messages = [];
        $unresolved = [];
        $soaNumber = $row[Col::SOA_NUMBER];

        // Resolve the named attachments first so CreateRequest's file rules run
        // against the real uploads, exactly as they do on the single-upload form.
        $files = $this->resolveAttachments($row, $line, $messages, $unresolved);

        $accountCode = $row[Col::ACCOUNT_CODE];
        $declaredType = $row[Col::ACCOUNT_TYPE];
        $derivedType = AccountType::fromAccountCode($accountCode);

        $payload = array_merge(
            Arr::except($row, [...Col::attachments(), self::LINE_KEY]),
            $files,
            $this->injectedAttributes($user),
            // CreateRequest requires an account type, and the form supplies one the
            // same way — from the code. A blank cell is not the uploader failing to
            // fill something in; it is a value this application would overwrite anyway.
            [Col::ACCOUNT_TYPE => $declaredType ?? $derivedType],
        );

        $validator = Validator::make($payload, $this->rules, $this->ruleMessages);

        if ($validator->fails()) {
            foreach ($validator->errors()->messages() as $field => $fieldMessages) {
                // A column whose attachment could not be resolved has already been
                // reported by name; "the file pdf field is required" only repeats it.
                if (in_array($field, $unresolved, true)) {
                    continue;
                }

                $messages = array_merge($messages, array_values($fieldMessages));
            }
        }

        // Duplicate detection within the same file: the unique rule above only sees
        // rows already persisted, and this batch is not written until every row passes
        // — or, with `skip_errors`, until every row that passes is written. A number is
        // only *claimed* below once this row is confirmed to have no other problems, so
        // a row that fails for an unrelated reason never falsely marks a later, genuinely
        // valid row as a duplicate of a row that was never actually going to be saved.
        $soaNumberKey = $soaNumber !== null ? Str::lower($soaNumber) : null;

        if ($soaNumberKey !== null && isset($this->seenSoaNumbers[$soaNumberKey])) {
            $messages[] = "Duplicate SOA number '{$soaNumber}' — already used on row {$this->seenSoaNumbers[$soaNumberKey]}.";
        }

        // The account type is derived from the account code everywhere else in the
        // application ({@see AccountType::fromAccountCode}), so a sheet that disagrees
        // is reporting one of the two wrong rather than describing a third kind. Only
        // a cell the uploader actually filled in can disagree.
        if (
            $accountCode !== null
            && $declaredType !== null
            && $declaredType !== AccountType::TPA_HMO
            && $declaredType !== $derivedType
        ) {
            $messages[] = "Account type '{$declaredType}' does not match account code '{$accountCode}', which is "
                . AccountType::label($derivedType) . '.';
        }

        if ($messages !== []) {
            return [[
                'row' => $line,
                'soa_number' => $soaNumber,
                'messages' => array_values(array_unique($messages)),
            ], null];
        }

        if ($soaNumberKey !== null) {
            $this->seenSoaNumbers[$soaNumberKey] = $line;
        }

        $attributes = $validator->validated();

        // Mirror CreateRequest::passedValidation(): the stored type is the derived one.
        $attributes[Col::ACCOUNT_TYPE] = $derivedType;

        // The files are carried separately; they become paths once stored.
        return [null, [
            'attributes' => Arr::except($attributes, Col::attachments()),
            'files' => $files,
        ]];
    }

    /**
     * Match a row's attachment columns to the uploaded files, by type and base name.
     *
     * A cell names an attachment without its extension — {@see attachmentCompositeKey}
     * is what turns that into the key {@see indexAttachments} filed the real upload
     * under, so `file_pdf` can only ever resolve to a PDF and `file_xls` only ever to
     * an Excel file, whatever extension the cell happens to also include.
     *
     * A named-but-missing attachment is reported here rather than left to the `required`
     * rule, so the message names the file the uploader has to go and add. An attachment
     * claimed by an earlier row is refused too: storing a file moves it, so the second
     * invoice would be saved pointing at a file that is no longer there.
     *
     * @param  array<string,?string>  $row
     * @param  list<string>  $messages  Collected by reference.
     * @param  list<string>|null  $unresolved  Columns left without a file, by reference.
     * @return array<string,UploadedFile>
     */
    private function resolveAttachments(array $row, int $line, array &$messages, ?array &$unresolved = null): array
    {
        $files = [];
        $unresolved = [];

        foreach (Col::attachments() as $column) {
            $name = $row[$column];

            if ($name === null) {
                continue;
            }

            $category = $column === Col::FILE_PDF ? 'pdf' : 'xls';
            $baseName = Str::lower(trim(pathinfo(basename($name), PATHINFO_FILENAME)));
            $key = "{$category}:{$baseName}";
            $file = $this->attachments[$key] ?? null;

            if ($file === null) {
                $messages[] = "Attachment '{$name}' for {$column} was not found among the uploaded attachments.";
                $unresolved[] = $column;

                continue;
            }

            if (isset($this->claimedAttachments[$key])) {
                $messages[] = "Attachment '{$name}' is already used by row {$this->claimedAttachments[$key]}; "
                    . 'each billing invoice needs its own file.';
                $unresolved[] = $column;

                continue;
            }

            $this->claimedAttachments[$key] = $line;
            $files[$column] = $file;
        }

        return $files;
    }

    /**
     * Store every row's attachments and save its invoice, in one transaction.
     *
     * Storing an attachment moves it onto the billing disk, which is not something the
     * database transaction can undo. A batch that fails on its hundredth row therefore
     * rolls back the ninety-nine invoices before it while their files stay on the
     * share, referenced by nothing and indistinguishable from live ones. Every path
     * written here is recorded as it is written, and swept up again if the transaction
     * does not commit.
     *
     * @param  list<array{attributes:array<string,mixed>,files:array<string,UploadedFile>}>  $rows
     * @return list<Soa>
     */
    private function persist(array $rows): array
    {
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($rows, &$storedPaths) {
                $created = [];

                foreach ($rows as $row) {
                    $attributes = $row['attributes'];

                    // CommonHelper owns the storage path and file-naming convention, and
                    // reads its files off a request; the row's uploads are wrapped in one
                    // so the batch and the single upload store files identically.
                    CommonHelper::storeUploadedFiles(
                        $attributes[Col::SOA_NUMBER],
                        $attributes[Col::ACCOUNT_CODE],
                        $attributes[Col::BRANCH_CODE] ?? null,
                        Request::create('/', 'POST', [], [], $row['files']),
                        $attributes,
                    );

                    // storeUploadedFiles() replaces each attachment column with the path
                    // it wrote to; that path is the only handle on the file from here.
                    foreach (Col::attachments() as $column) {
                        $path = $attributes[$column] ?? null;

                        if (is_string($path) && $path !== '') {
                            $storedPaths[] = $path;
                        }
                    }

                    $created[] = $this->soa->saveSoa($attributes);
                }

                return $created;
            });
        } catch (\Throwable $e) {
            $this->discardStoredFiles($storedPaths);

            throw $e;
        }
    }

    /**
     * Delete the attachments written by a batch that then failed to commit.
     *
     * Best-effort, and never allowed to become the error the caller sees: it runs while
     * an exception that matters more is already unwinding, and a file that will not
     * delete is untidy rather than incorrect. Failures are logged with the path so the
     * share can still be reconciled by hand.
     *
     * @param  list<string>  $paths
     */
    private function discardStoredFiles(array $paths): void
    {
        if ($paths === []) {
            return;
        }

        try {
            $disk = Storage::disk(config('vc.disks.billing'));

            foreach ($paths as $path) {
                $disk->delete($path);
            }

            // Reported by the failure entry {@see recordFailure()}.
            $this->discardedAttachments = count($paths);
        } catch (\Throwable $e) {
            Log::error('SoaBatchImportService: attachments of a rolled-back batch could not be removed', [
                'paths' => $paths,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Queue the "new billing invoice" notification for each imported invoice.
     *
     * Queued rather than sent inline, which is the difference between one batch and one
     * invoice: a full batch would otherwise hold the request open for that many SMTP
     * round trips. A worker must be running for these to go out.
     *
     * Best-effort and per invoice: the batch is already committed by this point, so a
     * failure to enqueue is logged and the remaining notifications still go out.
     *
     * @param  list<Soa>  $soas
     */
    private function notify(array $soas, User $user): void
    {
        foreach ($soas as $soa) {
            if (empty($soa->file_pdf) || empty($soa->account_code)) {
                continue;
            }

            try {
                CommonHelper::sendBillingInvoiceEmail($soa, $user, NewBillingInvoiceUploaded::class, queue: true);
            } catch (\Throwable $e) {
                Log::error('SoaBatchImportService: notification could not be queued', [
                    'soa_number' => $soa->soa_number,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
