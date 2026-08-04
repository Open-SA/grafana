<?php

/**
 * -------------------------------------------------------------------------
 * Grafana plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of Grafana.
 *
 * Grafana is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Grafana is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Grafana. If not, see <http://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2025 by Grafana plugin team.
 * @license   GPLv2 https://www.gnu.org/licenses/gpl-2.0.html
 * @link      https://github.com/Open-Sa/grafana
 * -------------------------------------------------------------------------
 */

namespace GlpiPlugin\Grafana;

use CommonDBTM;

class DashboardRight extends CommonDBTM
{
    public static $rightname = 'config';

    public static function getTypeName($nb = 0)
    {
        return __('Grafana dashboard rights', 'grafana');
    }

    /**
     * Valid actor types and their translated labels.
     *
     * @return array<string, string>
     */
    public static function getActorTypes(): array
    {
        return [
            'Profile' => __('Profile', 'grafana'),
            'User'    => __('User', 'grafana'),
            'Group'   => __('Group', 'grafana'),
            'Entity'  => __('Entity', 'grafana'),
        ];
    }

    /**
     * Whether the current user can see at least one dashboard.
     * Checks all four actor types against the current session.
     */
    public static function canUserViewDashboards(int $userId): bool
    {
        /** @var \DBmysql $DB */
        global $DB;

        if (!$DB->tableExists(self::getTable())) {
            return false;
        }

        $orConditions = self::buildOrConditions($userId);
        if (empty($orConditions)) {
            return false;
        }

        $iterator = $DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => ['OR' => $orConditions],
            'LIMIT' => 1,
        ]);

        return count($iterator) > 0;
    }

    /**
     * Whether the current user can see a specific dashboard.
     * Checks all four actor types against the current session.
     */
    public static function canUserViewDashboard(int $userId, string $dashboardUuid): bool
    {
        /** @var \DBmysql $DB */
        global $DB;

        if (!$DB->tableExists(self::getTable())) {
            return false;
        }

        $orConditions = self::buildOrConditions($userId);
        if (empty($orConditions)) {
            return false;
        }

        $iterator = $DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => [
                'dashboard_uuid' => $dashboardUuid,
                'OR'             => $orConditions,
            ],
            'LIMIT' => 1,
        ]);

        return count($iterator) > 0;
    }

    /**
     * All grants for a given dashboard, with resolved actor names.
     *
     * @return array<int, array{id: int, actor_type: string, actor_id: int, actor_name: string}>
     */
    public static function getGrantsForDashboard(string $dashboardUuid): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        $iterator = $DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => ['dashboard_uuid' => $dashboardUuid],
            'ORDER' => 'actor_type ASC',
        ]);

        $byType = ['Profile' => [], 'User' => [], 'Group' => [], 'Entity' => []];
        $rows   = [];

        foreach ($iterator as $row) {
            $rows[]                                     = $row;
            $byType[$row['actor_type']][$row['actor_id']] = true;
        }

        $tableMap = [
            'Profile' => 'glpi_profiles',
            'User'    => 'glpi_users',
            'Group'   => 'glpi_groups',
            'Entity'  => 'glpi_entities',
        ];

        $names = [];
        foreach ($tableMap as $type => $table) {
            $ids = array_keys($byType[$type]);
            if (empty($ids)) {
                continue;
            }
            $nameIter = $DB->request([
                'SELECT' => ['id', 'name'],
                'FROM'   => $table,
                'WHERE'  => ['id' => $ids],
            ]);
            foreach ($nameIter as $nameRow) {
                $names[$type][$nameRow['id']] = $nameRow['name'];
            }
        }

        $grants = [];
        foreach ($rows as $row) {
            $grants[] = [
                'id'         => (int) $row['id'],
                'actor_type' => $row['actor_type'],
                'actor_id'   => (int) $row['actor_id'],
                'actor_name' => $names[$row['actor_type']][$row['actor_id']] ?? '?',
            ];
        }

        return $grants;
    }

    /**
     * Add a visibility grant for a dashboard.
     */
    public static function addGrant(string $dashboardUuid, string $actorType, int $actorId): bool
    {
        $right = new self();
        return (bool) $right->add([
            'dashboard_uuid' => $dashboardUuid,
            'actor_type'     => $actorType,
            'actor_id'       => $actorId,
        ]);
    }

    /**
     * Remove a visibility grant by its row ID.
     */
    public static function removeGrant(int $id): bool
    {
        $right = new self();
        return $right->delete(['id' => $id]);
    }

    // ── Default tab actors ────────────────────────────────────────────────

    public static function getDefaultTabTable(): string
    {
        return 'glpi_plugin_grafana_defaulttabs';
    }

    /**
     * Whether the given user matches any default-tab actor grant.
     */
    public static function isDefaultTabForUser(int $userId): bool
    {
        /** @var \DBmysql $DB */
        global $DB;

        if (!$DB->tableExists(self::getDefaultTabTable())) {
            return false;
        }

        $orConditions = self::buildOrConditions($userId);
        if (empty($orConditions)) {
            return false;
        }

        $iterator = $DB->request([
            'FROM'  => self::getDefaultTabTable(),
            'WHERE' => ['OR' => $orConditions],
            'LIMIT' => 1,
        ]);

        return count($iterator) > 0;
    }

    /**
     * All default-tab actor grants with resolved names.
     *
     * @return array<int, array{id: int, actor_type: string, actor_id: int, actor_name: string}>
     */
    public static function getDefaultTabActors(): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        if (!$DB->tableExists(self::getDefaultTabTable())) {
            return [];
        }

        $iterator = $DB->request([
            'FROM'  => self::getDefaultTabTable(),
            'ORDER' => 'actor_type ASC',
        ]);

        $byType = ['Profile' => [], 'User' => [], 'Group' => [], 'Entity' => []];
        $rows   = [];

        foreach ($iterator as $row) {
            $rows[]                                       = $row;
            $byType[$row['actor_type']][$row['actor_id']] = true;
        }

        $tableMap = [
            'Profile' => 'glpi_profiles',
            'User'    => 'glpi_users',
            'Group'   => 'glpi_groups',
            'Entity'  => 'glpi_entities',
        ];

        $names = [];
        foreach ($tableMap as $type => $table) {
            $ids = array_keys($byType[$type]);
            if (empty($ids)) {
                continue;
            }
            $nameIter = $DB->request([
                'SELECT' => ['id', 'name'],
                'FROM'   => $table,
                'WHERE'  => ['id' => $ids],
            ]);
            foreach ($nameIter as $nameRow) {
                $names[$type][$nameRow['id']] = $nameRow['name'];
            }
        }

        $actors = [];
        foreach ($rows as $row) {
            $actors[] = [
                'id'         => (int) $row['id'],
                'actor_type' => $row['actor_type'],
                'actor_id'   => (int) $row['actor_id'],
                'actor_name' => $names[$row['actor_type']][$row['actor_id']] ?? '?',
            ];
        }

        return $actors;
    }

    /**
     * Add an actor to the default-tab list.
     */
    public static function addDefaultTabActor(string $actorType, int $actorId): bool
    {
        /** @var \DBmysql $DB */
        global $DB;

        return $DB->insert(self::getDefaultTabTable(), [
            'actor_type' => $actorType,
            'actor_id'   => $actorId,
        ]);
    }

    /**
     * Remove an actor from the default-tab list by row ID.
     */
    public static function removeDefaultTabActor(int $id): bool
    {
        /** @var \DBmysql $DB */
        global $DB;

        return $DB->delete(self::getDefaultTabTable(), ['id' => $id]);
    }

    // ── Internal helpers ─────────────────────────────────────────────────

    /**
     * Build OR conditions array for the current user session.
     * Groups are read from session to avoid a subquery.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function buildOrConditions(int $userId): array
    {
        $groups    = (array) ($_SESSION['glpigroups'] ?? []);
        $profileId = (int) ($_SESSION['glpiactiveprofile']['id'] ?? 0);
        $entityId  = (int) ($_SESSION['glpiactive_entity'] ?? 0);

        $conditions = [
            ['actor_type' => 'User',    'actor_id' => $userId],
            ['actor_type' => 'Profile', 'actor_id' => $profileId],
            ['actor_type' => 'Entity',  'actor_id' => $entityId],
        ];

        if (!empty($groups)) {
            $conditions[] = ['actor_type' => 'Group', 'actor_id' => $groups];
        }

        return $conditions;
    }
}
