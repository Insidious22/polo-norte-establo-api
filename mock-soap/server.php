<?php
/**
 * Mock local del servicio SOAP heredado: MeteoPoloNorteService.
 *
 * Levantar con:
 *   php -S 127.0.0.1:4001 mock-soap/server.php
 *
 * WSDL disponible en:
 *   GET http://127.0.0.1:4001/meteo?wsdl
 *
 * Reglas de prueba en POST /meteo:
 * - fecha = 2026-12-24 -> soap:Fault ("Estación sin señal") [regla oficial del examen]
 * - fecha = 2026-12-23 -> Demora 5s para simular timeout (> 3s)
 * - fecha = 2026-12-22 -> VENTISCA (provoca 422 en la API)
 * - fecha = 2026-12-21 -> NEVADA_LEVE (permite programar -> 201)
 * - Cualquier otra fecha -> DESPEJADO (permite programar -> 201)
 */

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Servir WSDL si piden ?wsdl
if ($method === 'GET' && isset($_GET['wsdl'])) {
    header('Content-Type: text/xml; charset=utf-8');
    readfile(__DIR__ . '/meteo.wsdl');
    exit;
}

if ($method === 'GET') {
    header('Content-Type: text/plain; charset=utf-8');
    echo "MeteoPoloNorteService SOAP Mock activo.\n";
    echo "WSDL: http://127.0.0.1:4001/meteo?wsdl\n";
    echo "Endpoint SOAP (POST): http://127.0.0.1:4001/meteo\n";
    exit;
}

if ($method !== 'POST' || $path !== '/meteo') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Ruta no encontrada. Use POST /meteo o GET /meteo?wsdl\n";
    exit;
}

$rawBody = file_get_contents('php://input') ?: '';
libxml_use_internal_errors(true);
$xml = simplexml_load_string($rawBody);

if (!$xml instanceof SimpleXMLElement) {
    responderFault('soap:Client', 'Solicitud SOAP XML malformada', 400);
}

$fechaNodes = $xml->xpath("//*[local-name()='fecha']");
$zonaNodes  = $xml->xpath("//*[local-name()='zona']");

$fecha = !empty($fechaNodes) ? trim((string) $fechaNodes[0]) : '';
$zona  = !empty($zonaNodes)  ? trim((string) $zonaNodes[0])  : '';

if ($fecha === '' || $zona === '') {
    responderFault('soap:Client', 'Los campos zona y fecha son obligatorios', 400);
}

// 1. Caso oficial del examen: fecha 2026-12-24 provoca soap:Fault
if ($fecha === '2026-12-24') {
    responderFault('soap:Server', 'Estación sin señal', 500);
}

// 2. Caso opcional de timeout (> 3 segundos)
if ($fecha === '2026-12-23') {
    sleep(5);
}

// 3. Pronósticos deterministas para probar todas las ramas en Postman
$pronostico = match ($fecha) {
    '2026-12-22' => 'VENTISCA',
    '2026-12-21' => 'NEVADA_LEVE',
    default      => 'DESPEJADO',
};

header('Content-Type: text/xml; charset=utf-8');
http_response_code(200);

echo <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
                  xmlns:mt="http://polonorte.beatlech/meteo">
  <soapenv:Body>
    <mt:ObtenerPronosticoResponse>
      <mt:pronostico>{$pronostico}</mt:pronostico>
    </mt:ObtenerPronosticoResponse>
  </soapenv:Body>
</soapenv:Envelope>
XML;
exit;

function responderFault(string $faultCode, string $faultString, int $httpStatus): never
{
    $codeEsc = htmlspecialchars($faultCode, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $msgEsc  = htmlspecialchars($faultString, ENT_XML1 | ENT_QUOTES, 'UTF-8');

    header('Content-Type: text/xml; charset=utf-8');
    http_response_code($httpStatus);

    echo <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <soap:Fault>
      <faultcode>{$codeEsc}</faultcode>
      <faultstring>{$msgEsc}</faultstring>
    </soap:Fault>
  </soap:Body>
</soap:Envelope>
XML;
    exit;
}
