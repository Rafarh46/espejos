<?php

declare(strict_types=1);

header('Content-Type: application/json');

$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Instala la librería stripe/stripe-php con Composer para habilitar pagos en línea.'
    ]);
    exit;
}

require_once $autoloadPath;

$secretKey = getenv('STRIPE_SECRET_KEY') ?: '';
$publishableKey = getenv('STRIPE_PUBLISHABLE_KEY') ?: '';

if ($secretKey === '' || $publishableKey === '') {
    http_response_code(500);
    echo json_encode([
        'error' => 'Configura STRIPE_SECRET_KEY y STRIPE_PUBLISHABLE_KEY en el entorno del servidor.'
    ]);
    exit;
}

$payload = file_get_contents('php://input');
$data = json_decode($payload, true);

if (!is_array($data) || !isset($data['items']) || !is_array($data['items'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Solicitud inválida: no se recibieron artículos.']);
    exit;
}

$lineItems = [];
foreach ($data['items'] as $item) {
    if (!isset($item['name'], $item['price'], $item['quantity'])) {
        continue;
    }

    $name = trim((string) $item['name']);
    $price = (float) $item['price'];
    $quantity = (int) $item['quantity'];

    if ($name === '' || $price <= 0 || $quantity <= 0) {
        continue;
    }

    $lineItems[] = [
        'price_data' => [
            'currency' => 'mxn',
            'product_data' => [
                'name' => $name
            ],
            'unit_amount' => (int) round($price * 100)
        ],
        'quantity' => $quantity
    ];
}

if (count($lineItems) === 0) {
    http_response_code(400);
    echo json_encode(['error' => 'No hay artículos válidos para procesar.']);
    exit;
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$origin = sprintf('%s://%s', $scheme, $host);

\Stripe\Stripe::setApiKey($secretKey);

try {
    $session = \Stripe\Checkout\Session::create([
        'mode' => 'payment',
        'line_items' => $lineItems,
        'success_url' => $origin . '/tienda-online.html?success=1',
        'cancel_url' => $origin . '/tienda-online.html?canceled=1'
    ]);

    echo json_encode([
        'sessionId' => $session->id,
        'publishableKey' => $publishableKey
    ]);
} catch (\Stripe\Exception\ApiErrorException $exception) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Stripe error: ' . $exception->getMessage()
    ]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Error interno al crear la sesión de pago.'
    ]);
}

