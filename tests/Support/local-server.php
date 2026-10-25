<?php

declare(strict_types=1);

// Router for "php -S" in CurlHttpClientTest: real HTTP answers, including misbehaving ones.
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url(is_string($uri) ? $uri : '/', PHP_URL_PATH);
switch ($path) {
    case '/v1/limits':
        header('Content-Type: application/json');
        echo '{"minInvoiceUsdCents":500,"maxInvoiceUsdCents":100000}';
        break;
    case '/echo':
        header('Content-Type: application/json');
        header('X-Twice: a');
        header('X-Twice: b', false);
        echo json_encode(['method' => $_SERVER['REQUEST_METHOD'], 'probe' => $_SERVER['HTTP_X_PROBE'] ?? null, 'body' => file_get_contents('php://input')]);
        break;
    case '/redirect':
        header('Location: https://evil.example/steal', true, 302);
        break;
    case '/drip':
    case '/v1/networks':
        // Headers, then one byte at a time: never silent long enough for an idle timeout.
        header('Content-Length: 1000');
        for ($i = 0; $i < 150; $i++) {
            echo ' ';
            flush();
            usleep(20_000);
        }
        break;
    default:
        http_response_code(404);
        echo '{"code":"not_found","detail":"No such endpoint"}';
}
