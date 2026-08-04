<?php

/**
 * -------------------------------------------------------------------------
 * Grafana plugin for GLPI
 * -------------------------------------------------------------------------
 * Dashboard permissions management page.
 * Allows admins to grant visibility of each Grafana dashboard to specific
 * profiles, users, groups, or entities.
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

$apiclient  = new APIClient();
$dashboards = $apiclient->getDashboards();

$dashboardData = [];
if (is_array($dashboards)) {
    foreach ($dashboards as $dashboard) {
        $dashboardData[] = [
            'uuid'   => $dashboard['uid'],
            'title'  => $dashboard['title'],
            'grants' => DashboardRight::getGrantsForDashboard($dashboard['uid']),
        ];
    }
}

$configUrl = Toolbox::getItemTypeFormURL('Config') . '?forcetab=' . urlencode('GlpiPlugin\Grafana\Config$1');

TemplateRenderer::getInstance()->display('@grafana/rights.html.twig', [
    'dashboards'          => $dashboardData,
    'actor_types'         => DashboardRight::getActorTypes(),
    'default_tab_actors'  => DashboardRight::getDefaultTabActors(),
    'form_url'            => Plugin::getWebDir('grafana') . '/front/rights.php',
    'ajax_url'            => Plugin::getWebDir('grafana') . '/ajax/rights.php',
    'config_url'          => $configUrl,
]);

Html::footer();
