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

include('../../../inc/includes.php');

use GlpiPlugin\Grafana\DashboardRight;

Session::checkLoginUser();

if (!DashboardRight::canUserViewDashboards((int) Session::getLoginUserID())) {
    header('HTTP/1.1 403 Forbidden', true, 403);
    echo json_encode([
        'error' => 'You don\'t have permission to view dashboards',
    ]);
    return;
}

header('Content-Type: application/json');

use GlpiPlugin\Grafana\Config;
use GlpiPlugin\Grafana\Token;

$config = Config::getConfig();

if (empty($config['private_key']) || empty($config['public_key'])) {
    header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
    echo json_encode(['error' => 'RSA keys not found. Please reinstall the plugin.']);
    return;
}

echo json_encode([
    'token' => Token::mint($config),
]);
