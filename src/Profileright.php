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
 * - Small changes names and values
 */

namespace GlpiPlugin\Grafana;

use CommonDBTM;
use CommonGLPI;
use Profile;
use Session;
use Plugin;
use GlpiPlugin\Grafana\APIClient;
use DBmysql;
use Glpi\Application\View\TemplateRenderer;

class Profileright extends Profile
{
    /**
     * Necessary right to edit the rights of this plugin.
     */
    public static $rightname = 'profile';

    /**
     * {@inheritDoc}
     * @see CommonGLPI::getTypeName()
     */
    public static function getTypeName($nb = 0)
    {
        return __('Grafana', 'grafana');
    }

    /**
     * {@inheritDoc}
     * @see CommonGLPI::getTabNameForItem()
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if (Profile::class === $item->getType() && Session::haveRight('profile', READ)) {
            return self::createTabEntry(self::getTypeName());
        }

        return '';
    }

    /**
     * {@inheritDoc}
     * @see CommonGLPI::displayTabContentForItem()
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {

        if ($item instanceof Profile && Session::haveRight('profile', READ)) {
            $profileright = new self();
            $profileright->showForm($item->fields['id']);
        }

        return true;
    }

    /**
     * Display profile rights form.
     *
     * @param integer $id Profile id
     * @param array $options
     *
     * @return bool
     */
    public function showForm($id, $options = [])
    {
        if (!Session::haveRight('profile', READ)) {
            return false;
        }

        // Capture hook output so it can be passed as a template variable
        ob_start();
        Plugin::doHook('pre_item_form', ['item' => $this, 'options' => &$options]);
        $hook_html = ob_get_clean();

        // Build per-dashboard rows; dropdownRight() outputs directly so capture it
        $apiclient  = new APIClient();
        $dashboards = $apiclient->getDashboards();
        $rows = [];
        foreach ($dashboards as $dashboard) {
            ob_start();
            Profile::dropdownRight(
                sprintf('dashboard[%s]', $dashboard['uid']),
                [
                    'value'   => self::getProfileRightForDashboard($id, $dashboard['uid']),
                    'nonone'  => 0,
                    'noread'  => 0,
                    'nowrite' => 1,
                ],
            );
            $rows[] = [
                'title'    => $dashboard['title'],
                'dropdown' => ob_get_clean(),
            ];
        }

        TemplateRenderer::getInstance()->display('@grafana/profileright.html.twig', [
            'form_url'    => self::getFormURL(),
            'profiles_id' => $id,
            'type_name'   => self::getTypeName(),
            'can_update'  => Session::haveRight('profile', UPDATE),
            'hook_html'   => $hook_html,
            'rows'        => $rows,
        ]);

        return true;
    }

    /**
     * Check if profile is able to view at least one dashboard.
     *
     * @param integer $profileId
     *
     * @return boolean
     */
    public static function canProfileViewDashboards($profileId)
    {
        /** @var \DBmysql $DB */
        global $DB;

        $iterator = $DB->request(
            [
                'FROM'  => self::getTable(),
                'WHERE' => [
                    'profiles_id' => $profileId,
                ],
            ],
        );

        foreach ($iterator as $right) {
            if ($right['rights'] & READ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if profile is able to view given dashboard.
     *
     * @param integer $profileId
     * @param integer $dashboardUuid
     *
     * @return integer
     */
    public static function canProfileViewDashboard($profileId, $dashboardUuid)
    {
        return self::getProfileRightForDashboard($profileId, $dashboardUuid) & READ;
    }

    /**
     * Returns profile rights for given dashboard.
     *
     * @param integer $profileId
     * @param integer $dashboardUuid
     *
     * @return integer
     */
    private static function getProfileRightForDashboard($profileId, $dashboardUuid)
    {
        $rightCriteria = [
            'profiles_id'    => $profileId,
            'dashboard_uuid' => $dashboardUuid,
        ];

        $profileRight = new self();
        if ($profileRight->getFromDBByCrit($rightCriteria)) {
            return $profileRight->fields['rights'];
        }

        return 0;
    }

    /**
     * Defines profile rights for dashboard.
     *
     * @param integer $profileId
     * @param integer $dashboardUuid
     * @param integer $rights
     *
     * @return void
     */
    public static function setDashboardRightsForProfile($profileId, $dashboardUuid, $rights)
    {
        $profileRight = new self();

        $rightsExists = $profileRight->getFromDBByCrit(
            [
                'profiles_id'    => $profileId,
                'dashboard_uuid' => $dashboardUuid,
            ],
        );

        if ($rightsExists) {
            $profileRight->update(
                [
                    'id'     => $profileRight->fields['id'],
                    'rights' => $rights,
                ],
            );
        } else {
            $profileRight->add(
                [
                    'profiles_id'    => $profileId,
                    'dashboard_uuid' => $dashboardUuid,
                    'rights'         => $rights,
                ],
            );
        }
    }
}
