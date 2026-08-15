<?php

declare(strict_types=1);

use Src\Curriculum\Modality\Domain\Services\DefaultModalityRule;

it('resolves the default modality name to Presencial', function (): void {
    expect(DefaultModalityRule::name())->toBe('Presencial');
});
