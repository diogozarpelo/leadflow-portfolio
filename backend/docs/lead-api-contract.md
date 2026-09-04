# Contrato da API de Leads — LeadFlow Industrial

> **Status do documento:** rascunho técnico em evolução.
>
> **Última atualização:** 04/09/2026.
>
> **Integração 3C:** especificação externa ainda pendente de confirmação técnica e homologação.

## Situação

Este documento descreve o contrato atual do endpoint de leads da LeadFlow Industrial.

A API recebe, valida e armazena os leads com status inicial `pending`.

Em desenvolvimento local, as três origens de lead do front-end — formulário de contato, solicitação de amostra e modal do WhatsApp — enviam dados para esta API.

A conexão utiliza o endpoint `POST /api/leads` durante o desenvolvimento local. Na versão pública demonstrativa, os formulários simulam o envio com sucesso no navegador, sem transmitir dados para serviços externos.

O encaminhamento para a 3C ainda não está implementado e dependerá da definição do serviço externo, autenticação, campos e tratamento de respostas.

## Endpoint

```http
POST /api/leads
Content-Type: application/json
Accept: application/json
```

A API é utilizada localmente em `http://127.0.0.1:8000`. A versão pública demonstrativa não expõe uma API de produção.

## Tipos de lead

O campo `type` aceita:

| Valor | Origem |
|---|---|
| `contact` | Formulário principal de contato |
| `sample_request` | Solicitação de amostra |
| `whatsapp` | Modal de atendimento pelo WhatsApp |

## Campos comuns

Estes campos são obrigatórios em todos os tipos:

| Campo | Tipo | Limite | Descrição |
|---|---|---:|---|
| `type` | string | valores definidos | Tipo do lead |
| `name` | string | 150 | Nome da pessoa |
| `email` | string | 254 | E-mail válido |
| `company` | string | 180 | Empresa |
| `phone` | string | 50 | Telefone ou WhatsApp |
| `language` | string | valores definidos | Idioma do site |
| `source_page` | string | valores definidos | Página de origem |

O campo `language` aceita:

```text
pt, en, es, zh, ar
```

O campo `source_page` aceita:

```text
home, blog
```

## Campos condicionais

| Campo | Contato | Amostra | WhatsApp | Limite |
|---|---|---|---|---:|
| `company_registration` | opcional | obrigatório | obrigatório | 80 |
| `sector` | obrigatório | opcional | opcional | 100 |
| `location` | opcional | obrigatório | opcional | 180 |
| `quantity` | opcional | opcional | obrigatório | 100 |
| `message` | obrigatório | obrigatório | opcional | 5000 |

Campos opcionais podem ser omitidos ou enviados como `null`.

## Honeypot

O campo técnico `website` é utilizado como proteção contra robôs.

Ele deve:

- não ser enviado; ou
- ser enviado vazio.

Se estiver preenchido, a API rejeitará a solicitação.

Esse campo não possui relação com o endereço do site da LeadFlow Industrial. Seu nome genérico é proposital, pois muitos robôs tentam preenchê-lo automaticamente.

## Exemplo — contato

```json
{
  "type": "contact",
  "name": "Cliente Exemplo",
  "email": "cliente@example.com",
  "company": "Empresa Exemplo",
  "company_registration": null,
  "phone": "+55 19 99999-0000",
  "sector": "Higiene e Limpeza",
  "message": "Gostaria de receber informações comerciais.",
  "language": "pt",
  "source_page": "home",
  "website": ""
}
```

## Exemplo — solicitação de amostra

```json
{
  "type": "sample_request",
  "name": "Cliente Exemplo",
  "email": "cliente@example.com",
  "company": "Empresa Exemplo",
  "company_registration": "12.345.678/0001-90",
  "phone": "+55 19 99999-0000",
  "location": "São Paulo / SP / Brasil",
  "message": "Solicito uma amostra técnica de SLES 70%.",
  "language": "pt",
  "source_page": "home",
  "website": ""
}
```

## Exemplo — WhatsApp

```json
{
  "type": "whatsapp",
  "name": "Cliente Exemplo",
  "email": "cliente@example.com",
  "company": "Empresa Exemplo",
  "company_registration": "12.345.678/0001-90",
  "phone": "+55 19 99999-0000",
  "quantity": "1 tonelada",
  "language": "pt",
  "source_page": "home",
  "website": ""
}
```

## Processamento atual

Quando a solicitação é válida, a API:

1. valida os campos de acordo com o tipo do lead;
2. remove do processamento os campos não autorizados;
3. cria um registro na tabela `leads`;
4. define o status inicial como `pending`;
5. devolve o identificador interno e o status.

