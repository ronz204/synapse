<?php

declare(strict_types=1);

namespace App\Support\Performance;

/**
 * The four classes of user interaction the performance budgets are defined
 * against (specs/002-perceived-performance/data-model.md, entity 1).
 *
 * Deliberately closed. Every measurable interaction in the application belongs
 * to exactly one of these; an interaction that fits none of them means the
 * harness is measuring something the spec does not cover, and should be
 * rejected rather than forced into a class it does not belong to. Adding a
 * fifth case is a signal that a budget is wrong, not that a case is missing.
 */
enum InteractionClass: string
{
    /** First load after signing in, with nothing cached on the machine. */
    case AppBoot = 'AppBoot';

    /** Pressing a sidebar entry through to usable content. */
    case ModuleOpen = 'ModuleOpen';

    /** Sorting, paginating, searching or filtering inside an open list. */
    case InModule = 'InModule';

    /** Saving, assigning, deleting, uploading, requesting an export. */
    case Write = 'Write';

    public function label(): string
    {
        return match ($this) {
            self::AppBoot => __('Application boot'),
            self::ModuleOpen => __('Module open'),
            self::InModule => __('In-module interaction'),
            self::Write => __('Write action'),
        };
    }
}
