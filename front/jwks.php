<?php

$dir = dirname(__DIR__, 3) . '/files/_plugins/grafana/keys/';

$details = openssl_pkey_get_details(openssl_pkey_get_public(file_get_contents($dir . 'public_key.pem')));

$n = rtrim(strtr(base64_encode($details['rsa']['n']), '+/', '-_'), '=');
$e = rtrim(strtr(base64_encode($details['rsa']['e']), '+/', '-_'), '=');

echo json_encode([
  'keys' => [[
    'kty' => 'RSA',
    'kid' => 'grafana-key-1',
    'use' => 'sig',
    'alg' => 'RS256',
    'n'   => $n,
    'e'   => $e
  ]]
]);
