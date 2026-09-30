<?php

namespace App\Support;

use App\Models\Job;
use App\Models\User;
use App\Notifications\JobNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Sends a JobNotification to every user holding an ability, except the
 * person who triggered it (they already know).
 */
class Notify
{
    /** @param 'info'|'success'|'warning'|'error' $level */
    public static function ability(string $ability, Job $job, string $title, string $level = 'info', ?User $except = null): void
    {
        $roles = array_map(fn ($role) => $role->value, Abilities::MAP[$ability] ?? []);

        $users = User::query()
            ->whereIn('role', $roles)
            ->when($except ?? auth()->user(), fn ($q, $u) => $q->whereKeyNot($u->id))
            ->get();

        Notification::send($users, new JobNotification($job, $title, $level));
    }
}
