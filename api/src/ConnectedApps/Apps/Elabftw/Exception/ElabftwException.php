<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Elabftw\Exception;

class ElabftwException extends \Exception
{
    public function __construct(string $message, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
