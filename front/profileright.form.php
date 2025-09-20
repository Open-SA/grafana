<?php

/**
 * -------------------------------------------------------------------------
 * Derived from Metabase plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is based on Metabase.
 *
 * Metabase is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Metabase is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Metabase. If not, see <http://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2018-2023 by Metabase plugin team.
 * @license   GPLv2 https://www.gnu.org/licenses/gpl-2.0.html
 * @link      https://github.com/pluginsGLPI/metabase
 * -------------------------------------------------------------------------
 * Modified by Grafana plugin team
 * @copyright Copyright (C) 2025 by Grafana plugin team.
 * @link      https://github.com/Open-Sa/grafana
 * -------------------------------------------------------------------------
 * Changes:
 * - Small refactorings
 */

use GlpiPlugin\Grafana\Profileright;
use GlpiPlugin\Grafana\APIClient;

include('../../../inc/includes.php');

if (isset($_REQUEST['update'])) {
    Session::checkRight('profile', UPDATE);

    if (
        !array_key_exists('profiles_id', $_REQUEST)
        || empty($_REQUEST['profiles_id'])
        || !array_key_exists('dashboard', $_REQUEST)
        || !is_array($_REQUEST['dashboard'])
    ) {
        Session::addMessageAfterRedirect(
            __('Invalid request.', 'grafana'),
            false,
            ERROR,
        );
        Html::back();
    }

    $viewableDashboardsUuids = [];
    foreach ($_REQUEST['dashboard'] as $dashboardUuid => $rights) {
        Profileright::setDashboardRightsForProfile(
            $_REQUEST['profiles_id'],
            $dashboardUuid,
            $rights,
        );

        if ($rights & READ) {
            $viewableDashboardsUuids[] = $dashboardUuid;
        }
    }
} elseif (isset($_REQUEST['set_rights_to_all'])) {
    Session::checkRight('profile', UPDATE);

    if (!array_key_exists('profiles_id', $_REQUEST) || empty($_REQUEST['profiles_id'])) {
        Session::addMessageAfterRedirect(
            __('Invalid request.', 'grafana'),
            false,
            ERROR,
        );
        Html::back();
    }

    $apiclient = new APIClient();

    $viewableDashboardsUuids = [];
    foreach ($apiclient->getDashboards() as $dashboard) {
        Profileright::setDashboardRightsForProfile(
            $_REQUEST['profiles_id'],
            $dashboard['uid'],
            $_REQUEST['set_rights_to_all'],
        );

        $viewableDashboardsUuids[] = $dashboard['uid'];
    }
}

Html::back();
