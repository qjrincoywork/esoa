<?php

namespace App\Rules;

use App\Models\NavigationModule;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The parent (ref_id) a navigation module may hang off.
 *
 * Modules form a two-level tree — a top-level module and its actions — which is what the
 * sidebar, the action columns and the navigation details pane all read. So the parent
 * must be a live top-level module, must not be the module itself, and a module that
 * already has submodules cannot become a submodule, or its children would fall a level
 * below anything that renders them.
 */
class ValidParentModule implements ValidationRule
{
    /**
     * @param  int|null  $moduleId  The module being edited; null when creating.
     */
    public function __construct(private readonly ?int $moduleId = null)
    {
    }

    /**
     * Fail with a message naming which of the tree constraints the parent breaks.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $parentId = (int) $value;

        if ($this->moduleId !== null && $parentId === $this->moduleId) {
            $fail('A module cannot be its own parent.');

            return;
        }

        $parent = NavigationModule::query()->select(['id', 'ref_id'])->find($parentId);

        if (!$parent) {
            $fail('The selected parent module does not exist.');

            return;
        }

        if ($parent->ref_id !== null) {
            $fail('The parent module must be a top-level module.');

            return;
        }

        if ($this->moduleId !== null && NavigationModule::query()->where('ref_id', $this->moduleId)->exists()) {
            $fail('This module has submodules of its own, so it must stay top-level.');
        }
    }
}
