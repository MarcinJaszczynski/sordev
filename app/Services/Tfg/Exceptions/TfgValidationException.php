<?php

namespace App\Services\Tfg\Exceptions;

class TfgValidationException extends TfgException
{
    public function __construct(
        string $message,
        public readonly array $errors = [],
    ) {
        parent::__construct($message);
    }
}
