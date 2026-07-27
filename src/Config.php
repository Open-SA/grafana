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
 * - Added multiple fields and changed the configuration tab to fit Grafana
 */

namespace GlpiPlugin\Grafana;

use CommonDBTM;
use Config as GlpiConfig;
use Html;
use CommonGLPI;
use Session;
use GlpiPlugin\Grafana\APIClient;
use Plugin;
use Glpi\Application\View\TemplateRenderer;

class Config extends CommonDBTM
{
    public static function getTypeName($nb = 0)
    {
        return __('Grafana', 'grafana');
    }

    /**
     * Return the current config of the plugin store in the glpi config table
     *
     * @return array config with keys => values
     */
    public static function getConfig()
    {
        return GlpiConfig::getConfigurationValues('plugin:grafana');
    }

    public static function getConfigFrom(string $from)
    {
        return GlpiConfig::getConfigurationValues($from);
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        switch ($item->getType()) {
            case 'Config':
                return self::createTabEntry(self::getTypeName());
        }

        return '';
    }

    public static function displayTabContentForItem(
        CommonGLPI $item,
        $tabnum = 1,
        $withtemplate = 0
    ) {
        switch (get_class($item)) {
            case GlpiConfig::class:
                return self::showForConfig($item, $withtemplate);
        }

        return true;
    }

    public static function showForConfig(GlpiConfig $config, $withtemplate = 0)
    {
        /** @var array $CFG_GLPI */
        global $CFG_GLPI;

        if (!Session::haveRight("config", UPDATE)) {
            return false;
        }

        $current_config = self::getConfig();
        $is_valid       = self::isValid();
        $api_status     = null;
        $last_error     = null;

        if ($is_valid) {
            $apiclient  = new APIClient();
            $api_status = $apiclient->status();
            $error      = $apiclient->getLastError();
            if (count($error)) {
                $last_error = $error;
            }
        }

        TemplateRenderer::getInstance()->display('@grafana/config.html.twig', [
            'form_url'       => Plugin::getWebDir('grafana') . '/front/config.form.php',
            'current_config' => $current_config,
            'jwks_url'       => $CFG_GLPI['url_base'] . '/plugins/grafana/front/jwks.php',
            'is_valid'       => $is_valid,
            'api_status'     => $api_status,
            'last_error'     => $last_error,
            'dashboards_url' => Plugin::getWebDir('grafana') . '/front/dashboards.php',
        ]);

        return true;
    }

    /**
     * Check if current saved config is valid
     * @param  boolean $with_api also check api status
     * @return boolean
     */
    public static function isValid($with_api = false)
    {
        $current_config = self::getConfig();
        $valid_config   = (!empty($current_config['url'])
            && !empty($current_config['password']));

        $valid_api = true;
        if ($with_api) {
            $apiclient = new APIClient();
            $apiclient->connect();
            $valid_api = !in_array(false, $apiclient->status());
        }

        return ($valid_config && $valid_api);
    }

    /**
     * Hook called when updating plugin configuration.
     *
     * @param array $input
     * @return array
     * @see Config::prepareInputForUpdate()
     */
    public static function configUpdate($input)
    {
        // if (isset($input['token'])) {
        //     if (empty($input['token'])) {
        //         unset($input['token']);
        //     } else {
        //         // Remove existing session token to force reconnection
        //         //unset($_SESSION['grafana']['token']);
        //     }
        // }
        if (isset($input['password'])) {
            if (empty($input['password'])) {
                unset($input['password']);
            }
        }

        return $input;
    }

    public static function getDashboards($folder_uid)
    {
        $api = new APIClient();
        $dashs = $api->getDashboards($folder_uid);
    }

    public static function displayDashboardJson($dashboard_id)
    {
        $apiclient = new APIClient();
        $dashboard = $apiclient->getDashboard($dashboard_id);
        self::displayPrettyJson($dashboard);
        Html::printCleanArray($dashboard);
    }


    public static function displayPrettyJson($array = [])
    {
        echo Html::css("lib/prism/prism.css");
        echo Html::script("lib/prism/prism.js");

        echo "<pre><code class='language-json'>";
        echo htmlescape(preg_replace(
            "/(^|\G) {4}/m",
            "   ", // replace indentation from 4 to 3 spaces
            json_encode($array, JSON_PRETTY_PRINT
                + JSON_UNESCAPED_UNICODE
                + JSON_UNESCAPED_SLASHES)
        ));
        echo "</code></pre>";
    }
}
