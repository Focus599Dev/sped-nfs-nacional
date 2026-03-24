<?php

namespace NFePHP\NFSeNacional\Common;

/**
 * Classe base para comunicação com a API NFS-e Nacional ADN.
 *
 * Responsabilidades:
 *   - Carregar e validar a configuração (config.json)
 *   - Resolver endpoints por ambiente (produção/homologação)
 *   - Expor get() / post() que constroem a URL completa e retornam stdClass
 *
 * O transporte HTTP + mTLS é delegado ao RestCurl (via herança).
 *
 * @category  NFePHP
 * @package   NFePHP\NFSeNacional\Common\Tools
 * @copyright Copyright (c) 2024
 * @license   http://www.gnu.org/licenses/lgpl.txt LGPLv3+
 * @license   https://opensource.org/licenses/MIT MIT
 * @license   http://www.gnu.org/licenses/gpl.txt GPLv3+
 * @author    Marlon O. Barbosa <marlon.barbosa@focusit.com.br>
 */

use NFePHP\Common\Certificate;
use NFePHP\NFSeNacional\Exception\ApiException;
use Psr\Log\LoggerInterface;
use stdClass;

class Tools
{
    /** @var stdClass Configuração validada */
    protected stdClass $config;

    /** @var string 'producao' | 'homologacao' */
    protected string $ambiente;

    /** @var stdClass Endpoints carregados do JSON */
    protected stdClass $endpoints;

    private RestCurl $restCurl;

    /**
     * @param string               $configJson  JSON com cnpj, tpAmb [, timeout]
     * @param Certificate          $certificate Certificado digital ICP-Brasil
     * @param LoggerInterface|null $logger      PSR-3 logger opcional
     * @throws ApiException
     */
    public function __construct(
        string $configJson,
        Certificate $certificate,
        LoggerInterface $logger = null
    ) {
        $this->config    = Config::validate($configJson);
        $this->ambiente  = $this->config->tpAmb === 1 ? 'producao' : 'homologacao';
        $this->endpoints = $this->loadEndpoints();

        $this->restCurl = new RestCurl($certificate, $logger);
    }

    // -------------------------------------------------------------------------
    // Métodos HTTP com construção de URL
    // -------------------------------------------------------------------------

    /**
     * Ponto central de despacho HTTP.
     *
     * Constrói a URL completa, roteia para o método correto do RestCurl
     * conforme o verbo e retorna a resposta decodificada como stdClass.
     *
     * @param  string      $method  Verbo HTTP: 'GET' | 'POST'
     * @param  string      $path    Path relativo (ex.: /DFe/123)
     * @param  array       $query   Parâmetros de query string (apenas GET)
     * @param  string|null $body    Corpo JSON (apenas POST)
     * @param  string[]    $headers Headers adicionais
     * @return stdClass    Resposta decodificada
     * @throws ApiException
     */
    /**
     * Ponto central de despacho HTTP.
     *
     * Constrói a URL completa, roteia para o método correto do RestCurl
     * conforme o verbo e retorna a resposta decodificada como stdClass.
     *
     * @param  string      $method  Verbo HTTP: 'GET' | 'POST'
     * @param  string      $path    Path relativo (ex.: /DFe/123)
     * @param  array       $query   Parâmetros de query string (apenas GET)
     * @param  string|null $body    Corpo JSON (apenas POST)
     * @param  string[]    $headers Headers adicionais
     * @return stdClass    Resposta decodificada
     * @throws ApiException
     */
    public function sendRequest(
        string $method,
        string $path,
        array $query = [],
        ?string $body = null,
        array $headers = []
    ): stdClass {
        $timeout = (int) ($this->config->timeout ?? 30);
        $url     = $this->buildUrl($path, strtoupper($method) === 'GET' ? $query : []);

        $verb = strtoupper($method);

        if ($verb === 'GET') {
            $raw = $this->restCurl->get($url, $headers, $timeout);
        } elseif ($verb === 'POST') {
            $raw = $this->restCurl->post($url, $body, $headers, $timeout);
        } else {
            throw ApiException::apiError(0, "Verbo HTTP '{$method}' não suportado.");
        }

        if ($raw) {
            $raw = $this->decodeResponse($raw);
        }

        return $raw;
    }


    /**
     * Despacha uma requisição GET e retorna o corpo bruto (binário) sem decodificação JSON.
     * Útil para endpoints que retornam PDF ou outros formatos não-JSON.
     *
     * @param  string   $path    Path relativo (ex.: /danfse/abc123)
     * @param  array    $query   Parâmetros de query string
     * @param  string[] $headers Headers adicionais
     * @return string   Corpo bruto da resposta
     * @throws ApiException
     */
    public function sendRawRequest(string $path, array $query = [], array $headers = []): string
    {
        $timeout = (int) ($this->config->timeout ?? 30);
        $url     = $this->buildUrl($path, $query);

        return $this->restCurl->get($url, $headers, $timeout);
    }

    // -------------------------------------------------------------------------
    // Gerenciamento de endpoints
    // -------------------------------------------------------------------------

    /**
     * Retorna o path template de um endpoint pelo tipo e chave.
     *
     * @param  string $key  Chave do endpoint (ex.: 'dfePorNSU')
     * @param  string $type Grupo do endpoint (ex.: 'distribuicao')
     * @return string Path template
     * @throws ApiException
     */
    protected function getEndpoint(string $key, string $type = 'distribuicao'): string
    {
        if (empty($type)) {
            throw new \InvalidArgumentException('Tipo de endpoint não informado.');
        }

        $path = $this->endpoints->{$this->ambiente}->{$type}->$key ?? null;

        if ($path === null) {
            throw ApiException::apiError(
                0,
                "Endpoint '{$key}' não encontrado para o ambiente '{$this->ambiente}'."
            );
        }

        return $path;
    }

    // -------------------------------------------------------------------------
    // Helpers internos
    // -------------------------------------------------------------------------

    /**
     * Monta a URL completa a partir do path e query string.
     */
    private function buildUrl(string $path, array $query = []): string
    {
        $baseUrl = $this->endpoints->{$this->ambiente}->baseUrl;
        $url     = $baseUrl . $path;

        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        return $url;
    }

    /**
     * Decodifica o corpo JSON da resposta em stdClass.
     *
     * @throws ApiException
     */
    private function decodeResponse(string $raw): stdClass
    {
        $decoded = json_decode($raw);

        if (!is_object($decoded) && !is_array($decoded)) {
            throw ApiException::apiError(6, 'Resposta não é um JSON válido.');
        }

        return (object) $decoded;
    }

    /**
     * Carrega o arquivo de endpoints.
     *
     * @throws ApiException
     */
    protected function loadEndpoints(): stdClass
    {
        $file = __DIR__ . '/../../storage/endpoints.json';

        if (!file_exists($file)) {
            throw ApiException::apiError(0, 'Arquivo de endpoints não encontrado.');
        }

        $endpoints = json_decode((string) file_get_contents($file));

        if (!is_object($endpoints)) {
            throw ApiException::apiError(0, 'Arquivo de endpoints inválido.');
        }

        return $endpoints;
    }
}
