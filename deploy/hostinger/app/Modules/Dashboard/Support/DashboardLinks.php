<?php

namespace App\Modules\Dashboard\Support;

/**
 * Server-side allow-listed deep-link builders. Never accept client URLs.
 */
final class DashboardLinks
{
    public static function tasks(array $query = []): string
    {
        return self::path('/app/tasks', $query);
    }

    public static function task(int $id): string
    {
        return '/app/tasks/'.$id;
    }

    public static function contracts(array $query = []): string
    {
        return self::path('/app/contracts', $query);
    }

    public static function contract(int $id): string
    {
        return '/app/contracts/'.$id;
    }

    public static function meetings(array $query = []): string
    {
        return self::path('/app/meetings', $query);
    }

    public static function meeting(int $id): string
    {
        return '/app/meetings/'.$id;
    }

    public static function decisions(array $query = []): string
    {
        return self::path('/app/decisions', $query);
    }

    public static function decision(int $id): string
    {
        return '/app/decisions/'.$id;
    }

    public static function inventory(array $query = []): string
    {
        return self::path('/app/inventory', $query);
    }

    public static function assets(array $query = []): string
    {
        return self::path('/app/assets', $query);
    }

    public static function asset(int $id): string
    {
        return '/app/assets/'.$id;
    }

    public static function myCustodies(): string
    {
        return '/app/my-custodies';
    }

    public static function notifications(): string
    {
        return '/app/notifications';
    }

    /**
     * @param  array<string, scalar|null>  $query
     */
    private static function path(string $base, array $query = []): string
    {
        if ($query === []) {
            return $base;
        }

        return $base.'?'.http_build_query($query);
    }
}
