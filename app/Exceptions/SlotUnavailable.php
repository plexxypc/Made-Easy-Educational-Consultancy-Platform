<?php

namespace App\Exceptions;

/**
 * A consultation slot can't be taken right now (full, cancelled, too soon,
 * or being booked by someone else this instant). The message is safe to
 * show to customers.
 */
class SlotUnavailable extends BusinessRuleException {}
