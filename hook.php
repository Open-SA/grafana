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
use GlpiPlugin\Grafana\Profileright;

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

    $table = Profileright::getTable();

    if (!$DB->tableExists($table)) {
        $migration->displayMessage("Installing $table");

        $query = "CREATE TABLE IF NOT EXISTS `$table` (
                     `id` int {$default_key_sign} NOT NULL AUTO_INCREMENT,
                     `profiles_id` int {$default_key_sign} NOT NULL,
                     `dashboard_uuid` varchar(200) NOT NULL,
                     `rights` int NOT NULL,
                     PRIMARY KEY (`id`),
                     UNIQUE `profiles_id_dashboard_uuid` (`profiles_id`, `dashboard_uuid`)
                  ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
        $DB->query($query) or die($DB->error());
    }

    $migration->executeMigration();

    // fill config table with default values if missing
    foreach (
        [
            // api access
            'url'           => '',
            'token'       => '',
            'username'      => '',
        ] as $key => $value
    ) {
        GlpiConfig::setConfigurationValues('plugin:grafana', [$key => $value]);
    }

    $keysDir = GLPI_PLUGIN_DOC_DIR . '/grafana/keys';

    if (!is_dir($keysDir)) {
        mkdir($keysDir, 0755, true);
    }

    $private_key_path = $keysDir . '/private_key.pem';
    $public_key_path = $keysDir . '/public_key.pem';

    if (file_exists($private_key_path) || file_exists($public_key_path)) {
        return true;
    }

    $key_pair = openssl_pkey_new([
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);

    openssl_pkey_export($key_pair, $private_key);
    file_put_contents($private_key_path, $private_key);

    $keyDetails = openssl_pkey_get_details($key_pair);
    $public_key = $keyDetails['key'];
    file_put_contents($public_key_path, $public_key);

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
    // $config = new GlpiConfig();
    // $config->deleteByCriteria(['context' => 'plugin:grafana']);

    $DB->query('DROP TABLE IF EXISTS `' . Profileright::getTable() . '`');


    return true;
}
