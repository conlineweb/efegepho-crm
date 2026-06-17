

<?php
// Incluir la librería de Stripe
require_once('stripe-php/init.php');

require_once __DIR__ . '/../../vendor/autoload.php'; // sube una carpeta hasta /home/usuario/vendor/

use Dotenv\Dotenv;

// Cargar .env que está en tu proyecto
$dotenv = Dotenv::createImmutable(__DIR__ . '/../../'); 
$dotenv->load();


// Establecer tu clave secreta de Stripe
\Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);


// Obtener los datos enviados desde el frontend (AJAX)
$fechaActual = $_POST['fechaActual'];
$horaActual = $_POST['horaActual'];
$estatus = $_POST['estatus'];
$idclie = $_POST['idclie'];
$monto = $_POST['monto'];  // Monto recibido del formulario
$monto_centavos = round($monto * 100);  // Convertimos a centavos y redondeamos
$concepto = $_POST['concepto'];
$formaPago = $_POST['forma_pago']; // Aunque no lo usaremos aquí, lo puedes guardar si lo necesitas
//$id_pago = $_POST['id_pago'];
$email = $_POST['email'];
$currency = $_POST['currency'];
$expires_at = time() + (7 * 24 * 60 * 60);  // Expiración en 7 días (en segundos)


// Crear la sesión de Checkout en Stripe
try {
    $session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'line_items' => [
            [
                'price_data' => [
                    'currency' => $currency, // O la moneda que estés utilizando
                    'product_data' => [
                        'name' => $concepto,
                    ],
                    'unit_amount' => $monto_centavos, // Asegúrate de que el monto esté en centavos
                ],
                'quantity' => 1,
            ],
        ],
        'mode' => 'payment',
        'customer_email' => $email, // Si tienes el correo del cliente
        'success_url' => 'https://citas.efegepho.com.mx/payment_success.php?session_id={CHECKOUT_SESSION_ID}', // URL de éxito
        'cancel_url' => 'https://citas.efegepho.com.mx/payment_cancel.php', // URL de cancelación
        // 'expires_at' => $expires_at,

    ]);

    // Enviar la URL de la sesión de pago a la respuesta
    echo json_encode([
        'success' => true,
        'session_url' => $session->url,
        'session_id'=>$session->id,
        'resend'=>$resend 
    ]);
} catch (\Stripe\Exception\ApiErrorException $e) {
    // Manejar errores si la sesión de Checkout no se crea
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
