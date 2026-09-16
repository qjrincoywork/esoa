<?php

namespace App\Http\Resources;

use App\Enums\OldRemarkSide;
use App\Enums\OldRemarkType;
use App\Helpers\CommonHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One message of the legacy eSOA conversation (`remarks` on the SOA server).
 *
 * Shapes a row the way the previous system's chat read it: the author resolved to an
 * HMS display name when it is a login, which side of the thread the message belongs
 * to, the remark type the client filed it under, and the file it carried. Author names
 * come from the memo {@see CommonHelper::primeSystemUserNames()} fills, so a page of
 * messages costs a single HMS lookup instead of one per row.
 */
class OldRemarkResource extends JsonResource
{
    /**
     * Transform the legacy remark into a conversation row.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $side = OldRemarkSide::fromFlag($this->rem_isVC);

        return [
            'id' => $this->rem_id,
            'author' => $this->author(),
            'side' => $side,
            'side_label' => OldRemarkSide::label($side),
            'is_value_care' => $side === OldRemarkSide::VALUE_CARE,
            'message' => CommonHelper::convertStringEncoding($this->rem_remark),
            'type_label' => OldRemarkType::label($this->rem_to),
            'attachment' => trim((string) $this->rem_filename) ? 'Click to View Attached File': null,
            // 'attachment' => trim((string) $this->rem_filename) ?: null,
            'attachment_preview_token' => $this->previewToken($request),
            'created_at' => CommonHelper::formatDate($this->rem_date, true),
        ];
    }

    /**
     * Display name of whoever posted the message.
     *
     * ValueCare rows store an HMS login, client rows already store the company name,
     * so an unresolved value is shown as recorded — legacy threads reference staff
     * accounts that no longer exist in HMS.
     */
    protected function author(): string
    {
        $by = trim((string) $this->rem_by);

        return CommonHelper::systemUserName($by)
            ?? (CommonHelper::convertStringEncoding($by) ?: 'Unknown');
    }

    /**
     * Short-lived, user-bound token that streams the file the message carried.
     *
     * These files never moved: they still sit in the previous system's
     * `chat_attachments` tree, which the server can read as a directory but a browser
     * cannot open as a URL. So the app serves the bytes from its own origin — see
     * {@see \App\Http\Controllers\SoaController::previewOldRemarkFile()} — and the
     * client only ever holds an encrypted token, never a legacy path.
     *
     * Null when the message carried no file, nobody is authenticated, or the legacy row
     * names something this app will not serve.
     */
    protected function previewToken(Request $request): ?string
    {
        $path = $this->attachmentPath();

        if ($path === null || ! $request->user()) {
            return null;
        }

        return CommonHelper::createFilePreviewToken(
            config('vc.disks.legacy_chat'),
            $path,
            (int) $request->user()->id
        );
    }

    /**
     * Disk-relative path of the attachment: `{macode}/{soa number}/{file name}`, the
     * layout the legacy app wrote.
     *
     * Every segment comes out of the legacy database, so none of them is trusted to be
     * a well-formed path component: anything empty, or carrying a separator, traversal
     * marker or null byte, disqualifies that one message rather than failing the page
     * it appears on.
     */
    protected function attachmentPath(): ?string
    {
        $segments = array_map(
            fn ($segment) => trim((string) $segment),
            [$this->rem_macode, $this->rem_refid, $this->rem_filename]
        );

        foreach ($segments as $segment) {
            if (
                $segment === ''
                || str_contains($segment, '/')
                || str_contains($segment, '\\')
                || str_contains($segment, '..')
                || str_contains($segment, "\0")
            ) {
                return null;
            }
        }

        return implode('/', $segments);
    }
}
