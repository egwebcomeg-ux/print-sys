<?php

namespace App\Services\Odoo;

use App\Services\Odoo\Exceptions\OdooRejectedException;
use App\Services\Odoo\Exceptions\OdooTransientException;

interface OdooClientInterface
{
    /**
     * Call `object.execute_kw` on a model method.
     *
     * @param  list<mixed>  $args
     * @param  array<string, mixed>  $kwargs
     *
     * @throws OdooTransientException network/timeout/5xx — safe to retry
     * @throws OdooRejectedException Odoo returned an error — retrying won't help
     */
    public function executeKw(string $model, string $method, array $args, array $kwargs = []): mixed;
}
