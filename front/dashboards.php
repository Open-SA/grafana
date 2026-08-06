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

use GlpiPlugin\Grafana\Config;
use GlpiPlugin\Grafana\APIClient;
use Glpi\Application\View\TemplateRenderer;

include('../../../inc/includes.php');

Html::header(
    __('Grafana collections', 'grafana'),
    $_SERVER['PHP_SELF'],
    'config',
    'config',
    'collections',
);

Session::checkRight('config', READ);

$tree       = [];
$api_errors = [];
$grafanaConfig = new Config();
$apiclient     = new APIClient();
if ($grafanaConfig::isValid()) {
    $folders = $apiclient->getFolders();
    if ($folders === false) {
        $api_errors[] = $apiclient->getLastError();
        $folders = [];
    }

    $all_dashboards = $apiclient->getDashboards();
    if ($all_dashboards === false) {
        $api_errors[] = $apiclient->getLastError();
    } elseif (count($all_dashboards)) {
        $folderRefs  = [];
        fixOutOfBoundsDashboards($all_dashboards, $folders);
        $folderIndex = indexFolders($folders, $all_dashboards);
        $folderTree  = createFolderTree($folderIndex, $folderRefs);
        $tree        = fillTree($folderRefs, $all_dashboards, $folderTree);
    }
}

TemplateRenderer::getInstance()->display('@grafana/dashboards.html.twig', [
    'tree'       => $tree,
    'api_errors' => $api_errors,
]);

Html::footer();

function fixOutOfBoundsDashboards(&$all_dashboards, $folders)
{
    $knownFolderUids = [];
    foreach ($folders as $folder) {
        $knownFolderUids[] = $folder['uid'];
    }

    // border border case: when theres dashboards shared to the user that are inside a folder the user has no access to, "out of bounds" folders
    foreach ($all_dashboards as &$dashboard) {
        if (array_search($dashboard['folderUid'], $knownFolderUids) === false) {
            $dashboard['folderUid'] = 'root';
        }
    }
}

function indexFolders($folders, &$all_dashboards)
{
    $folderHierarchy = [];


    foreach ($folders as $folder) {
        $parent = $folder['folderUid'] ?? 'root';
        $folderHierarchy[$parent][] = $folder;
    }
    // Border case: when theres no root folder because the grafana user has access to only a specific folder thats not in root
    if (!empty($folderHierarchy) && !isset($folderHierarchy['root']) && count($folderHierarchy) === 1) {
        $root = array_key_first($folderHierarchy);
        $folderHierarchy['root'] = $folderHierarchy[$root];
        unset($folderHierarchy[$root]);

        foreach ($folderHierarchy['root'] as &$folder) {
            if (($folder['folderUid'] ?? '') === $root) {
                $folder['folderUid'] = 'root';
            }
        }

        // now to update all "root" dashboards to point to the new root
        foreach ($all_dashboards as &$dashboard) {
            if (($dashboard['folderUid'] ?? '') === $root) {
                $dashboard['folderUid'] = 'root';
            }
        }
    }


    return $folderHierarchy;
}

function createFolderTree($folderIndex, &$folderRefs = [], $parentUid = 'root')
{
    $tree = [];

    foreach ($folderIndex[$parentUid] ?? [] as $folder) {
        $subfolders = createFolderTree($folderIndex, $folderRefs, $folder['uid']);
        if (count($subfolders) > 0) {
            $folder['subfolders'] = $subfolders;
        }
        $tree[] = $folder;

        $folderRefs[$folder['uid']] = &$tree[array_key_last($tree)];
    }

    return $tree;
}

function fillTree($folderRefs, $all_dashboards, $tree)
{
    foreach ($all_dashboards as $dashboard) {
        $folderUid = $dashboard['folderUid'] ?? 'root';
        if ($folderUid === 'root') {
            $tree[] = $dashboard;
        } else {
            $folderRefs[$folderUid]['dashboards'][] = $dashboard;
        }
    }

    if (empty($tree) && !empty($folderRefs)) {
        $tree = array_values($folderRefs);
    }

    return $tree;
}
