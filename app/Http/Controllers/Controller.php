<?php

namespace App\Http\Controllers;

use Closure;
use Illuminate\Database\QueryException;
use Inertia\Inertia;

abstract class Controller
{
    /**
     * Flash a toast shown by the frontend's sonner Toaster.
     *
     * @param  'success'|'error'|'info'|'warning'  $type
     */
    protected function toast(string $message, string $type = 'success'): void
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);
    }

    /**
     * Run a delete that may hit a restrictOnDelete foreign key, turning the
     * DB error into a friendly Arabic toast instead of a 500.
     */
    protected function deleteSafely(Closure $delete, string $blockedMessage): bool
    {
        try {
            $delete();
        } catch (QueryException) {
            $this->toast($blockedMessage, 'error');

            return false;
        }

        return true;
    }
}
