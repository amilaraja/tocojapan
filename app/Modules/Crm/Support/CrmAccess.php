<?php

namespace App\Modules\Crm\Support;

use App\Models\User;
use App\Modules\Crm\Models\CrmCase;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;

/**
 * CRM roles and rights (SRS §2.2, CRM-GEN-002). Granted through the existing
 * Users screen: roles crm_admin, crm_agent, crm_operations; the Team lead
 * option is the crm.team_lead permission on an agent. The site's super_admin
 * and admin roles act as CRM Admin (the owner logs in as admin today).
 */
class CrmAccess
{
    public const ROLE_ADMIN = 'crm_admin';

    public const ROLE_AGENT = 'crm_agent';

    public const ROLE_OPERATIONS = 'crm_operations';

    public const ROLES = [self::ROLE_ADMIN, self::ROLE_AGENT, self::ROLE_OPERATIONS];

    public const ADMIN = 'crm.admin';

    public const AGENT = 'crm.agent';

    public const OPERATIONS = 'crm.operations';

    public const TEAM_LEAD = 'crm.team_lead';

    public const PERMISSIONS = [self::ADMIN, self::AGENT, self::OPERATIONS, self::TEAM_LEAD];

    public static function canUse(?Authenticatable $user = null): bool
    {
        $user ??= auth()->user();

        return self::isAdmin($user) || self::has($user, self::AGENT) || self::has($user, self::OPERATIONS);
    }

    public static function isAdmin(?Authenticatable $user = null): bool
    {
        return self::has($user ?? auth()->user(), self::ADMIN);
    }

    public static function isOperations(?Authenticatable $user = null): bool
    {
        return self::has($user ?? auth()->user(), self::OPERATIONS);
    }

    public static function isTeamLead(?Authenticatable $user = null): bool
    {
        return self::has($user ?? auth()->user(), self::TEAM_LEAD);
    }

    /** Cases this user may see (CRM-GEN-002). @param Builder<CrmCase> $query @return Builder<CrmCase> */
    public static function scopeCases(Builder $query, ?Authenticatable $user = null): Builder
    {
        $user ??= auth()->user();
        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }
        if (self::isAdmin($user) || self::isOperations($user)) {
            return $query;
        }
        if (self::isTeamLead($user)) {
            $queues = \App\Modules\Crm\Models\CrmQueueMember::where('user_id', $user->getAuthIdentifier())->pluck('queue_id');

            return $query->where(fn (Builder $q) => $q->where('owner_id', $user->getAuthIdentifier())->orWhereIn('queue_id', $queues));
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->where('owner_id', $user->getAuthIdentifier());
            // Agents allowed to pick from the unassigned queue see it too (OPEN-01).
            if (self::canPick($user)) {
                $q->orWhere(fn (Builder $u) => $u->whereNull('owner_id')->whereIn('state', \App\Modules\Crm\Domain\Crm::ACTIVE_STATES));
            }
        });
    }

    public static function canView(CrmCase $case, ?Authenticatable $user = null): bool
    {
        return self::scopeCases(CrmCase::query()->whereKey($case->getKey()), $user)->exists();
    }

    /** Owners, admins and team leads change a Case; Operations only work steps of stages 03 and 04. */
    public static function canEdit(CrmCase $case, ?Authenticatable $user = null): bool
    {
        $user ??= auth()->user();
        if ($user === null) {
            return false;
        }

        return self::isAdmin($user)
            || (int) $case->owner_id === (int) $user->getAuthIdentifier()
            || (self::isTeamLead($user) && self::canView($case, $user));
    }

    public static function canPick(?Authenticatable $user = null): bool
    {
        $user ??= auth()->user();
        if ($user === null || ! self::has($user, self::AGENT)) {
            return false;
        }

        return (bool) (\App\Modules\Crm\Models\CrmAgentStatus::where('user_id', $user->getAuthIdentifier())->value('can_pick_unassigned') ?? true);
    }

    /** Staff who can own Cases. @return \Illuminate\Database\Eloquent\Collection<int, User> */
    public static function staff(): \Illuminate\Database\Eloquent\Collection
    {
        try {
            return User::query()->permission([self::ADMIN, self::AGENT, self::OPERATIONS])->orderBy('name')->get();
        } catch (\Throwable) {
            return new \Illuminate\Database\Eloquent\Collection;
        }
    }

    /** Users who receive admin alerts. @return \Illuminate\Database\Eloquent\Collection<int, User> */
    public static function admins(): \Illuminate\Database\Eloquent\Collection
    {
        try {
            return User::query()->permission(self::ADMIN)->get();
        } catch (\Throwable) {
            return new \Illuminate\Database\Eloquent\Collection;
        }
    }

    private static function has(?Authenticatable $user, string $permission): bool
    {
        if ($user === null || ! method_exists($user, 'checkPermissionTo')) {
            return false;
        }

        try {
            return $user->checkPermissionTo($permission);
        } catch (\Throwable) {
            return false;
        }
    }
}
