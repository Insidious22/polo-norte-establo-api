<?php

namespace App\Adapters;

use App\Exceptions\MeteoServiceException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;
use Throwable;

/**
 * Adaptador del servicio SOAP heredado: MeteoPoloNorteService.
 *
 * Responsabilidad única:
 * - Traducir objeto de dominio -> XML SOAP 1.1 (ObtenerPronosticoRequest)
 * - Enviar la petición HTTP con timeout de 3 segundos y reintento con backoff (máx. 2 intentos)
 * - Traducir la respuesta XML -> objeto de dominio (o lanzar MeteoServiceException si hay Fault/timeout)
 *
 * El resto del sistema (controladores, servicios, repositorios) NO conoce XML ni SOAP.
 */
class MeteoAdapter
{
    public const PRONOSTICO_DESPEJADO   = 'DESPEJADO';
    public const PRONOSTICO_NEVADA_LEVE = 'NEVADA_LEVE';
    public const PRONOSTICO_VENTISCA    = 'VENTISCA';

    private const PRONOSTICOS_VALIDOS = [
        self::PRONOSTICO_DESPEJADO,
        self::PRONOSTICO_NEVADA_LEVE,
        self::PRONOSTICO_VENTISCA,
    ];

    /**
     * Consulta el pronóstico del clima en MeteoPoloNorteService.
     *
     * @return array{zona: string, fecha: string, pronostico: string}
     * @throws MeteoServiceException Si el servicio responde con soap:Fault o falla tras reintentos.
     */
    public function obtenerPronostico(string $zona, string $fecha): array
    {
        $url = (string) config('meteo.url');
        $timeout = (float) config('meteo.timeout_seconds', 3);
        $maxIntentos = max(1, (int) config('meteo.max_attempts', 2));
        $backoffMs = max(0, (int) config('meteo.backoff_ms', 300));

        $xmlRequest = $this->construirSoapRequest($zona, $fecha);
        $ultimoError = null;

        for ($intento = 1; $intento <= $maxIntentos; $intento++) {
            try {
                $response = Http::timeout($timeout)
                    ->withHeaders([
                        'Content-Type' => 'text/xml; charset=utf-8',
                        'SOAPAction'   => '"http://polonorte.beatlech/meteo/ObtenerPronostico"',
                    ])
                    ->withBody($xmlRequest, 'text/xml; charset=utf-8')
                    ->post($url);

                $xmlBody = $response->body();

                // Parsear respuesta XML (incluyendo soap:Fault aunque el HTTP sea 500 o 200)
                return $this->parsearSoapResponse($xmlBody, $zona, $fecha, $response->status());
            } catch (ConnectionException $e) {
                $ultimoError = new MeteoServiceException(
                    "Timeout ({$timeout}s) o error de conexión con MeteoPoloNorteService: {$e->getMessage()}",
                    'TIMEOUT_O_CONEXION'
                );
            } catch (MeteoServiceException $e) {
                $ultimoError = $e;
            } catch (Throwable $e) {
                $ultimoError = new MeteoServiceException(
                    "Error inesperado al consultar MeteoPoloNorteService: {$e->getMessage()}",
                    'ERROR_INESPERADO'
                );
            }

            Log::warning('Fallo al consultar MeteoPoloNorteService (SOAP)', [
                'intento'     => $intento,
                'maxIntentos' => $maxIntentos,
                'zona'        => $zona,
                'fecha'       => $fecha,
                'motivo'      => $ultimoError->motivo,
                'detalle'     => $ultimoError->getMessage(),
            ]);

            // Backoff exponencial simple antes del siguiente intento (ej. 300ms)
            if ($intento < $maxIntentos && $backoffMs > 0) {
                usleep($backoffMs * $intento * 1000);
            }
        }

        throw $ultimoError ?? new MeteoServiceException('Servicio meteorológico no disponible');
    }

    /**
     * Construye el sobre SOAP 1.1 exactamente según el contrato de MeteoPoloNorteService.
     */
    private function construirSoapRequest(string $zona, string $fecha): string
    {
        $zonaEscapada = htmlspecialchars($zona, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $fechaEscapada = htmlspecialchars($fecha, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
                  xmlns:mt="http://polonorte.beatlech/meteo">
  <soapenv:Body>
    <mt:ObtenerPronosticoRequest>
      <mt:zona>{$zonaEscapada}</mt:zona>
      <mt:fecha>{$fechaEscapada}</mt:fecha>
    </mt:ObtenerPronosticoRequest>
  </soapenv:Body>
</soapenv:Envelope>
XML;
    }

    /**
     * Traduce el XML recibido desde el servicio SOAP a un arreglo limpio de dominio.
     *
     * @return array{zona: string, fecha: string, pronostico: string}
     */
    private function parsearSoapResponse(string $xmlBody, string $zona, string $fecha, int $httpStatus): array
    {
        if (trim($xmlBody) === '') {
            throw new MeteoServiceException(
                "Respuesta vacía desde MeteoPoloNorteService (HTTP {$httpStatus})",
                'RESPUESTA_VACIA'
            );
        }

        $prevUseInternal = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlBody);
        libxml_clear_errors();
        libxml_use_internal_errors($prevUseInternal);

        if (!$xml instanceof SimpleXMLElement) {
            throw new MeteoServiceException(
                "Respuesta XML inválida desde MeteoPoloNorteService (HTTP {$httpStatus})",
                'XML_INVALIDO'
            );
        }

        // 1. Verificar si el servicio SOAP devolvió un soap:Fault
        $faultNodes = $xml->xpath("//*[local-name()='Fault']");
        if (!empty($faultNodes)) {
            $faultStringNodes = $xml->xpath("//*[local-name()='Fault']//*[local-name()='faultstring']");
            $faultMessage = !empty($faultStringNodes) ? trim((string) $faultStringNodes[0]) : 'SOAP Fault';

            throw new MeteoServiceException(
                "SOAP Fault recibido de MeteoPoloNorteService: {$faultMessage}",
                'SOAP_FAULT'
            );
        }

        if ($httpStatus >= 400) {
            throw new MeteoServiceException(
                "MeteoPoloNorteService respondió con HTTP {$httpStatus}",
                'HTTP_ERROR'
            );
        }

        // 2. Extraer el pronóstico sin depender del prefijo de namespace (mt:, ns2:, etc.)
        $pronosticoNodes = $xml->xpath("//*[local-name()='pronostico']");
        $pronostico = !empty($pronosticoNodes) ? strtoupper(trim((string) $pronosticoNodes[0])) : '';

        if (!in_array($pronostico, self::PRONOSTICOS_VALIDOS, true)) {
            throw new MeteoServiceException(
                "Pronóstico desconocido recibido desde MeteoPoloNorteService: '{$pronostico}'",
                'PRONOSTICO_DESCONOCIDO'
            );
        }

        return [
            'zona'       => $zona,
            'fecha'      => $fecha,
            'pronostico' => $pronostico,
        ];
    }
}
