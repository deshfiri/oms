<?php

namespace App\Services\Couriers;

/**
 * Thrown when a store's courier configuration is incomplete or invalid,
 * preventing the booking from starting. Always contains a human-readable
 * message suitable for showing to the operator.
 */
class CourierConfigException extends \RuntimeException {}
