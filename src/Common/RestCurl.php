<?php

namespace NFePHP\NFSeNacional\Common;

/**
 * Cliente HTTP REST com mTLS para a API NFS-e Nacional.
 *
 * Estende SoapBase para aproveitar o gerenciamento de certificados
 * (gravação de arquivos PEM temporários, cleanup, etc.) e adiciona
 * os métodos GET/POST necessários para a API REST do ADN.
 *
 * @category  NFePHP
 * @package   NFePHP\NFSeNacional\Common\RestCurl
 * @copyright Copyright (c) 2024
 * @license   http://www.gnu.org/licenses/lgpl.txt LGPLv3+
 * @license   https://opensource.org/licenses/MIT MIT
 * @license   http://www.gnu.org/licenses/gpl.txt GPLv3+
 * @author    Marlon O. Barbosa <marlon.barbosa@focusit.com.br>
 */

use NFePHP\Common\Certificate;
use NFePHP\Common\Soap\SoapBase;
use NFePHP\NFSeNacional\Exception\ApiException;
use Psr\Log\LoggerInterface;

class RestCurl extends SoapBase
{
    /** @var string Última URL requisitada */
    public string $lastRequest = '';

    /** @var string Último corpo de resposta HTTP */
    public string $lastResponse = '';

    /** @var int Último código HTTP recebido */
    public int $lastHttpCode = 0;

    /**
     * @param Certificate          $certificate Certificado digital ICP-Brasil
     * @param LoggerInterface|null $logger      PSR-3 logger opcional
     */
    public function __construct(Certificate $certificate, LoggerInterface $logger = null)
    {
        // SoapBase já chama saveTemporarilyKeyFiles(), que grava os arquivos PEM
        // em $this->tempdir . $this->certfile e $this->tempdir . $this->prifile
        parent::__construct($certificate, $logger);
    }

    // -------------------------------------------------------------------------
    // Métodos REST públicos
    // -------------------------------------------------------------------------

    /**
     * Executa GET autenticado com mTLS.
     *
     * @param  string   $url     URL completa
     * @param  string[] $headers Headers adicionais
     * @param  int      $timeout Timeout em segundos
     * @return string   Corpo bruto da resposta
     * @throws ApiException
     */
    public function get(string $url, array $headers = [], int $timeout = 30): string
    {
        return $this->execute('GET', $url, null, $headers, $timeout);
    }

    /**
     * Executa POST autenticado com mTLS.
     *
     * @param  string      $url     URL completa
     * @param  string|null $body    Corpo JSON da requisição
     * @param  string[]    $headers Headers adicionais
     * @param  int         $timeout Timeout em segundos
     * @return string      Corpo bruto da resposta
     * @throws ApiException
     */
    public function post(string $url, ?string $body = null, array $headers = [], int $timeout = 30): string
    {
        return $this->execute('POST', $url, $body, $headers, $timeout);
    }

    // -------------------------------------------------------------------------
    // Motor cURL interno
    // -------------------------------------------------------------------------

    /**
     * Executa a requisição cURL com configuração mTLS completa.
     *
     * Usa os arquivos PEM temporários gerenciados pelo SoapBase:
     *   $this->tempdir . $this->certfile  → certificado do cliente (cert + chain)
     *   $this->tempdir . $this->prifile   → chave privada do cliente
     *
     * @throws ApiException
     */
    private function execute(
        string $method,
        string $url,
        ?string $body,
        array $headers,
        int $timeout = 30
    ): string {
        $this->lastRequest = $url;

        $defaultHeaders = [
            'Content-Type: text/json',
        ];

        $oCurl = curl_init();

        curl_setopt($oCurl, CURLOPT_URL, $url);
        curl_setopt($oCurl, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, true);
         curl_setopt($oCurl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($oCurl, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($oCurl, CURLOPT_HEADER, 0);
        curl_setopt($oCurl, CURLOPT_HTTPHEADER, array_merge($defaultHeaders, $headers));

        // mTLS — arquivos PEM gerenciados pelo SoapBase
        curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($oCurl, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
        curl_setopt($oCurl, CURLOPT_SSLCERT, $this->tempdir . $this->certfile);
        curl_setopt($oCurl, CURLOPT_SSLKEY,  $this->tempdir . $this->prifile);

        if (!empty($this->temppass)) {
            curl_setopt($oCurl, CURLOPT_KEYPASSWD, $this->temppass);
        }

        if ($method === 'POST') {
            curl_setopt($oCurl, CURLOPT_POST,       true);
            curl_setopt($oCurl, CURLOPT_POSTFIELDS, $body ?? '');
        } else {
            curl_setopt($oCurl, CURLOPT_HTTPGET, true);
        }
        // Proxy (herdado do SoapBase)
        if (!empty($this->proxyIP)) {
            curl_setopt($oCurl, CURLOPT_PROXY,     $this->proxyIP);
            curl_setopt($oCurl, CURLOPT_PROXYPORT, $this->proxyPort);
            if (!empty($this->proxyUser)) {
                curl_setopt($oCurl, CURLOPT_PROXYUSERPWD, "{$this->proxyUser}:{$this->proxyPass}");
            }
        }

        $response  = curl_exec($oCurl);
        
        $httpCode  = (int) curl_getinfo($oCurl, CURLINFO_HTTP_CODE);

        $curlError = curl_error($oCurl);

        curl_close($oCurl);

        if ($response === false) {
            throw ApiException::apiError(0, $curlError ?: 'Erro desconhecido no cURL.');
        }

        $this->lastResponse = $response;
        $this->lastHttpCode = $httpCode;

        // $this->saveDebugFiles($method . '_' . parse_url($url, PHP_URL_PATH), $url, $response);

        if ($httpCode >= 500) {
            throw ApiException::apiError(7, (string) $httpCode);
        }

        return $response;
    }

    // -------------------------------------------------------------------------
    // Implementação do contrato SoapBase (não usada para REST)
    // -------------------------------------------------------------------------

    /**
     * Não utilizado — RestCurl não envia envelopes SOAP.
     * Implementado apenas para satisfazer o contrato abstrato de SoapBase.
     *
     * {@inheritdoc}
     */
    public function send(
        $url,
        $operation = '',
        $action = '',
        $soapver = SOAP_1_2,
        $parameters = [],
        $namespaces = [],
        $request = '',
        $soapheader = null
    ) {
        // Parâmetros exigidos pelo contrato SoapBase — não utilizados em REST
        unset($url, $operation, $action, $soapver, $parameters, $namespaces, $request, $soapheader);

        throw new \BadMethodCallException('RestCurl não suporta envio SOAP. Use get() ou post().');
    }
}
