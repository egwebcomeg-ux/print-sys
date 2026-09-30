<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Single source of truth for who may do what. Each ability becomes a Gate
 * (used as `can:` route middleware) and is shared with the frontend so
 * buttons are hidden for roles that can't use them.
 */
class Abilities
{
    /** @var array<string, list<UserRole>> */
    public const MAP = [
        // Catalogue
        'manage-catalog' => [UserRole::Admin],                              // paper types, suppliers, dies, presses
        'manage-paper-prices' => [UserRole::Admin, UserRole::Sales],        // supplier prices move often
        'update-press-backlog' => [UserRole::Admin, UserRole::Production],
        'manage-settings' => [UserRole::Admin],                             // pricing constants
        'manage-users' => [UserRole::Admin],

        // CRM
        'manage-customers' => [UserRole::Admin, UserRole::Sales],
        'manage-leads' => [UserRole::Admin, UserRole::Sales],

        // Jobs
        'create-jobs' => [UserRole::Admin, UserRole::Sales],
        'advance-sales-status' => [UserRole::Admin, UserRole::Sales],       // draft → quoted → approved
        'run-production' => [UserRole::Admin, UserRole::Production],        // stages, press, in_production, completed
        'manage-invoicing' => [UserRole::Admin, UserRole::Sales],           // Odoo retry + manual fallback

        // Inventory
        'manage-inventory' => [UserRole::Admin, UserRole::Production],      // paper receipts, stock counts
    ];

    public static function register(): void
    {
        foreach (self::MAP as $ability => $roles) {
            Gate::define($ability, fn (User $user) => $user->hasRole(...$roles));
        }
    }

    /** @return array<string, bool> */
    public static function for(?User $user): array
    {
        $abilities = [];

        foreach (self::MAP as $ability => $roles) {
            $abilities[$ability] = $user !== null && $user->hasRole(...$roles);
        }

        return $abilities;
    }
}
