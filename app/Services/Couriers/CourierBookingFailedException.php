<?php

namespace App\Services\Couriers;

/**
 * Thrown when "Send to courier" could not obtain a REAL consignment from the
 * courier API. No consignment is created and the order's status does NOT change
 * — it stays in Processing so the operator can fix the cause and retry. Nothing
 * fake or manual is ever written.
 */
class CourierBookingFailedException extends \RuntimeException
{
}
