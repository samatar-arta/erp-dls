<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Violation d'une règle métier (message destiné à l'utilisateur, en français).
 */
class RegleMetierException extends RuntimeException
{
}
