<?php

$host = parse_url(getenv('APP_URL'), PHP_URL_HOST);
$context = stream_context_create(['http' => ['header' => "Host: $host\r\n", 'timeout' => 4, 'ignore_errors' => true]]);
$body = @file_get_contents('http://127.0.0.1:8080/ready', false, $context);
exit($body === '{"status":"ready"}' ? 0 : 1);
