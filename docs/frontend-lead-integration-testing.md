# Checklist de integração dos formulários com a API

## Situação

Este documento registra o procedimento de validação da integração entre o frontend da LeadFlow Industrial e a API Laravel de leads.

A integração com a API está habilitada somente no ambiente local. Na versão pública demonstrativa, os três formulários simulam sucesso no navegador, sem transmitir dados nem abrir serviços externos.

Nenhum dado é enviado atualmente à 3C, à 3C Plus ou à Kommo.

## Pré-requisitos

- PHP e dependências do backend instalados;
- migrations executadas;
- banco SQLite local disponível;
- Live Server instalado no VS Code;
- porta `8000` livre para o Laravel;
- porta `5500` livre para o frontend.

## Iniciar o backend

Dentro da pasta `backend`, execute:

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

Endereço esperado:

```text
http://127.0.0.1:8000
```

## Iniciar o frontend

Abra o arquivo `index.html` utilizando o Live Server.

Endereço esperado:

```text
http://127.0.0.1:5500/index.html
```

O frontend não deve ser aberto diretamente por um endereço `file:///`.

## Configuração do Live Server

O arquivo `.vscode/settings.json` impede que mudanças no SQLite, nos logs e no cache do Laravel recarreguem a página:

```json
{
  "liveServer.settings.ignoreFiles": [
    "**/backend/database/**",
    "**/backend/storage/**",
    "**/backend/bootstrap/cache/**"
  ]
}
```

## Fluxos integrados

| Origem | Tipo enviado à API | Comportamento local |
|---|---|---|
| Formulário principal | `contact` | Armazena o lead e exibe confirmação |
| Solicitação de amostra | `sample_request` | Armazena o lead e mantém o modal aberto |
| Modal do WhatsApp | `whatsapp` | Armazena o lead, exibe confirmação local e fecha o modal |

Todos os leads devem ser criados com status inicial `pending`.

## Requisições esperadas

Durante um envio local, o servidor pode registrar duas requisições para `/api/leads`:

```text
OPTIONS /api/leads
POST /api/leads
```

A requisição `OPTIONS` corresponde à verificação CORS realizada pelo navegador.

Somente a requisição `POST` cria o lead. As duas linhas não representam envio duplicado.

## Verificar o último lead

Dentro da pasta `backend`, execute:

```powershell
php artisan tinker --execute="dump(\App\Models\Lead::query()->latest('id')->firstOrFail()->only(['id', 'type', 'status', 'language', 'source_page']));"
```

O registro deve apresentar:

- tipo correspondente ao formulário;
- status `pending`;
- idioma selecionado no site;
- origem `home`.

## Validação dos idiomas

Em 31/08/2026, foram validados os cinco idiomas disponíveis:

| Idioma | Código | Fluxos validados |
|---|---|---|
| Português | `pt` | Contato, amostra e WhatsApp |
| Inglês | `en` | Contato |
| Espanhol | `es` | Solicitação de amostra |
| Chinês | `zh` | Contato |
| Árabe | `ar` | Solicitação de amostra e WhatsApp |

Também foram confirmados:

- alteração correta dos rótulos e mensagens;
- persistência do código do idioma;
- layout RTL no idioma árabe;
- limpeza dos campos após sucesso;
- permanência do modal de amostra após o envio;
- confirmação do registro local sem abertura de conversa externa.

## Teste de indisponibilidade

Para simular uma indisponibilidade, interrompa o Laravel com:

```text
Ctrl + C
```

Ao tentar enviar um formulário, o comportamento esperado é:

- nenhuma confirmação de sucesso;
- exibição de aviso ao usuário;
- preservação dos campos preenchidos;
- reativação do botão;
- nenhum lead criado.

O Console do navegador pode apresentar:

```text
ERR_CONNECTION_REFUSED
TypeError: Failed to fetch
```

Essas mensagens são esperadas quando o backend local está desligado.

Depois de reiniciar o Laravel, o mesmo formulário deve poder ser enviado normalmente.

## Validações automatizadas

Execute a suíte completa:

```powershell
php artisan test
```

Linha de base validada em 10/09/2026:

```text
87 testes aprovados
426 asserções aprovadas
```

Verifique também a formatação do backend:

```powershell
php .\vendor\bin\pint --test
```

Linha de base:

```text
73 arquivos aprovados
```

Para verificar a sintaxe do JavaScript:

```powershell
node --check ..\js\app.js
```

Nenhuma saída representa sintaxe válida.

## Limitações atuais

- backend ainda não publicado;
- integração ativa somente no ambiente local;
- formulários públicos operando em modo demonstrativo, sem transmissão externa;
- driver externo configurado como `null`;
- nenhuma transmissão para a 3C ou para a 3C Plus;
- nenhuma integração ativa com a Kommo;
- nenhuma recepção de callback externo;
- autenticação e contrato técnico da 3C ainda pendentes.

## Critério de aprovação

A integração local é considerada aprovada quando:

1. os três tipos de lead são armazenados;
2. os idiomas são preservados;
3. os registros iniciam como `pending`;
4. não existem envios duplicados;
5. o usuário recebe confirmação ou erro adequado;
6. o WhatsApp registra o lead localmente sem abrir conversa externa;
7. o repositório permanece limpo após os testes.
