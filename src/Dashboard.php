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
use GlpiPlugin\Grafana\DashboardRight;
use GlpiPlugin\Grafana\APIClient;
use Central;
use Session;
use Dropdown;
use DateTimeImmutable;
use Glpi\Application\View\TemplateRenderer;

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
                if (DashboardRight::canUserViewDashboards((int) Session::getLoginUserID())) {
                    return self::createTabEntry(self::getTypeName(), 0, -1, 'ti ti-chart-infographic');
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
                if (DashboardRight::canUserViewDashboards((int) Session::getLoginUserID())) {
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
        $apiError   = null;
        if ($dashboards === false) {
            $apiError   = $apiclient->getLastError();
            $dashboards = [];
        } elseif (is_array($dashboards)) {
            $userId     = (int) Session::getLoginUserID();
            $dashboards = array_filter(
                $dashboards,
                function ($dashboard) use ($userId) {
                    return DashboardRight::canUserViewDashboard($userId, $dashboard['uid']);
                },
            );
        }

        if ($apiError !== null) {
            TemplateRenderer::getInstance()->display('@grafana/dashboard.html.twig', [
                'api_error'    => $apiError,
                'dropdown'     => '',
                'keys_missing' => false,
            ]);
            return;
        }

        if (empty($dashboards)) {
            return;
        }

        $validIds = array_column($dashboards, 'id');
        if ($requestedUuid !== null && in_array((int) $requestedUuid, $validIds, true)) {
            $currentUuid = (int) $requestedUuid;
        } else {
            $currentUuid = current($dashboards)['id'];
        }

        ob_start();
        Dropdown::showFromArray(
            'current_dashboard',
            array_combine(array_column($dashboards, 'id'), array_column($dashboards, 'title')),
            [
                'on_change' => $is_helpdesk
                    ? 'location.href = location.origin+location.pathname+"?uuid="+$(this).val()'
                    : 'reloadTab("uuid=" + $(this).val());',
                'value'     => $currentUuid,
            ],
        );
        $dropdown = ob_get_clean();

        $private_key_path = GLPI_PLUGIN_DOC_DIR . '/grafana/keys/private_key.pem';
        $public_key_path  = GLPI_PLUGIN_DOC_DIR . '/grafana/keys/public_key.pem';

        if (!file_exists($private_key_path) || !file_exists($public_key_path)) {
            TemplateRenderer::getInstance()->display('@grafana/dashboard.html.twig', [
                'dropdown'     => $dropdown,
                'keys_missing' => true,
            ]);
            return;
        }

        $config      = Config::getConfig();
        $private_key = file_get_contents($private_key_path);
        $public_key  = file_get_contents($public_key_path);

        $signer_config = Configuration::forAsymmetricSigner(
            new Sha256(),
            InMemory::plainText($private_key),
            InMemory::plainText($public_key),
        );

        $now   = new DateTimeImmutable();
        $token = $signer_config->builder()
            ->issuedBy('glpi_plugin')
            ->expiresAt($now->modify('+1 hour'))
            ->relatedTo($config['username'])
            ->withHeader('kid', 'grafana-key-1')
            ->getToken($signer_config->signer(), $signer_config->signingKey());

        $currentDashboard = current(array_filter($dashboards, function ($dashboard) use ($currentUuid) {
            return $dashboard['id'] == $currentUuid;
        }));
        $dashboardUrl = $currentDashboard['url'];
        $url          = rtrim($config['url'], '/');
        if (strpos($dashboardUrl, '/d/') !== 0) {
            $dashboardUrl = substr($dashboardUrl, strpos($dashboardUrl, '/d/'));
        }
        // The kiosk parameter hides the Grafana header/footer so only the dashboard is shown
        $baseIframeUrl = $url . $dashboardUrl . '?kiosk' . Config::buildGrafanaUrlParams();

        TemplateRenderer::getInstance()->display('@grafana/dashboard.html.twig', [
            'dropdown'        => $dropdown,
            'keys_missing'    => false,
            'iframe_src'      => $baseIframeUrl . '&auth_token=' . $token->toString(),
            'base_iframe_url' => $baseIframeUrl,
            'initial_token'   => $token->toString(),
            'refresh_url'     => $CFG_GLPI['url_base'] . '/plugins/grafana/ajax/refresh_token.php',
        ]);
    }
}
