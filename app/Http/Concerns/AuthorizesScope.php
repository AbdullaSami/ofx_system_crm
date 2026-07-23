<?php

namespace App\Http\Concerns;

use Illuminate\Database\Eloquent\Builder;
use App\Models\User;

/**
 * AuthorizesScope
 *
 * A reusable trait for controllers that need to apply data-scope
 * authorization based on Spatie permissions.
 *
 * Usage in a controller:
 *   use AuthorizesScope;
 *
 *   $query = $this->scopedQuery(Client::query(), 'clients', 'assigned_to');
 *
 * It reads the following permissions:
 *   {module}.view       → return all records (no scope applied)
 *   {module}.view.own   → scope to records owned by the authenticated employee
 *
 * If neither permission is present, abort(403) is called.
 * If the user has view.own but has no linked employee record, abort(403).
 */
trait AuthorizesScope
{
    /**
     * Apply data-scope authorization to an Eloquent query.
     *
     * @param  Builder  $query         The base query to scope.
     * @param  string   $module        The permission module (e.g. 'clients').
     * @param  string   $ownerColumn   The column that holds the employee ID (e.g. 'assigned_to').
     * @return Builder
     */
    protected function scopedQuery(Builder $query, string $module, string $ownerColumn): Builder
    {
        /** @var User $user */
        $user = auth()->user();

        // Global view: return unscoped query
        if ($user->can("{$module}.view")) {
            return $query;
        }

        // Own-only view: scope by employee
        if ($user->can("{$module}.view.own")) {
            $employee = $user->employee;
            abort_if(
                ! $employee,
                403,
                'Your account has no linked employee record. Contact an administrator.'
            );
            return $query->where($ownerColumn, $employee->id);
        }

        abort(403, "You do not have permission to view {$module}.");
    }

    /**
     * Authorize that the current user can mutate (update/delete) a specific record.
     *
     * @param  mixed   $record         The Eloquent model instance to check.
     * @param  string  $module         The permission module (e.g. 'clients').
     * @param  string  $action         The action: 'update' or 'delete'.
     * @param  string  $ownerColumn    The ownership column on the record (e.g. 'assigned_to').
     */
    protected function authorizeRecordAccess(
        $record,
        string $module,
        string $action,
        string $ownerColumn
    ): void {
        /** @var User $user */
        $user = auth()->user();

        // Global permission: can mutate anything
        if ($user->can("{$module}.{$action}")) {
            return;
        }

        // Own-scoped permission: can only mutate own records
        if ($user->can("{$module}.{$action}.own")) {
            $employee = $user->employee;
            abort_if(
                ! $employee,
                403,
                'Your account has no linked employee record. Contact an administrator.'
            );
            abort_if(
                (int) $record->{$ownerColumn} !== (int) $employee->id,
                403,
                "You do not have permission to {$action} this {$module} record."
            );
            return;
        }

        abort(403, "You do not have permission to {$action} {$module}.");
    }
}
