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
Session::checkRight("config", READ);

require_once __DIR__ . '/../src/Config.php';

if (isset($_REQUEST["empty_button"])) {
    Session::addMessageAfterRedirect("Success", false, INFO);
    Html::back();
} else {
    // This is basically the same block of code in /front/config.form.php with an API call to update the dark/light mode

    $config = new Config();
    $_POST['id'] = Config::getConfigIDForContext('core');
    if (!empty($_POST["update_auth"])) {
        $config->update($_POST);
        Html::back();
    }
    if (!empty($_POST["update"])) {
        $context = array_key_exists('config_context', $_POST) ? $_POST['config_context'] : 'core';

        $glpikey = new GLPIKey();
        foreach (array_keys($_POST) as $field) {
            if ($glpikey->isConfigSecured($context, $field)) {
                // Field must not be altered, it will be encrypted and never displayed, so sanitize is not necessary.
                $_POST[$field] = $_UPOST[$field];
            }
        }

        $config->update($_POST);

        $mode = $_POST['lightmode'] == "on" ? "light" : "dark";

        $apiclient = new APIClient();
        $apiclient->httpQuery(
            '/grafana/api/user/preferences',
            [
                'json' => [
                    'theme' => $mode
                ]
            ],
            'PUT'
        );


        Html::displayMessageAfterRedirect(__('Configuration saved successfully'), true);
        Html::redirect(Toolbox::getItemTypeFormURL('Config'));
    }

    $url = Toolbox::getItemTypeFormURL('Config') . "?forcetab=" . urlencode(GrafanaConfig::class . '$1');
    Html::displayMessageAfterRedirect(__('Theres been some kind of error'), false, ERROR);
    Html::redirect($url);
}
