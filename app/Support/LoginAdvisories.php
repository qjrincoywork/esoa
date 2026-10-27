<?php

namespace App\Support;

use App\Models\Advisory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Show the live advisories once per sign-in, the way most portals present a service
 * notice: as a pop-up on the first page after logging in, not on every page after it.
 *
 * Two halves, so the rule lives in one place:
 *  - {@see queue()} runs on the Login event ({@see \App\Listeners\QueueLoginAdvisories})
 *    and only marks the session — nothing is looked up while the user is signing in;
 *  - {@see pull()} runs when the next full page is rendered and consumes the mark, so
 *    the advisories travel with exactly one response. The lookup happens there, which
 *    means an advisory switched off in between is never shown.
 */
class LoginAdvisories
{
    /** Session key holding the "show advisories on the next page" mark. */
    private const SESSION_KEY = 'login_advisories.pending';

    /**
     * Mark the session so the next rendered page shows the live advisories.
     */
    public static function queue(Request $request): void
    {
        if ($request->hasSession()) {
            $request->session()->put(self::SESSION_KEY, true);
        }
    }

    /**
     * Consume the mark and return the advisories to show, or null when there is
     * nothing to show (no mark, or nothing live).
     *
     * The `key` is unique per sign-in, so the client can tell a fresh notice from the
     * same props replayed by browser back/forward and not pop it up twice.
     *
     * @return array{key: string, items: array<int, array<string, mixed>>}|null
     */
    public static function pull(Request $request): ?array
    {
        if (!$request->hasSession() || !$request->session()->pull(self::SESSION_KEY, false)) {
            return null;
        }

        $items = Advisory::query()
            ->live()
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get()
            ->map(fn (Advisory $advisory): array => self::present($advisory))
            ->values()
            ->all();

        return $items === [] ? null : [
            'key' => (string) Str::uuid(),
            'items' => $items,
        ];
    }

    /**
     * What the pop-up needs of one advisory — nothing about who wrote it or when.
     *
     * @return array<string, mixed>
     */
    public static function present(Advisory $advisory): array
    {
        return [
            'id' => $advisory->id,
            'title' => $advisory->title,
            'message' => $advisory->message,
            'image_url' => self::imageUrl($advisory),
        ];
    }

    /**
     * The URL the advisory's image is streamed from, versioned by the last update so a
     * replaced image is never served from the browser cache.
     */
    public static function imageUrl(Advisory $advisory): ?string
    {
        if (!$advisory->image) {
            return null;
        }

        return route('advisories.image', [
            'id' => $advisory->id,
            'v' => $advisory->updated_at?->timestamp,
        ], absolute: false);
    }
}
