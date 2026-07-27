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
 * - Removed question switch case.
 */

include('../../../inc/includes.php');

use GlpiPlugin\Grafana\Config;
use GlpiPlugin\Grafana\Profileright;

header('Content-Type: text/html; charset=UTF-8');
Html::header_nocache();
Session::checkLoginUser();

if (!isset($_REQUEST['uid']) || !isset($_REQUEST['type'])) {
    return;
}

switch ($_REQUEST['type']) {
    case 'dashboard':
        // Config READ grants access to the full dashboard specs page, so allow JSON too.
        // Otherwise fall back to per-dashboard profile rights (used from the Central tab).
        $canView = Session::haveRight('config', READ)
            || Profileright::canProfileViewDashboard($_SESSION['glpiactiveprofile']['id'], $_REQUEST['uid']);
        if (!$canView) {
            header('HTTP/1.1 403 Forbidden', true, 403);
            return;
        }
        Config::displayDashboardJson($_REQUEST['uid']);
        break;
}
