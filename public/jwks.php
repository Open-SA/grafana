<?php

$dir = dirname(__DIR__, 3) . '/files/_plugins/grafana/keys/';
$public_key_path = $dir . 'public_key.pem';

if (!file_exists($public_key_path)) {
    header('HTTP/1.1 500 Internal Server Error', true, 500);
    echo json_encode(['error' => 'RSA public key not found. Please reinstall the plugin.']);
    return;
}

$details = openssl_pkey_get_details(openssl_pkey_get_public(file_get_contents($public_key_path)));

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