O cliente não pode definir o status do lead.

## Resposta de sucesso

Status HTTP:

```http
201 Created
```

Corpo:

```json
{
  "message": "Lead received successfully.",
  "lead": {
    "id": 1,
    "status": "pending"
  }
}
```

## Erro de validação

Status HTTP:

```http
422 Unprocessable Content
```

Exemplo:

```json
{
  "message": "The email field must be a valid email address.",
  "errors": {
    "email": [
      "The email field must be a valid email address."
    ]
  }
}
```

Os campos presentes em `errors` dependem dos dados inválidos enviados.

## Limite de requisições

A API permite atualmente até cinco solicitações por minuto para o mesmo endereço IP.

Quando o limite é excedido, a API responde:

```http
429 Too Many Requests
```

Nenhum lead adicional é armazenado depois que o limite é atingido.

## CORS

Origens autorizadas atualmente:

```text
https://leadflow.example
https://www.leadflow.example
http://127.0.0.1:5500
http://localhost:5500
```

As duas primeiras origens correspondem à produção.

As duas últimas são utilizadas durante o desenvolvimento local.

Origens desconhecidas não recebem o cabeçalho de autorização CORS.

## Persistência

Os leads são armazenados na tabela `leads`.

Status inicial:

```text
pending
```

Bancos utilizados:

- desenvolvimento local: SQLite;
- testes automatizados: SQLite temporário em memória;
- produção planejada: MySQL 8.0.32-cll-lve;
- host MySQL informado pela hospedagem: `localhost`;
- porta MySQL informada pela hospedagem: `3306`.

## Identificador externo

Em 25/08/2026, foi implementada a fundação interna para armazenar o identificador externo fornecido futuramente pela 3C.

Estrutura atual:

- coluna `external_id` do tipo string, com limite de 191 caracteres;
- campo opcional, permitindo valor `null`;
- índice dedicado para consultas e correlação interna;
- atribuição controlada pelo método interno `assignExternalId()`;
- consulta segura pelo método interno `resolveByExternalId()`, exigindo exatamente um resultado;
- campo mantido fora do preenchimento em massa;
- campo não aceito como entrada pública dos formulários;
- nenhuma restrição de unicidade aplicada até a confirmação da regra da 3C.

Um lead é criado inicialmente sem identificador externo. O valor somente poderá ser associado posteriormente por uma operação interna confiável.

A consulta interna falha explicitamente quando o identificador não existe ou quando mais de um lead possui o mesmo valor, evitando associações silenciosas ou incorretas.

Esta implementação não ativa webhook, callback, envio CSV ou comunicação externa. Esses componentes dependerão da especificação técnica e da homologação com a 3C.

## Segurança atual

A API possui:

- validação específica para cada tipo de lead;
- campos com tamanhos limitados;
- CORS restrito;
- limite de requisições por endereço IP;
- honeypot contra preenchimento automático;
- controle interno do status;
- proteção contra atribuição direta de campos não permitidos;
- testes automatizados de comportamento e persistência.

Toda comunicação de produção deverá utilizar HTTPS.

Credenciais, chaves e senhas não fazem parte deste contrato e nunca devem ser registradas no repositório.

## Situação da hospedagem atual

A hospedagem compartilhada confirmou:

- PHP 8.3 disponível;
- extensão `pdo_mysql` habilitada;
- MySQL 8.0.32-cll-lve;
- MySQL no host `localhost`;
- MySQL na porta `3306`;
- criação de banco e usuário pelo painel;
- Apache com `mod_rewrite`;
- ausência de Composer no servidor;
- ausência de terminal e SSH;
- impossibilidade de alterar o diretório público da aplicação.

O desenvolvimento continuará localmente.

A estratégia definitiva de hospedagem do back-end será decidida depois da definição da integração com a 3C.

## Definições pendentes da 3C

Antes da integração, será necessário confirmar:

- endereço da API ou webhook da 3C;
- método HTTP esperado;
- forma de autenticação;
- campos obrigatórios;
- nomes esperados para cada campo;
- formato do corpo da requisição;
- formato das respostas;
- resposta que representa sucesso;
- respostas que representam erro;
- limites de requisição;
- tempo máximo de resposta;
- política de novas tentativas;
- tratamento de indisponibilidade;
- confirmação de recebimento;
- regras para os status `sent` e `failed`;
- formato e tamanho do `External ID` fornecido pela 3C;
- regra de unicidade e possibilidade de substituição do identificador;
- momento e estrutura em que o identificador será devolvido;
- necessidade de processamento em fila;
- política de privacidade;
- política de retenção dos dados.
