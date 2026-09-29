<?php

namespace App\Services\Odoo\Exceptions;

use RuntimeException;

/** Odoo answered with an error (bad data, access rights...) — retrying won't help. */
class OdooRejectedException extends RuntimeException {}
