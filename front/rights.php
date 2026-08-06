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

use GlpiPlugin\Grafana\DashboardRight;
use GlpiPlugin\Grafana\APIClient;
use Glpi\Application\View\TemplateRenderer;
use Plugin;

include('../../../inc/includes.php');

Session::checkRight('config', UPDATE);

if (!empty($_POST['add_grant'])) {
    $uuid      = $_POST['dashboard_uuid'] ?? '';
    $actorType = $_POST['actor_type'] ?? '';
    $actorId   = (int) ($_POST['actor_id'] ?? 0);

    if (
        $uuid !== ''
        && in_array($actorType, ['Profile', 'User', 'Group', 'Entity'], true)
        && $actorId > 0
    ) {
        DashboardRight::addGrant($uuid, $actorType, $actorId);
    }
    Html::back();
}

if (!empty($_POST['delete_grant'])) {
    $grantId = (int) ($_POST['grant_id'] ?? 0);
    if ($grantId > 0) {
        DashboardRight::removeGrant($grantId);
    }
    Html::back();
}

if (!empty($_POST['add_default_tab'])) {
    $actorType = $_POST['actor_type'] ?? '';
    $actorId   = (int) ($_POST['actor_id'] ?? 0);

    if (in_array($actorType, ['Profile', 'User', 'Group', 'Entity'], true) && $actorId > 0) {
        DashboardRight::addDefaultTabActor($actorType, $actorId);
    }
    Html::back();
}

if (!empty($_POST['delete_default_tab'])) {
    $actorId = (int) ($_POST['default_tab_id'] ?? 0);
    if ($actorId > 0) {
        DashboardRight::removeDefaultTabActor($actorId);
    }
    Html::back();
}

Html::header(
    __('Grafana dashboard permissions', 'grafana'),
    $_SERVER['PHP_SELF'],
    'config',
    'config',
    'grafana_rights',
);

$apiclient     = new APIClient();
$dashboards    = $apiclient->getDashboards();
$apiError      = null;
$dashboardData = [];

if ($dashboards === false) {
    $apiError = $apiclient->getLastError();
} elseif (is_array($dashboards)) {
    foreach ($dashboards as $dashboard) {
        $dashboardData[] = [
            'uuid'         => $dashboard['uid'],
            'title'        => $dashboard['title'],
            'folder_title' => $dashboard['folderTitle'] ?? '',
            'grants'       => DashboardRight::getGrantsForDashboard($dashboard['uid']),
        ];
    }
}

$configUrl = Toolbox::getItemTypeFormURL('Config') . '?forcetab=' . urlencode('GlpiPlugin\Grafana\Config$1');

TemplateRenderer::getInstance()->display('@grafana/rights.html.twig', [
    'dashboards'          => $dashboardData,
    'api_error'           => $apiError,
    'actor_types'         => DashboardRight::getActorTypes(),
    'default_tab_actors'  => DashboardRight::getDefaultTabActors(),
    'form_url'            => Plugin::getWebDir('grafana') . '/front/rights.php',
    'ajax_url'            => Plugin::getWebDir('grafana') . '/ajax/rights.php',
    'config_url'          => $configUrl,
]);

Html::footer();
