<?php

namespace App\Services\Couriers;

/**
 * Thrown when an adapter cannot produce a REAL consignment from the courier
 * (no direct API, or credentials missing). The booker catches this and creates
 * a "pending" consignment with no id — it never fabricates one.
 */
class CourierUnavailableException extends \RuntimeException
{
}
