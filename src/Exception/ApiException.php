<?php

namespace NFePHP\NFSeNacional\Exception;

/**
 * @category   NFePHP
 * @package    NFePHP\NFSeNacional\Exception
 * @copyright  Copyright (c) 2024
 * @license    http://www.gnu.org/licenses/lesser.html LGPL v3
 * @author     Marlon O. Barbosa <marlon.barbosa@focusit.com.br>
 */
class ApiException extends \RuntimeException implements ExceptionInterface
{
    public static $list = [
        0  => "Erro de comunicação com a API NFS-e: {{msg}}",
        1  => "A configuração (config.json) não é válida: {{msg}}",
        2  => "O certificado digital não foi encontrado ou é inválido: {{msg}}",
        3  => "NSU inválido: deve ser um inteiro maior ou igual a zero.",
        4  => "Chave de acesso inválida: {{msg}}",
        5  => "CNPJ inválido: {{msg}}",
        6  => "Resposta inesperada da API: {{msg}}",
        7  => "Erro HTTP {{msg}} retornado pela API NFS-e.",
        8  => "Não foi possível processar o certificado PFX: {{msg}}",
        9  => "Ambiente inválido: deve ser 1 (produção) ou 2 (homologação).",
    ];

    public static function apiError(int $code, string $msg = ''): self
    {
        $template = self::$list[$code] ?? "Erro desconhecido: {{msg}}";
        $message  = str_replace('{{msg}}', $msg, $template);
        return new self($message, $code);
    }
}
