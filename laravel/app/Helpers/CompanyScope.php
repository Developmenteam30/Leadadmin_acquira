<?php

namespace App\Helpers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Helper for applying company-scoped filtering to queries.
 * When user has idCompany set (client-level), filter to that company.
 * When null (staff/admin), no filter = all companies.
 */
class CompanyScope
{
    /**
     * Apply company scope to a query when the user has idCompany set.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     * @param  \Illuminate\Contracts\Auth\Authenticatable|null  $user
     * @param  string  $column  The column to filter on (e.g. 'idCompany', 'feedinc.idCompany')
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder
     */
    public static function apply($query, ?Authenticatable $user, string $column = 'idCompany')
    {
        $idCompany = self::getUserCompanyId($user);

        if ($idCompany !== null) {
            $query->where($column, $idCompany);
        }

        return $query;
    }

    /**
     * Check if the current user should have company-scoped access (has idCompany set).
     */
    public static function userHasCompanyScope(?Authenticatable $user): bool
    {
        return self::getUserCompanyId($user) !== null;
    }

    /**
     * Get the user's company ID for scoping, or null if they see all companies.
     * Treats null, empty string, and 0 as "no company" (all companies access).
     */
    public static function getUserCompanyId(?Authenticatable $user): ?int
    {
        $idCompany = $user?->idCompany ?? null;

        if ($idCompany === null || $idCompany === '' || $idCompany === 0) {
            return null;
        }

        return (int) $idCompany;
    }
}
