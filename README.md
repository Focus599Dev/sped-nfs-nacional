# SPED-NFS-NACIONAL

Biblioteca PHP para comunicação com a **API NFS-e ADN Contribuinte (v1)** do portal nacional de Nota Fiscal de Serviço Eletrônica.

## Requisitos

- PHP >= 7.4
- Extensões: `ext-curl`, `ext-openssl`, `ext-json`
- Certificado digital ICP-Brasil (A1 ou A3, tipo PFX/P12) com **autenticação mútua (mTLS)**

## Instalação

```bash
composer require nfephp-org/sped-nfs-nacional
```

## Configuração

Crie um JSON de configuração com os seguintes campos:

```json
{
    "cnpj":        "12345678000195",
    "certificado": "/caminho/para/certificado.pfx",
    "senha":       "senha_do_certificado",
    "tpAmb":       1,
    "timeout":     30
}
```

| Campo        | Tipo    | Obrigatório | Descrição                                              |
|--------------|---------|-------------|--------------------------------------------------------|
| `cnpj`       | string  | sim         | CNPJ do contribuinte (14 dígitos, sem pontuação)       |
| `certificado`| string  | sim         | Caminho absoluto do `.pfx` ou conteúdo em base64       |
| `senha`      | string  | sim         | Senha do certificado digital                           |
| `tpAmb`      | integer | sim         | `1` = Produção, `2` = Homologação                      |
| `timeout`    | integer | não         | Timeout em segundos (padrão: 30)                       |

## Uso

### Consultar DFe por NSU

```php
use NFePHP\NFSeNacional\Tools;

$configJson = file_get_contents('config.json');
$tools = new Tools($configJson);

// Consultar documento pelo NSU
$resposta = $tools->getDFePorNSU(123456);

echo $resposta->StatusProcessamento; // "DOCUMENTOS_LOCALIZADOS"

foreach ($resposta->LoteDFe as $dfe) {
    echo $dfe->NSU . PHP_EOL;
    echo $dfe->ChaveAcesso . PHP_EOL;
    echo $dfe->TipoDocumento . PHP_EOL;  // "NENHUM", etc.
    echo $dfe->TipoEvento . PHP_EOL;     // "CANCELAMENTO", etc.
    // $dfe->ArquivoXml contém o XML do documento
}
```

Parâmetros opcionais:

```php
// Com filtro de CNPJ e sem agrupamento em lote
$resposta = $tools->getDFePorNSU(123456, cnpjConsulta: '12345678000195', lote: false);
```

### Consultar Eventos por Chave de Acesso

```php
$chaveAcesso = '12345678901234567890123456789012345678901234'; // 44 dígitos
$resposta = $tools->getEventosPorChaveAcesso($chaveAcesso);
```

## Estrutura da Resposta (GET /DFe/{NSU})

```json
{
    "StatusProcessamento": "DOCUMENTOS_LOCALIZADOS",
    "LoteDFe": [
        {
            "NSU": 123456,
            "ChaveAcesso": "string",
            "TipoDocumento": "NENHUM",
            "TipoEvento": "CANCELAMENTO",
            "ArquivoXml": "string (XML base64 ou raw)",
            "DataHoraGeracao": "2019-08-24T14:15:22Z"
        }
    ],
    "Alertas": [],
    "Erros": [],
    "TipoAmbiente": "PRODUCAO",
    "VersaoAplicativo": "string",
    "DataHoraProcessamento": "2019-08-24T14:15:22Z"
}
```

`StatusProcessamento` pode ser:
- `DOCUMENTOS_LOCALIZADOS`
- `NENHUM_DOCUMENTO_LOCALIZADO`
- `REJEICAO`

## Referências

- [Documentação oficial da API ADN Contribuinte](https://adn.producaorestrita.nfse.gov.br/contribuintes/docs/index.html)
- [Padrões técnicos: TLS 1.2+, mTLS, JSON, XML 1.0, UTF-8, XMLDSIG, GZip base64]

## Licença

LGPL-3.0-or-later / GPL-3.0-or-later / MIT
