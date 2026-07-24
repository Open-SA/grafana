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

        $requestedUuid = isset($_GET['uuid']) ? $_GET['uuid'] : null;

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

        $validIds = array_column($dashboards, 'id');
        if ($requestedUuid !== null && in_array($requestedUuid, $validIds, true)) {
            $currentUuid = $requestedUuid;
        } else {
            $currentUuid = current($dashboards)['id'];
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
        $private_key_path = GLPI_PLUGIN_DOC_DIR . '/grafana/keys/private_key.pem';
        $public_key_path  = GLPI_PLUGIN_DOC_DIR . '/grafana/keys/public_key.pem';

        if (!file_exists($private_key_path) || !file_exists($public_key_path)) {
            echo '<div class="alert alert-warning">'
                . __('Grafana plugin: RSA keys not found. Please reinstall the plugin.', 'grafana')
                . '</div>';
            return;
        }

        $private_key = file_get_contents($private_key_path);
        $public_key  = file_get_contents($public_key_path);


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
        $baseIframeUrl = $url . $dashboardUrl . '?kiosk';
        $fullUrl = $baseIframeUrl . '&auth_token=' . $token->toString();

        echo "<iframe src='" . htmlescape($fullUrl) . "' id='grafana_iframe' allowtransparency></iframe>";

        echo Html::scriptBlock("
            (function() {
                var baseIframeUrl = '" . jsescape($baseIframeUrl) . "';
                var currentTokenExpiry = 0;
                var pendingRefresh = false;
                var refreshTimeout = null;

                function getTokenExpiry(token) {
                    try {
                        var payload = JSON.parse(atob(token.split('.')[1].replace(/-/g, '+').replace(/_/g, '/')));
                        return payload.exp * 1000;
                    } catch (e) {
                        return Date.now() + 55 * 60 * 1000;
                    }
                }

                function doRefresh() {
                    $.ajax({
                        url: '" . $CFG_GLPI['url_base'] . "/plugins/grafana/ajax/refresh_token.php',
                        dataType: 'json',
                        cache: false,
                        success: function(data) {
                            if (data.token) {
                                var iframe = document.getElementById('grafana_iframe');
                                if (iframe) {
                                    iframe.src = baseIframeUrl + '&auth_token=' + data.token;
                                }
                                pendingRefresh = false;
                                scheduleRefresh(data.token);
                            }
                        },
                        error: function() {
                            refreshTimeout = setTimeout(doRefresh, 30 * 1000);
                        }
                    });
                }

                function scheduleRefresh(token) {
                    if (refreshTimeout) {
                        clearTimeout(refreshTimeout);
                    }
                    currentTokenExpiry = getTokenExpiry(token);
                    var delay = Math.max(30 * 1000, currentTokenExpiry - Date.now() - 2 * 60 * 1000);
                    refreshTimeout = setTimeout(function() {
                        if (document.visibilityState === 'hidden') {
                            pendingRefresh = true;
                        } else {
                            doRefresh();
                        }
                    }, delay);
                }

                document.addEventListener('visibilitychange', function() {
                    if (document.visibilityState === 'visible') {
                        var tokenExpiredOrClose = Date.now() >= currentTokenExpiry - 2 * 60 * 1000;
                        if (pendingRefresh || tokenExpiredOrClose) {
                            if (refreshTimeout) {
                                clearTimeout(refreshTimeout);
                            }
                            pendingRefresh = false;
                            doRefresh();
                        }
                    }
                });

                scheduleRefresh('" . $token->toString() . "');
            })();
        ");
    }
}
