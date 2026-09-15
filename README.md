# Bravvo Barbearia · Landing + CRM da campanha Meta (PHP, Hostinger)

Fluxo: **anúncio → `/` (formulário) → `/obrigado` (evento Lead) → lead no CRM (`/crm`) → funil até Ganho ou Perdido.**

Feito para rodar na hospedagem compartilhada da Hostinger (plano Business): só PHP 8+. Sem banco de dados: os leads ficam em `data/leads.json`, protegido contra acesso direto.

## Estrutura

```
index.php             landing (renderiza templates/index.html)
obrigado.php          página de obrigado (evento Lead do Pixel)
crm.php               kanban, protegido por login
api/                  leads.php (cria/lista), lead.php (move/edita/exclui), stats.php, funil.php
templates/            HTML das páginas
img/                  logos
data/leads.json       os leads (criado sozinho no primeiro envio, fora do git)
.htaccess             rotas amigáveis e bloqueio de arquivos internos
config.example.php    → copie para config.php e preencha (fora do git)
crm.js                CLI para operar o funil pela API (não é servido)
dev-server.php        servidor local de testes (não é servido)
```

## Publicar na Hostinger via Git (passo a passo)

1. **Conectar o repositório**: hPanel → Avançado → Git → Criar novo repositório.
   Repositório: `https://github.com/luminawebcriativa/crm-campanha-bravvo.git`, branch `main`, diretório `public_html/agendar`.
   Se o repositório for privado, o hPanel mostra uma chave SSH: cadastre em GitHub → Settings → Deploy keys e use a URL SSH do repositório.
2. **Deploy**: clique em "Implantar" (Deploy). A pasta precisa estar vazia na primeira vez.
3. **Config**: no Gerenciador de Arquivos, dentro de `public_html/agendar`, copie `config.example.php` para `config.php` e preencha senha do CRM, token, ID do Pixel e WhatsApp.
4. **Atualização automática**: no mesmo painel Git, copie a URL do webhook e cadastre em GitHub → Settings → Webhooks → Add webhook (Payload URL = webhook, Content type = application/json, evento Push). A partir daí, todo push na `main` atualiza o site.
5. **HTTPS**: ative o SSL gratuito no hPanel e force HTTPS.
6. Teste: `https://bravvobarbearia.com.br/agendar/` abre a landing e `https://bravvobarbearia.com.br/agendar/crm` pede login.

O deploy só troca os arquivos do repositório. `config.php` e `data/leads.json` não estão no git, então nunca são sobrescritos. Backup: baixe `data/leads.json` de vez em quando.

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
| GET | `/api/funil` | sim | etapas e rótulos do funil |

Auth: `Authorization: Bearer <api_token>` ou Basic (usuário/senha do CRM).

## Meta

1. Crie o Pixel no Gerenciador de Eventos e coloque o ID em `meta_pixel_id`.
2. No anúncio, use a URL da landing com UTMs:
   `https://bravvobarbearia.com.br/agendar/?utm_source=meta&utm_medium=cpc&utm_campaign=bravvo-set&utm_content={{ad.name}}`
3. Objetivo da campanha: **Leads → site → evento Lead** (dispara na página de obrigado).
4. Opcional, recomendado: gere um token da **Conversions API** e preencha `meta_capi_token`. O servidor envia o mesmo evento Lead com o mesmo `event_id`, a Meta deduplica e você mantém atribuição mesmo com iOS e bloqueadores. Use `meta_test_event_code` para validar em "Testar eventos".
