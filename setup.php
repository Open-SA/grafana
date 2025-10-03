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
 * - Changed initialization logic to register hooks that it will use
 */

use Glpi\Plugin\Hooks;
use GlpiPlugin\Grafana\Config;
use GlpiPlugin\Grafana\Dashboard;
use GlpiPlugin\Grafana\Profileright;

require_once __DIR__ . '/src/Config.php';

define('PLUGIN_GRAFANA_VERSION', '1.0.0');

// Minimal GLPI version, inclusive
define('PLUGIN_GRAFANA_MIN_GLPI', '10.0.0');
// Maximum GLPI version, exclusive
define('PLUGIN_GRAFANA_MAX_GLPI', '11.0.99');

if (!defined('PLUGINGRAFANA_DIR')) {
    define('PLUGINGRAFANA_DIR', __DIR__);
}

/**
 * Init hooks of the plugin.
 * REQUIRED
 *
 * @return void
 */
function plugin_init_grafana()
{
    /** @var array $PLUGIN_HOOKS */
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['grafana'] = true;
    // don't load hooks if plugin not enabled (or glpi not logged)
    if (!Plugin::isPluginActive('grafana') || !Session::getLoginUserID()) {
        return;
    }

    $PLUGIN_HOOKS['add_css']['grafana'] = 'css/grafana.css';
    $PLUGIN_HOOKS['add_javascript']['grafana'] = 'js/grafana.js';

    // config page
    Plugin::registerClass(Config::class, ['addtabon' => 'Config']);
    $PLUGIN_HOOKS['config_page']['grafana'] = 'front/config.form.php';

    // add dashboards
    Plugin::registerClass(Dashboard::class, ['addtabon' => 'Central']);

    // profile rights management
    Plugin::registerClass(Profileright::class, ['addtabon' => 'Profile']);

    // Encryption
    $PLUGIN_HOOKS['secured_configs']['grafana'] = ['password'];
}


/**
 * Get the name and the version of the plugin
 * REQUIRED
 *
 * @return array
 */
function plugin_version_grafana()
{
    return [
        'name'         => 'Grafana',
        'version'      => PLUGIN_GRAFANA_VERSION,
        'author'       => '<a href="http://www.opensa.com.ar">Open Computación S.A.\'</a>',
        'license'      => 'GPLv2+',
        'homepage'     => 'https://github.com/Open-Sa/grafana',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_GRAFANA_MIN_GLPI,
                'max' => PLUGIN_GRAFANA_MAX_GLPI,
            ],
            'php' => [
                'min' => '8.2.0',
            ]
        ],
    ];
}

function plugin_grafana_recursive_remove_empty($haystack)
{
    foreach ($haystack as $key => $value) {
        if (is_array($value)) {
            if (count($value) == 0) {
                unset($haystack[$key]);
            } else {
                $haystack[$key] = plugin_grafana_recursive_remove_empty($haystack[$key]);
            }
        } elseif ($haystack[$key] === "") {
            unset($haystack[$key]);
        }
    }

    return $haystack;
}
