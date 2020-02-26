#!/usr/bin/env php
<?php

/**
 * Setup hash-salt for Drupal.
 *
 * This script is invoked during project creation, is not intended for
 * other purposes. Use with caution or you can get lockout of your
 * Drupal site.
 */

$hash_salt_file = getenv('HASH_SALT_FILE');

if (empty($hash_salt_file)) {
  $hash_salt_file = dirname(__FILE__, 2) . DIRECTORY_SEPARATOR . '.salt';
}

if (!file_exists($hash_salt_file)) {
  $salt = password_hash(base64_encode((string) microtime()), PASSWORD_DEFAULT, ['salt' => random_bytes(22)]);
  file_put_contents($hash_salt_file, $salt);
  chmod($hash_salt_file, 0640);
  echo "Hash salt generated and written on file ${hash_salt_file}!" . PHP_EOL;
}
