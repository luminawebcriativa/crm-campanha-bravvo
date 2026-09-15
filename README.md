# Bravvo Barbearia · Landing + CRM da campanha Meta (PHP, Hostinger)

Fluxo: **anúncio → `/` (formulário) → `/obrigado` (evento Lead) → lead no CRM (`/crm`) → funil até Ganho ou Perdido.**

Feito para rodar na hospedagem compartilhada da Hostinger (plano Business): só PHP 8+. Sem banco de dados: os leads ficam em `data/leads.json`, protegido contra acesso direto.

## Estrutura

```
public_html/            ← conteúdo que vai para o public_html da Hostinger
  index.php             landing (renderiza templates/index.html)
  obrigado.php          página de obrigado (evento Lead do Pixel)
  crm.php               kanban, protegido por login
  api/leads.php         POST cria lead (público) · GET lista (auth)
  api/lead.php          GET / PATCH / DELETE de um lead (auth)
  api/stats.php         números do funil (auth)
  api/config.php        etapas do funil (auth)
  templates/            HTML das páginas
  img/                  logos
  data/leads.json       os leads (criado sozinho no primeiro envio)
  .htaccess             rotas amigáveis e bloqueio de arquivos internos
  config.example.php    → copie para config.php e preencha
crm.js                  CLI para operar o funil pela API
```

## Publicar na Hostinger (passo a passo)

1. **Arquivos**: crie a pasta `public_html/agendar` no domínio bravvobarbearia.com.br e envie para ela tudo que está dentro de `public_html/` deste projeto (Gerenciador de arquivos ou FTP). O `.htaccess` e a pasta `data/` precisam ir junto. Todos os caminhos são relativos, então qualquer nome de pasta funciona.
2. **Config**: no servidor, copie `config.example.php` para `config.php` e preencha senha do CRM, token da API, ID do Pixel e WhatsApp.
3. **PHP**: hPanel → Configuração PHP → versão 8.1 ou superior (a extensão `curl` já vem ligada).
4. **HTTPS**: ative o SSL gratuito no hPanel e force HTTPS. O Pixel e a verificação de domínio da Meta exigem.
5. Teste: `https://bravvobarbearia.com.br/agendar/` deve abrir a landing e `https://bravvobarbearia.com.br/agendar/crm` deve pedir login. Envie um lead de teste e confira se ele aparece no CRM.

Backup: o arquivo `public_html/data/leads.json` é tudo. Baixe ele de vez em quando.

## Etapas do funil

`novo` → `contato` → `agendado` → `compareceu` → `ganho` | `perdido`

No CRM: arraste os cards entre colunas ou abra a ficha. Ganho pede o valor do atendimento, Perdido pede o motivo. Tudo fica no histórico.

## Operar pelo terminal (ou por automações)

Crie um `.env` na raiz do projeto:

```
CRM_URL=https://bravvobarbearia.com.br/agendar
API_TOKEN=o-mesmo-token-do-config.php
```

```bash
node crm.js list              # todos os leads
node crm.js list novo         # só uma etapa
node crm.js show <id>
node crm.js move <id> contato
node crm.js move <id> ganho --valor 85
node crm.js move <id> perdido --motivo "Não respondeu"
node crm.js note <id> "Pediu sábado 10h"
node crm.js stats
```

## API

| Método | Rota | Auth | Descrição |
|---|---|---|---|
| POST | `/api/leads` | pública | cria lead (JSON ou form). Honeypot `website`, limite 10/min por IP, dedupe por telefone em 10 min |
| GET | `/api/leads` | sim | lista todos com histórico |
| GET | `/api/leads/{id}` | sim | um lead |
| PATCH ou POST | `/api/leads/{id}` | sim | `{stage, lost_reason, valor, note, nome, whatsapp, servico, horario}` |
| DELETE | `/api/leads/{id}` | sim | remove |
| GET | `/api/stats` | sim | contagem por etapa, conversão, receita |

Auth: `Authorization: Bearer <api_token>` ou Basic (usuário/senha do CRM).

## Meta

1. Crie o Pixel no Gerenciador de Eventos e coloque o ID em `meta_pixel_id`.
2. No anúncio, use a URL da landing com UTMs:
   `https://bravvobarbearia.com.br/agendar/?utm_source=meta&utm_medium=cpc&utm_campaign=bravvo-set&utm_content={{ad.name}}`
3. Objetivo da campanha: **Leads → site → evento Lead** (dispara na página de obrigado).
4. Opcional, recomendado: gere um token da **Conversions API** e preencha `meta_capi_token`. O servidor envia o mesmo evento Lead com o mesmo `event_id`, a Meta deduplica e você mantém atribuição mesmo com iOS e bloqueadores. Use `meta_test_event_code` para validar em "Testar eventos".
