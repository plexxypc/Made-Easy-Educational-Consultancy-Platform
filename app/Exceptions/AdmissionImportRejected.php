<?php

namespace App\Exceptions;

/**
 * An admission requirements CSV was refused as a whole. `problems` lists
 * what to fix, by line number.
 */
class AdmissionImportRejected extends BusinessRuleException
{
    /**
     * @param  string  $message  Summary safe to show to staff.
     * @param  array<int, string>  $problems  e.g. "Line 4: the cut-off mark must be a whole number from 1 to 400."
     */
    public function __construct(string $message, public readonly array $problems = [])
    {
        parent::__construct($message);
    }
}
