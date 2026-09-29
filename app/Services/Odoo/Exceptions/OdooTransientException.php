<?php

namespace App\Services\Odoo\Exceptions;

use RuntimeException;

/** Network error, timeout or 5xx — the queued sync will retry. */
class OdooTransientException extends RuntimeException {}
