<?php

declare(strict_types=1);

namespace Src\Curriculum\Modality\Domain\Exceptions;

use DomainException;

final class ModalityResolutionDocumentRequiredException extends DomainException
{
    public static function missing(): self
    {
        return new self('You must attach the document that backs this modality resolution.');
    }
}
