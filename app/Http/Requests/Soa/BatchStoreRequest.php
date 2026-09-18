<?php

namespace App\Http\Requests\Soa;

use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validates the envelope of a batch billing-invoice upload.
 *
 * Only the shape of the payload is enforced here (a bounded list of row objects plus
 * the attachments they reference). Per-row business validation is delegated to
 * {@see \App\Services\SoaBatchImportService}, which runs every row through the very
 * rules {@see CreateRequest} applies to a single upload and refuses the whole file
 * when any one row fails, so nothing is half-imported.
 */
class BatchStoreRequest extends FormRequest
{
    /**
     * Authorize superadmin/admin roles or users holding the "soas.batch_store"
     * permission, mirroring {@see CreateRequest} for the single-upload form.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && (
            $user->hasAnyRole(['superadmin', 'admin']) ||
            $user->hasAnyPermission(['soas.batch_store'])
        );
    }

    /**
     * Validate that the request carries a bounded list of rows and attachments.
     *
     * The attachment rules are deliberately permissive about *which* file belongs to
     * which row — that mapping is by file name and is resolved per row by the importer;
     * here each upload only has to be a readable PDF/XLS within the size limit.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rows' => ['required', 'array', 'min:1', 'max:' . config('vc.soa_batch.max_rows')],
            'rows.*' => ['array'],
            'attachments' => ['required', 'array', 'min:1', 'max:' . config('vc.soa_batch.max_attachments')],
            'attachments.*' => [
                'file',
                'mimes:pdf,xls,xlsx',
                'max:' . config('vc.max_file_size'), // 2MB (size is in KB)
            ],
        ];
    }

    /**
     * Human-readable messages for the envelope-level failures.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rows.required' => 'No rows were found in the uploaded file.',
            'rows.array' => 'The uploaded data is not in the expected format.',
            'rows.min' => 'The uploaded file must contain at least one row.',
            'rows.max' => 'You can upload at most :max billing invoices at a time.',
            'attachments.required' => 'Upload the PDF and Excel attachments referenced by the rows.',
            'attachments.max' => 'You can attach at most :max files at a time.',
            'attachments.*.mimes' => 'Each attachment must be a PDF, XLS or XLSX file.',
            'attachments.*.max' => 'Each attachment may not be larger than :max kilobytes.',
        ];
    }

    /**
     * Decode the JSON-encoded rows into an array.
     *
     * The rows travel as a JSON string because the attachments ride along in the same
     * multipart body, which cannot carry nested objects on its own.
     */
    protected function prepareForValidation(): void
    {
        $this->assertBodyWasAccepted();

        $rows = $this->input('rows');

        if (is_string($rows)) {
            $decoded = json_decode($rows, true);

            $this->merge(['rows' => is_array($decoded) ? $decoded : []]);
        }
    }

    /**
     * Fail clearly when PHP threw the upload away for being too large.
     *
     * Past `post_max_size` PHP discards the entire body — fields and files alike — and
     * hands the application an empty request with the original Content-Length still on
     * it. Every rule below would then report the same upload as "no rows were found",
     * sending the user off to check a spreadsheet that was never the problem. This is
     * the one case worth naming before validation starts.
     */
    private function assertBodyWasAccepted(): void
    {
        $declaredLength = (int) $this->server('CONTENT_LENGTH', 0);

        if ($declaredLength > 0 && $this->post() === [] && $this->allFiles() === []) {
            abort(
                Response::HTTP_REQUEST_ENTITY_TOO_LARGE,
                'The upload is larger than this server accepts (post_max_size is '
                    . ini_get('post_max_size') . '). Split it into smaller batches, or raise the limit.'
            );
        }
    }
}
