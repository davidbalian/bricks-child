<?php
/** Loopback-only PHP development router, not a deployment entry point. */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('~(?:^|/)(?:\.git|\.private|\.staging|\.env)(?:/|$)~', rawurldecode($path))) { http_response_code(404); exit; }
$root = dirname(__DIR__, 2) . '/.staging/wp';
$candidate = realpath($root . $path);
if ($candidate && str_starts_with($candidate, $root . '/') && is_file($candidate)) { return false; }
require $root . '/index.php';
