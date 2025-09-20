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
use Dropdown;
use GlpiPlugin\Grafana\APIClient;
use Plugin;

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

        $public_key = file_get_contents(GLPI_PLUGIN_DOC_DIR . '/grafana/keys/public_key.pem');

        $canedit = Session::haveRight("config", UPDATE);

        if (!$canedit) {
            return false;
        }

        $current_config = self::getConfig();
        echo "<div class='grafana_config'>";
        echo '<h1>' . __('Grafana plugin configuration', 'grafana') . '</h1>';

        if ($canedit) {
            echo "<form name='form' action='" . Plugin::getWebDir('grafana') . "/front/config.form.php' method='post'>";
        }

        echo "<div id='base_config' class='grafana_config_block'>";
        echo self::showField([
            'label' => __('Grafana URL', 'grafana'),
            'attrs' => [
                'name'        => 'url',
                'value'       => $current_config['url'],
                'placeholder' => 'https://grafana.domain',
                'required' => true,
            ],
        ]);
        echo self::showField([
            'inputtype' => 'text',
            'label'    => __('Grafana Username', 'grafana'),
            'attrs'    => [
                'name'     => 'username',
                'value'    => $current_config['username'],
                'required' => true,
            ],
        ]);

        echo self::showField([
            'inputtype' => 'password',
            'label'     => __('Grafana Password', 'grafana'),
            'attrs'     => [
                'name'     => 'password',
                'value'    => '',
                'required' => false,
            ],
        ]);

        echo self::showField([
            'inputtype' => 'paragraph',
            'label'     => __('JWKS url', 'grafana'),
            'attrs'    => [
                'value' => $CFG_GLPI['url_base'] . "/plugins/grafana/public/jwks.php",
                'id' => 'grafana_jwks_url'
            ],
        ]);

        echo self::showField([
            'inputtype' => 'checkbox',
            'label'     => __('Grafana light mode theme', 'grafana'),
            'attrs'     => [
                'name'     => 'lightmode',
                'value'    => $current_config['lightmode'] ?? 0,
                'required' => false,
                'id' => 'grafana_lightmode',
            ],
        ]);

        echo "<input type='hidden' name='copy' id='translated_copy' value='" . __('Copy', 'grafana') . "'>";
        echo "<input type='hidden' name='copied' id='translated_copied' value='" . __('Copied!', 'grafana') . "'>";

        if ($canedit) {
            echo Html::hidden('config_class', ['value' => __CLASS__]);
            echo Html::hidden('config_context', ['value' => 'plugin:grafana']);
            echo Html::submit(_sx('button', 'Save'), [
                'class' => 'btn btn-primary',
                'icon'  => 'ti ti-device-floppy',
                'name'  => 'update',
            ]);
        }
        echo '</div>';
        Html::closeForm();


        if (self::isValid()) {
            echo "<h1>" . __("API status", 'grafana') . "</h1>";
            $apiclient    = new APIClient();
            $all_status   = $apiclient->status();

            echo "<ul>";
            foreach ($all_status as $status_label => $status) {
                $color_png = "greenbutton.png";
                if (!$status) {
                    $color_png = "redbutton.png";
                }
                echo "<li>";
                echo Html::image($CFG_GLPI['root_doc'] . "/pics/$color_png");
                echo "&nbsp;" . $status_label;
                echo "</li>";
            }
            echo "</ul>";

            $error = $apiclient->getLastError();
            if (count($error)) {
                echo "<h1>" . __("Last Error", 'grafana') . "</h1>";
                if (isset($error['exception'])) {
                    echo $error['exception'];
                } else {
                    Html::printCleanArray($error);
                }
            }

            echo "<div id='actions'>";
            if ($canedit) {
                echo "<form name='form' action='" . self::getFormUrl() . "' method='post'>";
            }

            echo "<h1>" . __("Action(s)", 'grafana') . "</h1>";
            echo "<div class='btn-group-vertical'>";

            Html::closeForm();
            echo '<a href="' . Plugin::getWebDir('grafana') . '/front/dashboards.php" class="btn btn-outline-secondary">'
                . "<i class='ti ti-chart-infographic'></i>"
                . "<span>" . __('Show reports and dashboards specifications', 'grafana') . "</span>"
                . '</a>';

            echo '</div>';
        }
    }

    public static function showField($options = [])
    {
        $rand            = mt_rand();
        $default_options = [
            'inputtype' => 'text',
            'itemtype'  => '',
            'label'     => '',
            'help'      => '',
            'attrs'     => [
                'name'        => '',
                'value'       => '',
                'placeholder' => '',
                'style'       => 'width:50%;',
                'id'          => "grafanaconfig_field_$rand",
                'class'       => 'grafana_input form-control',
                'required'    => 'required',
                'on_change'   => '',
            ],
        ];
        $options = array_replace_recursive($default_options, $options);

        if ($options['attrs']['required'] === false) {
            unset($options['attrs']['required']);
        }

        $out = '';
        $out .= "<div class='grafana_field'>";

        // call the field according to its type
        switch ($options['inputtype']) {
            default:
            case 'text':
                $out .= Html::input('fakefield', ['style' => 'display:none;']);
                $out .= Html::input($options['attrs']['name'], $options['attrs']);
                break;

            case 'password':
                $out .= "<input type='password' name='fakefield' style='display:none;'>";
                $out .= "<input type='password'";
                foreach ($options['attrs'] as $key => $value) {
                    $out .= "$key='$value' ";
                }
                $out .= '>';
                break;

            case 'yesno':
                $options['attrs']['display'] = false;
                $out .= Dropdown::showYesNo($options['attrs']['name'], $options['attrs']['value'], -1, $options['attrs']);
                break;

            case 'dropdown':
                $options['attrs']['display'] = false;
                $out .= Dropdown::show($options['itemtype'], $options['attrs']);
                break;

            case 'number':
                $options['attrs']['display'] = false;
                $out .= Dropdown::showNumber($options['attrs']['name'], $options['attrs']);
                break;

            case 'paragraph':
                $out .= '<div class="grafana_paragraph">';
                $id = $options['attrs']['id'];
                $out .= "<label class='grafana_label_paragraph' for='{$options['attrs']['id']}'>
                  {$options['label']}</label>";
                $options['attrs']['display'] = false;
                $out .= "<p id='" . $id . "'>" . nl2br($options['attrs']['value']) . "</p>";
                $out .= "<button id='copy_clipboard' type='button' class='btn btn-secondary'>"
                    . "<i id='button_icon' class='ti ti-clipboard'></i> "
                    . "<span id='button_text'>" . __('Copy', 'grafana') . "</span>"
                    . "</button>";
                $out .= '</div>';
                break;

            case 'checkbox':
                $checked = $options['attrs']['value'] ? 'checked' : '';
                $out .= "<input type='hidden' name='{$options['attrs']['name']}' value='0'>";
                $out .= "<input type='checkbox' class='form-check-input' name='{$options['attrs']['name']}' id='{$options['attrs']['id']}' " . $checked . ">";
                break;

            case 'iconbutton':
                $out .= "<button id='{$options['attrs']['id']}' type='button' class='btn btn-secondary'>"
                    . "<i id='{$options['attrs']['icon_id']}' class='{$options['attrs']['icon']}'></i> "
                    . "<span id='{$options['attrs']['text_id']}'> {$options['attrs']['buttontext']} </span>"
                    . "</button>";
                break;
        }
        if ($options['inputtype'] != 'paragraph') {
            $out .= "<label class='grafana_label' for='{$options['attrs']['id']}'>
                  {$options['label']}</label>";
        }

        if (strlen($options['help'])) {
            $out .= "<i class='fa grafana_help fa-info-circle' title='{$options['help']}'></i>";
        }

        $out .= '</div>';

        return $out;
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
        echo preg_replace(
            "/(^|\G) {4}/m",
            "   ", // replace indentation from 4 to 3 spaces
            json_encode($array, JSON_PRETTY_PRINT
                + JSON_UNESCAPED_UNICODE
                + JSON_UNESCAPED_SLASHES)
        );
        echo "</code></pre>";
    }
}
