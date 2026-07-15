<?php

namespace App\Services\Couriers\FraudCheck;

use App\Models\Store;

interface CourierFraudCheckInterface
{
    /**
     * Query the courier's delivery-history / fraud API for a phone number.
     *
     * @param Store  $store store whose courier credentials to use
     * @param string $phone normalized BD mobile (01XXXXXXXXX)
     *
     * @return array{
     *   summary: array{total:int,delivered:int,returned:int,cancelled:int,pending:int,success_rate:?float,return_rate:?float},
     *   risk: ?array{level:string,label:string,message:string},  // only when the courier itself provides one
     *   raw_reference: mixed,
     * }
     *
     * @throws FraudCheckException with an operator-safe message
     */
    public function check(Store $store, string $phone): array;
}
