<?php

namespace NFePHP\NFSeNacional;

/**
 * Ponto de entrada para comunicação com a API NFS-e Nacional ADN (v1).
 *
 * Endpoints implementados:
 *   GET /DFe/{NSU}                  – Documento Fiscal pelo NSU
 *   GET /DFe/{chaveAcesso}/Eventos  – Eventos vinculados à chave de acesso
 *
 * @category  NFePHP
 * @package   NFePHP\NFSeNacional\Tools
 * @copyright Copyright (c) 2024
 * @license   http://www.gnu.org/licenses/lgpl.txt LGPLv3+
 * @license   https://opensource.org/licenses/MIT MIT
 * @license   http://www.gnu.org/licenses/gpl.txt GPLv3+
 * @author    Marlon O. Barbosa <marlon.barbosa@focusit.com.br>
 * @link      https://github.com/Focus599Dev/sped-nfs-nacional
 */

use NFePHP\NFSeNacional\Common\Tools as CommonTools;
use NFePHP\NFSeNacional\Exception\ApiException;
use stdClass;

class Tools extends CommonTools
{
    /**
     * GET /DFe/{NSU}
     *
     * Retorna o Documento Fiscal de Serviço correspondente ao NSU informado.
     *
     * @param  int         $nsu          NSU (int64) a consultar
     * @param  string|null $cnpjConsulta Filtro por CNPJ (14 dígitos, opcional)
     * @param  bool        $lote         Retornar em lote (padrão: true)
     * @return stdClass    Resposta decodificada da API
     * @throws ApiException
     */
    public function getDFePorNSU(int $nsu, ?string $cnpjConsulta = null, bool $lote = true): stdClass
    {
        if ($nsu < 0) {
            throw ApiException::apiError(3, '');
        }

        $path = str_replace('{nsu}', (string) $nsu, $this->getEndpoint('dfePorNSU', 'distribuicao'));

        $query = [];

        if (!$lote) {
            $query['lote'] = 'false';
        } else {
            $query['lote'] = 'true';
        }

        if ($cnpjConsulta !== null) {
            if (!preg_match('/^\d{14}$/', $cnpjConsulta)) {
                throw ApiException::apiError(5, 'cnpjConsulta deve conter 14 dígitos numéricos.');
            }

            $query['cnpjConsulta'] = $cnpjConsulta;
        }

        return $this->sendRequest('GET', $path, $query);
    }

    /**
     * GET /danfse/{chave}
     *
     * Retorna o PDF da DANFSe (Documento Auxiliar da NFS-e Nacional) para a
     * chave de acesso informada.
     *
     * @param  string $chave Chave de acesso da NFS-e (44 dígitos)
     * @return string        Conteúdo binário do PDF
     * @throws ApiException
     */
    public function getDanfe(string $chave): string
    {
        if (!preg_match('/^\d{50}$/', $chave)) {
            throw ApiException::apiError(5, 'A chave de acesso deve conter exatamente 50 dígitos numéricos.');
        }

        $path = str_replace('{chave}', $chave, $this->getEndpoint('danfe', 'danfse'));

        return $this->sendRawRequest($path, [], ['Accept: application/pdf']);
    }


}
