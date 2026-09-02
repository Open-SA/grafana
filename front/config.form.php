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
 * - Added API call to update dark/light mode in Grafana
 */

use GlpiPlugin\Grafana\Config as GrafanaConfig;
use Session;
use Config;
use GlpiPlugin\Grafana\APIClient;

include('../../../inc/includes.php');
Session::checkRight("config", UPDATE);

require_once __DIR__ . '/../src/Config.php';

if (isset($_REQUEST["empty_button"])) {
    Session::addMessageAfterRedirect("Success", false, INFO);
    Html::back();
} elseif (!empty($_POST["update_url_params"])) {
    $input = [];
    foreach (array_keys(GrafanaConfig::getUrlParamsPool()) as $key) {
        $input['url_param_' . $key] = ($_POST['url_param_' . $key] ?? '0') === '1' ? 1 : 0;
    }
    Config::setConfigurationValues('plugin:grafana', $input);
    Html::back();
} elseif (!empty($_POST["update"])) {
    $input = [
        'lightmode'      => (int) ($_POST['lightmode'] ?? 0),
        'token_lifetime' => max(3, (int) ($_POST['token_lifetime'] ?? 10)),
    ];
    foreach (['url', 'username'] as $field) {
        if (isset($_POST[$field])) {
            $input[$field] = $_POST[$field];
        }
    }
    if (!empty($_POST['password'])) {
        $input['password'] = $_POST['password'];
    }

    if (!empty($input['url']) && !Toolbox::isValidWebUrl($input['url'])) {
        Session::addMessageAfterRedirect(
            __('Invalid Grafana URL: must be a valid http or https URL.', 'grafana'),
            false,
            ERROR
        );
        $url = Toolbox::getItemTypeFormURL('Config') . "?forcetab=" . urlencode(GrafanaConfig::class . '$1');
        Html::redirect($url);
    }

    Config::setConfigurationValues('plugin:grafana', $input);

    $mode = ($input['lightmode'] ?? 0) ? 'light' : 'dark';
    $apiclient = new APIClient();
    $themeResult = $apiclient->httpQuery(
        'user/preferences',
        ['json' => ['theme' => $mode]],
        'PUT'
    );

    if ($themeResult === false) {
        $err = $apiclient->getLastError();
        $errMsg = $err['exception'] ?? __('Unknown error', 'grafana');
        Session::addMessageAfterRedirect(
            sprintf(__('Configuration saved, but Grafana theme sync failed: %s', 'grafana'), $errMsg),
            false,
            WARNING
        );
    }

    Html::displayMessageAfterRedirect(__('Configuration saved successfully'), true);
    Html::redirect(Toolbox::getItemTypeFormURL('Config'));
} else {
    $url = Toolbox::getItemTypeFormURL('Config') . "?forcetab=" . urlencode(GrafanaConfig::class . '$1');
    Html::displayMessageAfterRedirect(__('Theres been some kind of error'), false, ERROR);
    Html::redirect($url);
}
