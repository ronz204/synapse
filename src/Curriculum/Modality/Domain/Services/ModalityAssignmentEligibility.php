<?php

declare(strict_types=1);

namespace Src\Curriculum\Modality\Domain\Services;

use Carbon\CarbonImmutable;

/**
 * Pure rule: "can this modality be assigned to a course right now?" A
 * modality that doesn't require a resolution is always eligible; one that
 * does needs at least one currently-valid resolution among the ones
 * supplied — more than one may legitimately exist (e.g. a renewal filed
 * before the previous one expires), so this only needs one to be valid,
 * not exactly one.
 *
 * Mirrors ModalityResolution::scopeValid() (app/Models/ModalityResolution.php)
 * exactly — valid_from has passed, and either there's no valid_to or it
 * hasn't been reached yet — expressed without reading now() internally, so
 * a caller controls "as of when" explicitly.
 */
final class ModalityAssignmentEligibility
{
    /**
     * @param  array<int, array{validFrom: CarbonImmutable, validTo: ?CarbonImmutable}>  $resolutions
     */
    public function isSatisfied(bool $requiresResolution, array $resolutions, CarbonImmutable $asOf): bool
    {
        if (! $requiresResolution) {
            return true;
        }

        foreach ($resolutions as $resolution) {
            if ($this->isCurrentlyValid($resolution, $asOf)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{validFrom: CarbonImmutable, validTo: ?CarbonImmutable}  $resolution
     */
    private function isCurrentlyValid(array $resolution, CarbonImmutable $asOf): bool
    {
        return $resolution['validFrom']->lessThanOrEqualTo($asOf)
            && ($resolution['validTo'] === null || $resolution['validTo']->greaterThanOrEqualTo($asOf));
    }
}
