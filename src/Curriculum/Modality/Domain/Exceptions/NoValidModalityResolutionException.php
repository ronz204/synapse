<?php

declare(strict_types=1);

namespace Src\Curriculum\Modality\Domain\Exceptions;

use DomainException;

/**
 * The write-time gate's rejection. Message text is cited verbatim by this
 * slice's acceptance criteria — do not reword it.
 */
final class NoValidModalityResolutionException extends DomainException
{
    public static function forCourse(int $courseId): self
    {
        return new self('No valid modality resolution exists for this course.');
    }
}
