<?php

declare(strict_types=1);

namespace App\Exceptions;

use InvalidArgumentException;

class SelfMessageException extends InvalidArgumentException
{
    public function __construct()
    {
        parent::__construct('Cannot send a message to yourself.');
    }
}
