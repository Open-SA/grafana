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

use Lcobucci\JWT\Configuration;

use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use GlpiPlugin\Grafana\Config;

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
    ->expiresAt($now->modify('+1 hour')) // Expires after an hour
    ->relatedTo($config['username']) // Sub claim with the username of the user in the config
    ->withHeader('kid', 'grafana-key-1') // Kinda selects the public key to use Grafana side
    ->getToken($signer_config->signer(), $signer_config->signingKey()); // Retrieves the generated token

echo json_encode([
    'token' => $token->toString(),
]);
