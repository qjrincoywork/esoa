<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The request details stamped beside an audit entry.
 *
 * Spatie already records who caused a change; what it cannot know is where the change
 * came from, which is the difference between "this user changed it" and "this user
 * changed it from this address, through this route".
 *
 * It lives here rather than inside {@see LogsAuditActivity} because not every audited
 * action is a model event: a rejected batch upload writes no model at all, and an entry
 * about it should still carry the same context, shaped the same way. One definition, so
 * the two kinds of entry cannot describe their origin differently.
 */
final class AuditContext
{
    /**
     * The parts of a request worth keeping beside an audit entry.
     *
     * @return array<string, string>
     */
    public static function forRequest(?Request $request): array
    {
        if ($request === null) {
            return [];
        }

        return array_filter([
            'ip' => $request->ip(),
            // Bounded: some agents run to kilobytes, and the trail is not the place.
            'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
            'route' => $request->route()?->getName(),
            'method' => $request->method(),
        ], static fn ($value): bool => $value !== null && $value !== '');
    }
}
