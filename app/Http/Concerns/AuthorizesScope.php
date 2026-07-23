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
            $employeeId = $user->getEmployeeId();
            if (! $employeeId) {
                return $query->whereRaw('1 = 0');
            }
            return $query->where($ownerColumn, $employeeId);
        }

        abort(403, "You do not have permission to view {$module}.");
    }

    /**
     * Authorize that the current user can access/mutate (view/update/delete) a specific record.
     *
     * @param  mixed   $record         The Eloquent model instance to check.
     * @param  string  $module         The permission module (e.g. 'clients').
     * @param  string  $action         The action: 'view', 'update', or 'delete'.
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

        // Global permission: can access anything
        if ($user->can("{$module}.{$action}")) {
            return;
        }

        // Own-scoped permission: can only access own records
        if ($user->can("{$module}.{$action}.own")) {
            $employeeId = $user->getEmployeeId();
            abort_if(
                ! $employeeId || (int) $record->{$ownerColumn} !== (int) $employeeId,
                403,
                "You do not have permission to {$action} this {$module} record."
            );
            return;
        }

        abort(403, "You do not have permission to {$action} {$module}.");
    }
}
