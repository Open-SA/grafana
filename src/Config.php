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
                return self::createTabEntry(self::getTypeName(), 0, -1, 'ti ti-chart-infographic');
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
            'form_url'        => Plugin::getWebDir('grafana') . '/front/config.form.php',
            'current_config'  => $current_config,
            'jwks_url'        => $CFG_GLPI['url_base'] . '/plugins/grafana/front/jwks.php',
            'is_valid'        => $is_valid,
            'api_status'      => $api_status,
            'last_error'      => $last_error,
            'dashboards_url'  => Plugin::getWebDir('grafana') . '/front/dashboards.php',
            'rights_url'      => Plugin::getWebDir('grafana') . '/front/rights.php',
            'url_params_pool' => self::getUrlParamsPool(),
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

    /**
     * Full pool of GLPI session fields that can be forwarded to Grafana as URL variables.
     * Keys become the Grafana variable name (prepended with "var-").
     *
     * @return array<string, string>  key => translated label
     */
    public static function getUrlParamsPool(): array
    {
        return [
            'glpi_user_id'      => __('User ID', 'grafana'),
            'glpi_username'     => __('Username (login)', 'grafana'),
            'glpi_firstname'    => __('First name', 'grafana'),
            'glpi_lastname'     => __('Last name', 'grafana'),
            'glpi_entity_id'    => __('Active entity ID', 'grafana'),
            'glpi_entity_name'  => __('Active entity name', 'grafana'),
            'glpi_entity_ids'   => __('All active entity IDs (comma-separated)', 'grafana'),
            'glpi_profile_id'   => __('Active profile ID', 'grafana'),
            'glpi_profile_name' => __('Active profile name', 'grafana'),
            'glpi_groups'       => __('Groups (comma-separated IDs)', 'grafana'),
            'glpi_language'     => __('Language', 'grafana'),
        ];
    }

    /**
     * Read the current session and return a value for each pool key.
     *
     * @return array<string, string>
     */
    public static function getSessionUrlParamValues(): array
    {
        /** @var array $CFG_GLPI */
        return [
            'glpi_user_id'      => (string) Session::getLoginUserID(),
            'glpi_username'     => (string) ($_SESSION['glpiname'] ?? ''),
            'glpi_firstname'    => (string) ($_SESSION['glpifirstname'] ?? ''),
            'glpi_lastname'     => (string) ($_SESSION['glpirealname'] ?? ''),
            'glpi_entity_id'    => (string) ($_SESSION['glpiactive_entity'] ?? ''),
            'glpi_entity_name'  => (string) ($_SESSION['glpiactive_entity_name'] ?? ''),
            'glpi_entity_ids'   => implode(',', (array) ($_SESSION['glpiactiveentities'] ?? [])),
            'glpi_profile_id'   => (string) ($_SESSION['glpiactiveprofile']['id'] ?? ''),
            'glpi_profile_name' => (string) ($_SESSION['glpiactiveprofile']['name'] ?? ''),
            'glpi_groups'       => implode(',', (array) ($_SESSION['glpigroups'] ?? [])),
            'glpi_language'     => (string) ($_SESSION['glpilanguage'] ?? ''),
        ];
    }

    /**
     * Build the Grafana URL query string for all enabled session params.
     * Returns a string like "&var-glpi_entity_id=42&var-glpi_user_id=5",
     * or an empty string if nothing is enabled.
     *
     * @return string
     */
    public static function buildGrafanaUrlParams(): string
    {
        $config = self::getConfig();
        $values = self::getSessionUrlParamValues();
        $parts  = [];

        foreach (array_keys(self::getUrlParamsPool()) as $key) {
            if (!empty($config['url_param_' . $key]) && ($values[$key] ?? '') !== '') {
                $parts[] = 'var-' . rawurlencode($key) . '=' . rawurlencode($values[$key]);
            }
        }

        return $parts ? '&' . implode('&', $parts) : '';
    }

    public static function displayDashboardJson($dashboard_id)
    {
        $apiclient = new APIClient();
        $dashboard = $apiclient->getDashboard($dashboard_id);
        if ($dashboard === false) {
            $err = $apiclient->getLastError();
            echo '<div class="alert alert-warning">'
                . htmlescape(__('Grafana API error', 'grafana') . ': ' . ($err['exception'] ?? __('Unknown error', 'grafana')))
                . '</div>';
            return;
        }
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
