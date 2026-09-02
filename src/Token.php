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

namespace GlpiPlugin\Grafana;

use DateTimeImmutable;
use GLPIKey;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;

class Token
{
    public static function mint(array $config): string
    {
        $private_key = (new GLPIKey())->decrypt($config['private_key']);
        $public_key  = $config['public_key'];

        $signer_config = Configuration::forAsymmetricSigner(
            new Sha256(),
            InMemory::plainText($private_key),
            InMemory::plainText($public_key),
        );

        $now   = new DateTimeImmutable();
        $token = $signer_config->builder()
            ->issuedBy('glpi_plugin')
            ->permittedFor(rtrim($config['url'], '/'))
            ->identifiedBy(bin2hex(random_bytes(16)))
            ->expiresAt($now->modify('+' . max(3, (int) $config['token_lifetime']) . ' minutes'))
            ->relatedTo($config['username'])
            ->withHeader('kid', 'grafana-key-1')
            ->getToken($signer_config->signer(), $signer_config->signingKey());

        return $token->toString();
    }
}
