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
use Html;
use DBmysql;

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

        echo '<form method="post" action="' . self::getFormURL() . '">';
        echo '<div class="spaced" id="tabsbody">';
        echo '<table class="tab_cadre_fixe" id="mainformtable">';

        echo '<tr class="headerRow"><th colspan="2">' . self::getTypeName() . '</th></tr>';

        Plugin::doHook('pre_item_form', ['item' => $this, 'options' => &$options]);

        echo '<tr><th colspan="2">' . __('Rights management', 'grafana') . '</th></tr>';

        echo '<input type="hidden" name="profiles_id" value="' . $id . '" />';

        if (Session::haveRight('profile', UPDATE)) {
            echo '<tr class="tab_bg_4">';
            echo '<td colspan="2" class="center">';
            echo '<button type="submit" class="btn btn-outline-secondary" name="set_rights_to_all" value="1">'
                . "<i class='ti ti-check'></i>"
                . '<span>' . __('Allow access to all', 'grafana') . '</span>'
                . '</button>';
            echo ' &nbsp; ';
            echo '<button type="submit" class="btn btn-outline-secondary" name="set_rights_to_all" value="0">'
                . "<i class='ti ti-forbid'></i>"
                . '<span>' . __('Disallow access to all', 'grafana') . '</span>'
                . '</button>';
            echo '</td>';
            echo '</tr>';
        }

        $apiclient  = new APIClient();
        $dashboards = $apiclient->getDashboards();

        foreach ($dashboards as $dashboard) {
            echo '<tr class="tab_bg_1">';
            echo '<td>' . $dashboard['title'] . '</td>';
            echo '<td>';
            Profile::dropdownRight(
                sprintf('dashboard[%s]', $dashboard['uid']),
                [
                    'value'   => self::getProfileRightForDashboard($id, $dashboard['uid']),
                    'nonone'  => 0,
                    'noread'  => 0,
                    'nowrite' => 1,
                ],
            );
            echo '</td>';
            echo '</tr>';
        }

        if (Session::haveRight('profile', UPDATE)) {
            echo '<tr class="tab_bg_4">';
            echo '<td colspan="2" class="center">';
            echo Html::submit(_sx('button', 'Save'), [
                'name'  => 'update',
                'icon'  => 'ti ti-device-floppy',
                'class' => 'btn btn-primary',
            ]);
            echo '</td>';
            echo '</tr>';
        }

        echo '</table>';
        echo '</div>';

        Html::closeForm();

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
