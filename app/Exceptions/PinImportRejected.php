<?php

namespace App\Exceptions;

/**
 * A PIN upload was refused as a whole. `problems` lists what to fix, by line
 * number, without repeating any PIN values.
 */
class PinImportRejected extends BusinessRuleException
{
    /**
     * @param  string  $message  Summary safe to show to admins.
     * @param  array<int, string>  $problems  e.g. "Line 4: duplicate of line 2."
     */
    public function __construct(string $message, public readonly array $problems = [])
    {
        parent::__construct($message);
    }
}
