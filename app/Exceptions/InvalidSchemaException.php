<?php

namespace App\Exceptions;

use Exception;

class InvalidSchemaException extends Exception
{
    /** @param string[] $errors */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Form schema failed validation: '.implode(' | ', $errors));
    }
}
