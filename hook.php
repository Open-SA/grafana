<?php

/**
 * -------------------------------------------------------------------------
 * Grafana plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of Grafana.
 *
 * Grafana is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Grafana is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Grafana. If not, see <http://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2025 by Grafana plugin team.
 * @license   GPLv2 https://www.gnu.org/licenses/gpl-2.0.html
 * @link      https://github.com/Open-Sa/grafana
 * -------------------------------------------------------------------------
 */

use Config as GlpiConfig;
use GlpiPlugin\Grafana\DashboardRight;

/**
 * Plugin install process
 *
 * @return boolean
 */
function plugin_grafana_install()
{

    global $DB;

    $version   = plugin_version_grafana();
    $migration = new Migration($version['version']);

    $default_charset = DBConnection::getDefaultCharset();
    $default_collation = DBConnection::getDefaultCollation();
    $default_key_sign = DBConnection::getDefaultPrimaryKeySignOption();

    $newTable = DashboardRight::getTable();
    $oldTable = 'glpi_plugin_grafana_profilerights';

    if (!$DB->tableExists($newTable)) {
        $migration->displayMessage("Installing $newTable");

        $query = "CREATE TABLE IF NOT EXISTS `$newTable` (
                     `id` int {$default_key_sign} NOT NULL AUTO_INCREMENT,
                     `dashboard_uuid` varchar(200) NOT NULL,
                     `actor_type` varchar(20) NOT NULL,
                     `actor_id` int {$default_key_sign} NOT NULL,
                     PRIMARY KEY (`id`),
                     UNIQUE KEY `dashboard_uuid_actor` (`dashboard_uuid`, `actor_type`, `actor_id`)
                  ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
        $DB->doQuery($query);
    }

    if ($DB->tableExists($oldTable)) {
        $migration->displayMessage("Migrating profile rights from $oldTable to $newTable");
        $DB->doQuery("
            INSERT IGNORE INTO `$newTable` (`dashboard_uuid`, `actor_type`, `actor_id`)
            SELECT `dashboard_uuid`, 'Profile', `profiles_id`
            FROM `$oldTable`
            WHERE (`rights` & 1) > 0
        ");
        $DB->doQuery("DROP TABLE `$oldTable`");
    }

    // Default tab actors table — added in 1.3.0, runs on upgrade too
    $defaultTabTable = 'glpi_plugin_grafana_defaulttabs';
    if (!$DB->tableExists($defaultTabTable)) {
        $migration->displayMessage("Installing $defaultTabTable");

        $query = "CREATE TABLE IF NOT EXISTS `$defaultTabTable` (
                     `id` int {$default_key_sign} NOT NULL AUTO_INCREMENT,
                     `actor_type` varchar(20) NOT NULL,
                     `actor_id` int {$default_key_sign} NOT NULL,
                     PRIMARY KEY (`id`),
                     UNIQUE KEY `actor_type_actor_id` (`actor_type`, `actor_id`)
                  ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
        $DB->doQuery($query);
    }

    $migration->executeMigration();

    // Only insert missing keys — setConfigurationValues does an upsert, so we
    // must guard existing values to avoid wiping the config on upgrade.
    $existing = GlpiConfig::getConfigurationValues('plugin:grafana');
    foreach (
        [
            'url'      => '',
            'token'    => '',
            'username' => '',
        ] as $key => $default
    ) {
        if (!array_key_exists($key, $existing)) {
            GlpiConfig::setConfigurationValues('plugin:grafana', [$key => $default]);
        }
    }

    $keysDir = GLPI_PLUGIN_DOC_DIR . '/grafana/keys';

    if (!is_dir($keysDir) && !mkdir($keysDir, 0755, true)) {
        $migration->displayWarning("Grafana plugin: could not create keys directory: $keysDir");
        return false;
    }

    $private_key_path = $keysDir . '/private_key.pem';
    $public_key_path = $keysDir . '/public_key.pem';

    if (file_exists($private_key_path) && file_exists($public_key_path)) {
        return true;
    }

    $key_pair = openssl_pkey_new([
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);

    if ($key_pair === false) {
        $migration->displayWarning('Grafana plugin: could not generate RSA key pair. ' . openssl_error_string());
        return false;
    }

    openssl_pkey_export($key_pair, $private_key);
    if (file_put_contents($private_key_path, $private_key) === false) {
        $migration->displayWarning("Grafana plugin: could not write private key to $private_key_path");
        return false;
    }

    $keyDetails = openssl_pkey_get_details($key_pair);
    $public_key = $keyDetails['key'];
    if (file_put_contents($public_key_path, $public_key) === false) {
        $migration->displayWarning("Grafana plugin: could not write public key to $public_key_path");
        return false;
    }

    return true;
}

/**
 * Plugin uninstall process
 *
 * @return boolean
 */
function plugin_grafana_uninstall()
{
    global $DB;
    $config = new GlpiConfig();
    $config->deleteByCriteria(['context' => 'plugin:grafana']);

    $DB->doQuery('DROP TABLE IF EXISTS `' . DashboardRight::getTable() . '`');
    $DB->doQuery('DROP TABLE IF EXISTS `glpi_plugin_grafana_defaulttabs`');
    $DB->doQuery('DROP TABLE IF EXISTS `glpi_plugin_grafana_profilerights`');


    return true;
}
