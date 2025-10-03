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
 * - Changed the dashboard display auth logic to fit Grafana
 */

namespace GlpiPlugin\Grafana;

require_once GLPI_ROOT . '/plugins/grafana/vendor/autoload.php';
use CommonDBTM;
use CommonGLPI;
use GlpiPlugin\Grafana\Profileright;
use GlpiPlugin\Grafana\APIClient;
use Central;
use Dropdown;
use DateTimeImmutable;
use Html;

use Lcobucci\JWT\Configuration;

use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;

class Dashboard extends CommonDBTM
{
    /**
     * {@inheritDoc}
     * @see CommonGLPI::getTypeName()
     */
    public static function getTypeName($nb = 0)
    {
        return __('Grafana dashboard', 'grafana');
    }

    /**
     * {@inheritDoc}
     * @see CommonGLPI::getTabNameForItem()
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        switch ($item->getType()) {
            case 'Central':
                if (Profileright::canProfileViewDashboards($_SESSION['glpiactiveprofile']['id'])) {
                    return self::createTabEntry(self::getTypeName());
                }

                break;
        }

        return '';
    }

    /**
     * {@inheritDoc}
     * @see CommonGLPI::displayTabContentForItem()
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        switch (get_class($item)) {
            case Central::class:
                if (Profileright::canProfileViewDashboards($_SESSION['glpiactiveprofile']['id'])) {
                    self::showForCentral($item, $withtemplate);
                }

                break;
        }

        return true;
    }

    /**
     * Display central tab.
     *
     * @param Central $item
     * @param number $withtemplate
     *
     * @return void
     */
    public static function showForCentral(Central $item, $withtemplate = 0, $is_helpdesk = false)
    {

        global $CFG_GLPI;

        $apiclient = new APIClient();

        $currentUuid = isset($_GET['uuid']) ? $_GET['uuid'] : null;

        $dashboards = $apiclient->getDashboards();
        if (is_array($dashboards)) {
            $dashboards = array_filter(
                $dashboards,
                function ($dashboard) {
                    $canView            = Profileright::canProfileViewDashboard(
                        $_SESSION['glpiactiveprofile']['id'],
                        $dashboard['uid'],
                    );

                    return $canView;
                },
            );
        }

        if (empty($dashboards)) {
            return;
        }

        if (null === $currentUuid) {
            $firstDashboard = current($dashboards);
            $currentUuid    = $firstDashboard['id'];
        }

        Dropdown::showFromArray(
            'current_dashboard',
            array_combine(array_column($dashboards, 'id'), array_column($dashboards, 'title')),
            [
                'on_change' => ($is_helpdesk) ? 'location.href = location.origin+location.pathname+"?uuid="+$(this).val()' : 'reloadTab("uuid=" + $(this).val());',
                'value'     => $currentUuid,
            ],
        );

        $config = Config::getConfig();
        $private_key = file_get_contents(GLPI_PLUGIN_DOC_DIR . '/grafana/keys/private_key.pem');
        $public_key = file_get_contents(GLPI_PLUGIN_DOC_DIR . '/grafana/keys/public_key.pem');


        $signer_config = Configuration::forAsymmetricSigner(
            new Sha256(),
            InMemory::plainText($private_key),
            InMemory::plainText($public_key),
        );


        // Create the token
        $now = new DateTimeImmutable();
        $token = $signer_config->builder()
            ->issuedBy("glpi_plugin") // Configures the issuer (iss claim)
            ->expiresAt($now->modify('+1 hour')) // Expiration time
            ->relatedTo($config['username']) // Sub claim with the username of the user in the config
            ->withHeader('kid', 'grafana-key-1') // Kinda selects the public key to use Grafana side
            ->getToken($signer_config->signer(), $signer_config->signingKey()); // Retrieves the generated token

        $currentDashboard = current(array_filter($dashboards, function ($dashboard) use ($currentUuid) {
            return $dashboard['id'] == $currentUuid;
        }));
        $dashboardUrl = $currentDashboard['url'];
        $url = rtrim($config['url'], '/');
        if (strpos($dashboardUrl, '/d/') !== 0) {
            $dashboardUrl = substr($dashboardUrl, strpos($dashboardUrl, '/d/'));
        }
        // The kiosk parameter is used to hide the Grafana header and footer so it only shows the dashboard
        $fullUrl = $url . $dashboardUrl . '?kiosk&auth_token=' . $token->toString();

        echo "<iframe src='$fullUrl' id='grafana_iframe' allowtransparency></iframe>";

        echo Html::scriptBlock("
            setInterval(function() {
                console.log('Refreshing Grafana iframe token');

                $.ajax({
                    url: '" . $CFG_GLPI['url_base'] . "/plugins/grafana/ajax/refresh_token.php',
                    dataType: 'json',
                    success: function(data) {
                        if (data.token) {
                            var ifram = document.getElementById('grafana_iframe');
                            var url_string = ifram.src;
                            var index_auth = url_string.indexOf('auth_token=');

                            if (index_auth !== -1) {
                                var new_url = url_string.substring(0, index_auth) + 'auth_token=' + data.token;
                            }
                            ifram.src = new_url;
                        } else {
                            console.error('Error refreshing token');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error refreshing token:', error);
                    }
                });
            }, 55 * 60 * 1000);");
    }
}
