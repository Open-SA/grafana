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
    /**
     * Derive a stable key ID from the public key material, so it changes
     * automatically whenever the RSA key pair is rotated instead of staying
     * fixed forever (which would make JWKS-consuming caches unable to tell
     * an old key apart from a newly generated one).
     */
    public static function keyId(string $publicKeyPem): string
    {
        return substr(hash('sha256', $publicKeyPem), 0, 16);
    }

    /**
     * Build the JWK (JSON Web Key) representation of the plugin's public key,
     * for exposure through the JWKS endpoint.
     */
    public static function publicJwk(string $publicKeyPem): array
    {
        $details = openssl_pkey_get_details(openssl_pkey_get_public($publicKeyPem));

        return [
            'kty' => 'RSA',
            'kid' => self::keyId($publicKeyPem),
            'use' => 'sig',
            'alg' => 'RS256',
            'n'   => rtrim(strtr(base64_encode($details['rsa']['n']), '+/', '-_'), '='),
            'e'   => rtrim(strtr(base64_encode($details['rsa']['e']), '+/', '-_'), '='),
        ];
    }

    /**
     * Generate a fresh 2048-bit RSA key pair for signing JWTs.
     *
     * @return array{private_key: string, public_key: string}|false
     */
    public static function generateKeyPair()
    {
        $key_pair = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($key_pair === false) {
            return false;
        }

        openssl_pkey_export($key_pair, $private_key);
        $public_key = openssl_pkey_get_details($key_pair)['key'];

        return [
            'private_key' => $private_key,
            'public_key'  => $public_key,
        ];
    }

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
            ->withHeader('kid', self::keyId($public_key))
            ->getToken($signer_config->signer(), $signer_config->signingKey());

        return $token->toString();
    }
}
