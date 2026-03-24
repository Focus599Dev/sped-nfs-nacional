<?php

namespace NFePHP\NFSeNacional\Common;

/**
 * Validates the library configuration JSON
 *
 * @category  NFePHP
 * @package   NFePHP\NFSeNacional\Common\Config
 * @copyright Copyright (c) 2024
 * @license   http://www.gnu.org/licenses/lgpl.txt LGPLv3+
 * @license   https://opensource.org/licenses/MIT MIT
 * @license   http://www.gnu.org/licenses/gpl.txt GPLv3+
 * @author    Marlon O. Barbosa <marlon.barbosa@focusit.com.br>
*/

use NFePHP\NFSeNacional\Exception\ApiException;
use stdClass;

class Config
{
    /**
     * Validates and returns the decoded config object.
     *
     * @param  string $content JSON string
     * @return stdClass
     * @throws ApiException
     */
    public static function validate(string $content): stdClass
    {
        $std = json_decode($content);

        if (!is_object($std)) {
            throw ApiException::apiError(1, 'O conteúdo passado não é um JSON válido.');
        }

        self::validInputData($std);

        return $std;
    }

    /**
     * Validates the config object against the JSON schema.
     *
     * @param  stdClass $data
     * @return bool
     * @throws ApiException
     */
    protected static function validInputData(stdClass $data): bool
    {
        $schemaFile = __DIR__ . '/../../storage/config.schema';

        if (!file_exists($schemaFile)) {
            throw ApiException::apiError(1, 'Arquivo de schema de configuração não encontrado.');
        }

        $schema   = json_decode(file_get_contents($schemaFile));
        $required = $schema->required ?? [];

        foreach ($required as $field) {
            if (!isset($data->$field)) {
                throw ApiException::apiError(1, "Campo obrigatório ausente: {$field}");
            }
        }

        if (isset($data->cnpj) && !preg_match('/^\d{14}$/', $data->cnpj)) {
            throw ApiException::apiError(5, 'O CNPJ deve conter exatamente 14 dígitos numéricos.');
        }

        if (isset($data->tpAmb) && !in_array((int) $data->tpAmb, [1, 2], true)) {
            throw ApiException::apiError(9, '');
        }

        return true;
    }
}
