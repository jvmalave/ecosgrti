<?php

declare(strict_types=1);

namespace App\Domains\Core\Exceptions;

use Exception;

class SequentialityViolationException extends Exception
{
    // Excepción puramente semántica para identificar rupturas lógicas en el cronograma
}