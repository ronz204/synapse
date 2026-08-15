<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Src\Curriculum\Modality\Domain\Services\ModalityAssignmentEligibility;

beforeEach(function (): void {
    $this->service = new ModalityAssignmentEligibility;
    $this->asOf = CarbonImmutable::parse('2026-06-15');
});

it('is always satisfied when the modality does not require a resolution', function (): void {
    expect($this->service->isSatisfied(false, [], $this->asOf))->toBeTrue();
});

it('is not satisfied when the modality requires a resolution and none exist', function (): void {
    expect($this->service->isSatisfied(true, [], $this->asOf))->toBeFalse();
});

it('is satisfied by a currently-valid open-ended resolution', function (): void {
    $resolutions = [
        ['validFrom' => $this->asOf->subDay(), 'validTo' => null],
    ];

    expect($this->service->isSatisfied(true, $resolutions, $this->asOf))->toBeTrue();
});

it('is not satisfied when the only resolution on file has already expired', function (): void {
    $resolutions = [
        ['validFrom' => $this->asOf->subYears(2), 'validTo' => $this->asOf->subYear()],
    ];

    expect($this->service->isSatisfied(true, $resolutions, $this->asOf))->toBeFalse();
});

it('is satisfied when at least one of several resolutions is currently valid', function (): void {
    $resolutions = [
        ['validFrom' => $this->asOf->subYears(2), 'validTo' => $this->asOf->subYear()],
        ['validFrom' => $this->asOf->subDay(), 'validTo' => null],
    ];

    expect($this->service->isSatisfied(true, $resolutions, $this->asOf))->toBeTrue();
});

it('is not satisfied by a resolution whose validity window starts in the future', function (): void {
    $resolutions = [
        ['validFrom' => $this->asOf->addMonth(), 'validTo' => null],
    ];

    expect($this->service->isSatisfied(true, $resolutions, $this->asOf))->toBeFalse();
});

it('treats the exact validFrom instant as already valid (inclusive lower bound)', function (): void {
    $resolutions = [
        ['validFrom' => $this->asOf, 'validTo' => null],
    ];

    expect($this->service->isSatisfied(true, $resolutions, $this->asOf))->toBeTrue();
});

it('treats the exact validTo instant as still valid (inclusive upper bound)', function (): void {
    $resolutions = [
        ['validFrom' => $this->asOf->subYear(), 'validTo' => $this->asOf],
    ];

    expect($this->service->isSatisfied(true, $resolutions, $this->asOf))->toBeTrue();
});
