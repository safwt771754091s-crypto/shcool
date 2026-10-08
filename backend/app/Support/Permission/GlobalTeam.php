<?php

namespace App\Support\Permission;

/**
 * spatie/laravel-permission's "team" column is part of a composite primary
 * key, so it can never be NULL. Platform-wide roles (ministry, governorate,
 * directorate, super admin) therefore live in a reserved team id instead of
 * the "no team" state.
 */
final class GlobalTeam
{
    public const ID = 0;
}
