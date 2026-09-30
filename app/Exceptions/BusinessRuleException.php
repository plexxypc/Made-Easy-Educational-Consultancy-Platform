<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by Action classes when a request breaks a business rule (invalid
 * status transition, self-demotion, ...). The message is safe to show to staff.
 */
class BusinessRuleException extends RuntimeException {}
