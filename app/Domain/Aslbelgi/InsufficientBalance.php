<?php

namespace App\Domain\Aslbelgi;

/** The balance does not cover a service order; nothing was started or charged. */
class InsufficientBalance extends \RuntimeException
{
    public function __construct(public readonly float $shortfall)
    {
        parent::__construct('insufficient balance');
    }
}
