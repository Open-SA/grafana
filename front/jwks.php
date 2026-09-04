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

include('../../../inc/includes.php');

header('Content-Type: application/json');
header('Cache-Control: no-store');

use Config as GlpiConfig;

$config = GlpiConfig::getConfigurationValues('plugin:grafana');

if (empty($config['public_key'])) {
    header('HTTP/1.1 500 Internal Server Error', true, 500);
    echo json_encode(['error' => 'RSA public key not found. Please reinstall the plugin.']);
    return;
}

$details = openssl_pkey_get_details(openssl_pkey_get_public($config['public_key']));

$n = rtrim(strtr(base64_encode($details['rsa']['n']), '+/', '-_'), '=');
$e = rtrim(strtr(base64_encode($details['rsa']['e']), '+/', '-_'), '=');

echo json_encode([
    'keys' => [[
        'kty' => 'RSA',
        'kid' => 'grafana-key-1',
        'use' => 'sig',
        'alg' => 'RS256',
        'n'   => $n,
        'e'   => $e,
    ]],
]);
