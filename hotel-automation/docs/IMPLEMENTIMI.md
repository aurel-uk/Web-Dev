# Udhëzues Implementimi - Sistemi i Automatizimit të Hoteleve

## Përmbajtja

1. [Hyrje](#hyrje)
2. [Opsioni 1: Me Docker (Rekomanduar)](#opsioni-1-me-docker-rekomanduar)
3. [Opsioni 2: Pa Docker](#opsioni-2-pa-docker)
4. [Konfigurimi i Kredencialeve](#konfigurimi-i-kredencialeve)
5. [Importimi i Workflows](#importimi-i-workflows)
6. [Testimi](#testimi)
7. [Zgjidhja e Problemeve](#zgjidhja-e-problemeve)

---

## Hyrje

Ky sistem përbëhet nga:
- **4 workflows n8n** për automatizimin e komunikimit
- **Databaza PostgreSQL** për ruajtjen e të dhënave
- **Integrimi me AI** (OpenAI GPT-4)
- **Integrimi me WhatsApp, Twilio, dhe Telegram**

---

## Opsioni 1: Me Docker (Rekomanduar)

### Kërkesat

- Docker Desktop instaluar
- Docker Compose instaluar
- 4GB RAM minimum
- Port 5678 dhe 5432 të lira

### Hapat

#### Hapi 1: Hap terminalin dhe shko te dosja e projektit

```bash
cd /rruga/deri/hotel-automation
```

#### Hapi 2: Ekzekuto skriptin e setup

```bash
./setup.sh
```

Ky skript do të:
1. Krijojë skedarin `.env` me konfigurime default
2. Startojë PostgreSQL container
3. Startojë n8n container
4. Inicializojë databazën me skemën dhe të dhënat e testit

#### Hapi 3: Prit derisa sistemi të jetë gati

Shiko mesazhin:
```
✓ PostgreSQL is ready
✓ n8n is ready
```

#### Hapi 4: Hap n8n në browser

```
URL: http://localhost:5678
Username: admin
Password: HotelAdmin2024!
```

---

## Opsioni 2: Pa Docker

### Kërkesat

- Node.js 18 ose më i ri
- npm instaluar

### Hapat

#### Hapi 1: Ekzekuto skriptin quick-start

```bash
./quick-start.sh
```

Ky do të instalojë n8n dhe ta startojë me SQLite.

#### Hapi 2: Hap n8n

```
URL: http://localhost:5678
Username: admin
Password: hotel2024
```

**Shënim:** Pa Docker, databaza PostgreSQL duhet konfiguruar veçmas.

---

## Konfigurimi i Kredencialeve

### Hapi 1: Hap Settings > Credentials në n8n

### Hapi 2: Shto këto kredenciale:

#### PostgreSQL
```
Name: Hotel PostgreSQL
Host: postgres (ose localhost nëse pa Docker)
Port: 5432
Database: hotel_automation
User: hotel_admin
Password: HotelSecure2024!
```

#### OpenAI API
```
Name: OpenAI API
API Key: sk-your-actual-openai-key
```

Merr çelësin nga: https://platform.openai.com/api-keys

#### Telegram Bot
```
Name: Hotel Telegram Bot
Access Token: your-bot-token
```

Krijo bot me @BotFather në Telegram.

#### WhatsApp (HTTP Header Auth)
```
Name: WhatsApp API Auth
Header Name: Authorization
Header Value: Bearer YOUR_WHATSAPP_ACCESS_TOKEN
```

---

## Importimi i Workflows

### Mënyra 1: Automatike (me Docker)

```bash
./import-workflows.sh
```

### Mënyra 2: Manuale

1. Hap n8n → Workflows
2. Kliko "..." → "Import from File"
3. Importo secilën:
   - `workflows/whatsapp-auto-reply.json`
   - `workflows/phone-call-automation.json`
   - `workflows/telegram-stats-bot.json`
   - `workflows/scheduled-stats-report.json`

### Hapi 4: Konfiguro çdo workflow

Për çdo workflow:

1. Hap workflow-n
2. Kliko në çdo node me ikonë paralajmërimi (⚠️)
3. Zgjidh kredencialet e duhura:
   - Nodes PostgreSQL → "Hotel PostgreSQL"
   - Nodes OpenAI → "OpenAI API"
   - Nodes Telegram → "Hotel Telegram Bot"

4. Kliko **Save**
5. Kliko **Active** (toggle lart djathtas)

---

## Testimi

### Test 1: Kontrollo që sistemi punon

```bash
./test-system.sh
```

### Test 2: Test WhatsApp webhook

```bash
# Test verifikimi
curl "http://localhost:5678/webhook/whatsapp-webhook?hub.mode=subscribe&hub.challenge=test123&hub.verify_token=test"

# Duhet të kthejë: test123
```

### Test 3: Test Telegram Bot

1. Gjej botin tënd në Telegram
2. Dërgo: `/start`
3. Duhet të marrësh mesazh mirëseardhje
4. Dërgo: `/stats_today`
5. Duhet të marrësh statistikat

### Test 4: Simulo mesazh WhatsApp

```bash
curl -X POST http://localhost:5678/webhook/whatsapp-webhook \
  -H "Content-Type: application/json" \
  -d '{
    "object": "whatsapp_business_account",
    "entry": [{
      "id": "TEST_BUSINESS_ID",
      "changes": [{
        "value": {
          "messaging_product": "whatsapp",
          "metadata": {"phone_number_id": "DEMO_PHONE_NUMBER_ID"},
          "contacts": [{"profile": {"name": "Test User"}, "wa_id": "355691234567"}],
          "messages": [{
            "from": "355691234567",
            "id": "test-msg-001",
            "timestamp": "1706190000",
            "text": {"body": "Mirëdita, a keni dhoma të lira?"},
            "type": "text"
          }]
        },
        "field": "messages"
      }]
    }]
  }'
```

---

## Konfigurimi i Webhook-eve në Prodhim

### WhatsApp Business API

1. Hap Meta Business Suite
2. Shko te WhatsApp → Configuration → Webhook
3. Vendos:
   - Callback URL: `https://your-domain.com/webhook/whatsapp-webhook`
   - Verify Token: (çfarëdo stringu që zgjedh)
4. Subscribe to: `messages`

### Twilio Voice

1. Hap Twilio Console
2. Shko te Phone Numbers → Active Numbers
3. Zgjidh numrin tënd
4. Në Voice & Fax, vendos:
   - A Call Comes In: Webhook
   - URL: `https://your-domain.com/webhook/twilio-voice-webhook`
   - HTTP POST

---

## Zgjidhja e Problemeve

### Problemi: n8n nuk starton

```bash
# Kontrollo logs
docker-compose logs n8n

# Restarto
docker-compose restart n8n
```

### Problemi: Databaza nuk lidhet

```bash
# Kontrollo që PostgreSQL po punon
docker-compose exec postgres pg_isready

# Kontrollo logs
docker-compose logs postgres
```

### Problemi: Workflows nuk aktivizohen

1. Kontrollo që të gjitha kredencialet janë konfiguruar
2. Kontrollo që databaza është lidhur
3. Shiko execution logs në n8n

### Problemi: Webhook nuk merr mesazhe

1. Kontrollo që workflow është aktiv (toggle ON)
2. Kontrollo URL-në e webhook
3. Kontrollo firewall/port forwarding

### Problemi: AI nuk përgjigjet

1. Kontrollo OpenAI API key
2. Kontrollo që ke kredit në llogarinë OpenAI
3. Shiko error logs në n8n execution

---

## Komandat e Dobishme

```bash
# Start sistemin
docker-compose up -d

# Stop sistemin
docker-compose down

# Shiko logs
docker-compose logs -f

# Restart n8n
docker-compose restart n8n

# Hyr në databazë
docker-compose exec postgres psql -U hotel_admin -d hotel_automation

# Backup databazën
docker-compose exec postgres pg_dump -U hotel_admin hotel_automation > backup.sql
```

---

## Struktura e Projektit

```
hotel-automation/
├── docker-compose.yml      # Konfigurimi Docker
├── setup.sh                # Skript setup automatik
├── quick-start.sh          # Start pa Docker
├── import-workflows.sh     # Import workflows
├── test-system.sh          # Test sistemi
├── .env                    # Variablat e mjedisit
│
├── workflows/              # n8n workflows JSON
│   ├── whatsapp-auto-reply.json
│   ├── phone-call-automation.json
│   ├── telegram-stats-bot.json
│   └── scheduled-stats-report.json
│
├── database/
│   ├── schema.sql          # Skema e databazës
│   └── seed-data.sql       # Të dhëna test
│
├── config/
│   ├── .env.example        # Template për .env
│   └── n8n-credentials.json
│
├── prompts/                # AI prompts
├── examples/               # Webhook examples
└── docs/                   # Dokumentacioni
```

---

## Mbështetje

Për ndihmë:
- n8n Community: https://community.n8n.io
- Dokumentacioni n8n: https://docs.n8n.io
- OpenAI Docs: https://platform.openai.com/docs

---

*Përditësuar: Janar 2025*
