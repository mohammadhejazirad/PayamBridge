<?php

declare(strict_types=1);

// Ask Packagist to re-scan both GitHub repositories after their tags exist.
$username = getenv('PACKAGIST_USERNAME');
$token = getenv('PACKAGIST_API_TOKEN');
$laravelRepository = getenv('LARAVEL_SPLIT_REPOSITORY');
$rootRepository = getenv('GITHUB_REPOSITORY');
if (! $username || ! $token || ! $laravelRepository || ! $rootRepository) {
    throw new RuntimeException('Packagist credentials or repository names are missing.');
}
foreach ([$rootRepository, $laravelRepository] as $repository) {
    if (! preg_match('~^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$~D', $repository)) {
        throw new RuntimeException('Invalid GitHub repository name.');
    }
    $url = 'https://packagist.org/api/update-package?'.http_build_query(['username' => $username, 'apiToken' => $token]);
    $body = json_encode(['repository' => ['url' => 'https://github.com/'.$repository]], JSON_THROW_ON_ERROR);
    $context = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
        'content' => $body,
        'ignore_errors' => true,
        'timeout' => 30,
    ]]);
    $response = @file_get_contents($url, false, $context);
    $status = '';
    $headers = function_exists('http_get_last_response_headers') ? http_get_last_response_headers() : ($http_response_header ?? []);
    foreach ($headers ?? [] as $header) {
        if (str_starts_with($header, 'HTTP/')) {
            $status = $header;
        }
    }
    if ($response === false || ! preg_match('~^HTTP/\S+ 2\d\d\b~', $status)) {
        throw new RuntimeException('Packagist rejected the update for '.$repository.' ('.$status.').');
    }
    $result = json_decode($response, true);
    if (! is_array($result) || ($result['status'] ?? null) !== 'success') {
        throw new RuntimeException('Packagist did not confirm a successful update for '.$repository.'.');
    }
    fwrite(STDOUT, 'Packagist accepted update request for '.$repository.".\n");
}
