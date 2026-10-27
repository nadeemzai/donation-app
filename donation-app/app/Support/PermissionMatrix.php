<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Reads config/permissions.php and answers "which permissions does a role get?".
 */
final class PermissionMatrix
{
    public static function name(string $area, string $ability): string
    {
        return "{$area}.{$ability}";
    }

    /**
     * Every permission name, e.g. ["users.view_any", "users.view", ...].
     *
     * @return list<string>
     */
    public static function all(): array
    {
        $names = [];

        foreach (array_keys(self::matrix()) as $area) {
            foreach (self::abilities() as $ability) {
                $names[] = self::name($area, $ability);
            }
        }

        return $names;
    }

    /**
     * @return list<string>
     */
    public static function forRole(string $role): array
    {
        $index = array_search($role, self::roles(), true);

        if ($index === false) {
            throw new InvalidArgumentException("Unknown role [{$role}].");
        }

        /** @var array<string, list<string>> $levels */
        $levels = config('permissions.levels');
        $names = [];

        foreach (self::matrix() as $area => $row) {
            foreach ($levels[$row[$index]] ?? [] as $ability) {
                $names[] = self::name($area, $ability);
            }
        }

        return $names;
    }

    /**
     * @return list<string>
     */
    public static function roles(): array
    {
        /** @var list<string> $roles */
        $roles = config('permissions.roles');

        return $roles;
    }

    /**
     * @return list<string>
     */
    public static function abilities(): array
    {
        /** @var list<string> $abilities */
        $abilities = config('permissions.abilities');

        return $abilities;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function matrix(): array
    {
        /** @var array<string, list<string>> $matrix */
        $matrix = config('permissions.matrix');

        return $matrix;
    }
}
