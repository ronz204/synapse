<?php

declare(strict_types=1);

namespace App\Concerns;

/**
 * Regex fragments shared by every Form Object that persists free-text input,
 * so the same character-class decision isn't repeated (and doesn't drift)
 * across a dozen unrelated forms. Only the pattern is shared here —
 * required/nullable and max length stay form-specific, since those
 * genuinely vary per field.
 */
trait TextPatternValidationRules
{
    /**
     * For human-readable labels persisted as data: plan/course/modality/role
     * names, a resolution's approving body. Allows letters (incl. accented
     * Spanish characters), digits, spaces, and the punctuation this
     * project's own data actually uses (periods, commas, apostrophes,
     * hyphens, parentheses, slashes — e.g. "Plan Demo 2020 (Terminal)",
     * "Ingeniería de Software I"). Excludes everything with no place in a
     * name: angle/curly/square brackets, @ # $ % ^ & * + = | \ ~ ` and the
     * like.
     */
    protected function properNamePatternRule(): string
    {
        return "regex:/^[\\p{L}\\p{N} .,'()\\/-]+$/u";
    }

    /**
     * For short institutional codes persisted as data: a course code, an
     * official resolution number (e.g. "DEMO-101", "R-VOL-00001"). Letters,
     * digits, hyphens and underscores only — no spaces or punctuation, since
     * these are identifiers meant to be typed/matched exactly.
     */
    protected function institutionalCodePatternRule(): string
    {
        return 'regex:/^[A-Za-z0-9_-]+$/';
    }

    /**
     * For snake_case system identifiers persisted as data: a Permission's
     * `module`/`action` (e.g. "study_plans", "export_pdf") — lowercase
     * letters, digits and underscores, starting with a letter.
     */
    protected function identifierPatternRule(): string
    {
        return 'regex:/^[a-z][a-z0-9_]*$/';
    }
}
