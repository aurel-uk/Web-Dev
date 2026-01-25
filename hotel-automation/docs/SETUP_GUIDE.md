# Hotel Automation System - Complete Setup Guide

## Table of Contents

1. [Overview](#overview)
2. [Prerequisites](#prerequisites)
3. [Database Setup](#database-setup)
4. [n8n Installation](#n8n-installation)
5. [API Configuration](#api-configuration)
6. [Workflow Import](#workflow-import)
7. [Testing](#testing)
8. [Production Deployment](#production-deployment)
9. [Troubleshooting](#troubleshooting)

---

## Overview

This system provides automated communication handling for hotels including:

- **WhatsApp Auto-Reply**: Intelligent responses to customer inquiries
- **Phone Call Automation**: Voice bot with speech recognition
- **Multilingual Support**: Albanian (sq), English (en), Italian (it)
- **Human Handoff**: Smart routing to staff when needed
- **Statistics Dashboard**: Telegram bot for real-time stats

### Architecture

```
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│   WhatsApp      │     │   Twilio        │     │   Telegram      │
│   Business API  │     │   Voice         │     │   Bot API       │
└────────┬────────┘     └────────┬────────┘     └────────┬────────┘
         │                       │                       │
         ▼                       ▼                       ▼
┌────────────────────────────────────────────────────────────────┐
│                         n8n Workflows                           │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐          │
│  │  WhatsApp    │  │  Phone Call  │  │  Telegram    │          │
│  │  Auto-Reply  │  │  Automation  │  │  Stats Bot   │          │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘          │
│         │                 │                 │                   │
│         ▼                 ▼                 ▼                   │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │                    OpenAI GPT-4                          │   │
│  │  - Language Detection                                    │   │
│  │  - Intent Classification                                 │   │
│  │  - Response Generation                                   │   │
│  └─────────────────────────────────────────────────────────┘   │
└────────────────────────────────────────────────────────────────┘
                              │
                              ▼
                    ┌─────────────────┐
                    │   PostgreSQL    │
                    │   Database      │
                    └─────────────────┘
```

---

## Prerequisites

### Required Services

| Service | Purpose | Cost |
|---------|---------|------|
| n8n (self-hosted) | Workflow automation | Free (self-hosted) |
| PostgreSQL | Database | Free (self-hosted) |
| OpenAI API | AI processing | Pay-per-use (~$0.01/request) |
| Meta WhatsApp Business | WhatsApp messaging | Free tier available |
| Twilio | Voice calls | Pay-per-use (~$0.02/min) |
| Telegram Bot | Notifications & stats | Free |

### System Requirements

- **Server**: 2 CPU cores, 4GB RAM minimum
- **OS**: Ubuntu 20.04+ / Debian 11+ / Docker
- **Node.js**: 18.x or higher
- **PostgreSQL**: 14.x or higher
- **Domain**: With SSL certificate for webhooks

---

## Database Setup

### 1. Install PostgreSQL

```bash
# Ubuntu/Debian
sudo apt update
sudo apt install postgresql postgresql-contrib

# Start PostgreSQL
sudo systemctl start postgresql
sudo systemctl enable postgresql
```

### 2. Create Database and User

```bash
# Login to PostgreSQL
sudo -u postgres psql

# Create database and user
CREATE DATABASE hotel_automation;
CREATE USER hotel_admin WITH ENCRYPTED PASSWORD 'your-secure-password';
GRANT ALL PRIVILEGES ON DATABASE hotel_automation TO hotel_admin;

# Enable UUID extension
\c hotel_automation
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

\q
```

### 3. Import Schema

```bash
# Navigate to the project directory
cd hotel-automation/database

# Import the schema
psql -U hotel_admin -d hotel_automation -f schema.sql
```

### 4. Verify Installation

```bash
psql -U hotel_admin -d hotel_automation -c "\dt"
```

You should see tables: `hotels`, `whatsapp_conversations`, `whatsapp_messages`, `phone_calls`, etc.

---

## n8n Installation

### Option 1: Docker (Recommended)

```bash
# Create docker-compose.yml
cat > docker-compose.yml << 'EOF'
version: '3.8'

services:
  n8n:
    image: n8nio/n8n:latest
    container_name: n8n-hotel
    restart: always
    ports:
      - "5678:5678"
    environment:
      - N8N_HOST=0.0.0.0
      - N8N_PORT=5678
      - N8N_PROTOCOL=https
      - N8N_ENCRYPTION_KEY=${N8N_ENCRYPTION_KEY}
      - WEBHOOK_URL=https://your-domain.com
      - DB_TYPE=postgresdb
      - DB_POSTGRESDB_HOST=postgres
      - DB_POSTGRESDB_PORT=5432
      - DB_POSTGRESDB_DATABASE=n8n
      - DB_POSTGRESDB_USER=n8n_user
      - DB_POSTGRESDB_PASSWORD=${DB_PASSWORD}
      - GENERIC_TIMEZONE=Europe/Tirane
    volumes:
      - n8n_data:/home/node/.n8n
    depends_on:
      - postgres

  postgres:
    image: postgres:14
    container_name: postgres-hotel
    restart: always
    environment:
      - POSTGRES_USER=hotel_admin
      - POSTGRES_PASSWORD=${DB_PASSWORD}
      - POSTGRES_DB=hotel_automation
    volumes:
      - postgres_data:/var/lib/postgresql/data
      - ./database/schema.sql:/docker-entrypoint-initdb.d/schema.sql

volumes:
  n8n_data:
  postgres_data:
EOF

# Start containers
docker-compose up -d
```

### Option 2: npm Installation

```bash
# Install n8n globally
npm install n8n -g

# Set environment variables
export N8N_ENCRYPTION_KEY="your-32-character-key"
export DB_TYPE=postgresdb
export DB_POSTGRESDB_HOST=localhost
export DB_POSTGRESDB_DATABASE=hotel_automation
export DB_POSTGRESDB_USER=hotel_admin
export DB_POSTGRESDB_PASSWORD=your-password

# Start n8n
n8n start
```

### Access n8n

Open your browser and go to: `http://your-server:5678`

Create an admin account on first access.

---

## API Configuration

### 1. OpenAI API

1. Go to [OpenAI Platform](https://platform.openai.com/api-keys)
2. Create a new API key
3. Note: You need credits for API usage

**In n8n:**
1. Go to **Settings > Credentials**
2. Click **Add Credential**
3. Select **OpenAI API**
4. Enter your API key

### 2. WhatsApp Business API (Meta)

1. Go to [Meta Business Suite](https://business.facebook.com/)
2. Create a Meta Business Account (if not existing)
3. Add WhatsApp to your business
4. Go to **WhatsApp > API Setup**
5. Note your:
   - Phone Number ID
   - WhatsApp Business Account ID
   - Access Token (generate a permanent token)

**Webhook Configuration:**
1. Go to **WhatsApp > Configuration**
2. Click **Edit** on Webhook
3. Enter your webhook URL: `https://your-n8n-domain.com/webhook/whatsapp-webhook`
4. Enter verification token (choose any string)
5. Subscribe to: `messages`

### 3. Twilio (Voice)

1. Sign up at [Twilio Console](https://console.twilio.com/)
2. Get a phone number with voice capability
3. Note your:
   - Account SID
   - Auth Token
   - Phone Number

**Voice Webhook:**
1. Go to **Phone Numbers > Manage > Active Numbers**
2. Click your number
3. Under **Voice & Fax**, set:
   - A Call Comes In: Webhook
   - URL: `https://your-n8n-domain.com/webhook/twilio-voice-webhook`
   - HTTP POST

### 4. Telegram Bot

1. Open Telegram and message [@BotFather](https://t.me/botfather)
2. Send `/newbot` and follow instructions
3. Note your bot token

**Set Bot Commands:**
```
/setcommands
```
Then paste:
```
stats_today - Today's statistics
stats_week - This week's statistics
stats_month - This month's statistics
pending - Pending handoff requests
help - Show help message
```

---

## Workflow Import

### 1. Import Workflows

1. Open n8n at `http://your-server:5678`
2. Go to **Workflows**
3. Click **Import from File**
4. Import these files in order:
   - `workflows/whatsapp-auto-reply.json`
   - `workflows/phone-call-automation.json`
   - `workflows/telegram-stats-bot.json`
   - `workflows/scheduled-stats-report.json`

### 2. Configure Credentials

For each workflow, update the credential references:

1. Click on each database node
2. Select your PostgreSQL credential
3. Click on OpenAI nodes
4. Select your OpenAI credential
5. Click on Telegram nodes
6. Select your Telegram credential

### 3. Update Webhook URLs

After importing, note the webhook URLs generated by n8n:
- WhatsApp: `https://your-domain.com/webhook/whatsapp-webhook`
- Twilio: `https://your-domain.com/webhook/twilio-voice-webhook`

Update these in Meta and Twilio dashboards.

### 4. Activate Workflows

Click the **Active** toggle on each workflow to enable them.

---

## Testing

### Test WhatsApp

1. Send a test message to your WhatsApp Business number
2. Check n8n executions for the workflow run
3. Verify response was sent

**Test Messages:**
```
English: "Hello, do you have rooms available?"
Albanian: "Mirëdita, a keni dhoma të lira?"
Italian: "Buongiorno, avete camere disponibili?"
```

### Test Phone Calls

1. Call your Twilio number
2. Listen for the multilingual greeting
3. Speak a test phrase
4. Verify AI response or transfer

### Test Telegram Bot

1. Message your bot with `/start`
2. Send `/stats_today`
3. Verify you receive statistics

### Test Human Handoff

Send messages that should trigger handoff:
```
"I want to speak with a receptionist"
"I have a complaint about my room"
"I need a refund for my booking"
```

---

## Production Deployment

### Security Checklist

- [ ] Use HTTPS for all webhooks
- [ ] Set strong database passwords
- [ ] Enable n8n authentication
- [ ] Use environment variables for secrets
- [ ] Set up firewall rules
- [ ] Enable database SSL
- [ ] Regular backups

### SSL with Nginx

```nginx
server {
    listen 443 ssl;
    server_name n8n.your-domain.com;

    ssl_certificate /etc/letsencrypt/live/n8n.your-domain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/n8n.your-domain.com/privkey.pem;

    location / {
        proxy_pass http://localhost:5678;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_cache_bypass $http_upgrade;
    }
}
```

### Monitoring

1. Set up n8n error workflow for notifications
2. Monitor database size and performance
3. Track API usage and costs
4. Set up uptime monitoring

---

## Troubleshooting

### Common Issues

**Webhook not receiving messages:**
- Verify webhook URL is accessible from internet
- Check SSL certificate is valid
- Verify verification token matches
- Check n8n is running and workflow is active

**AI responses not generating:**
- Verify OpenAI API key is valid
- Check you have API credits
- Review n8n execution logs

**Database connection failed:**
- Verify PostgreSQL is running
- Check connection credentials
- Ensure database user has permissions

**Telegram bot not responding:**
- Verify bot token is correct
- Ensure webhook is set (or use polling)
- Check user is authorized in database

### Debug Mode

Enable debug logging in n8n:
```bash
export N8N_LOG_LEVEL=debug
n8n start
```

### Getting Help

- n8n Community: [community.n8n.io](https://community.n8n.io)
- n8n Documentation: [docs.n8n.io](https://docs.n8n.io)
- OpenAI Documentation: [platform.openai.com/docs](https://platform.openai.com/docs)

---

## Adding New Hotels (Multi-Tenant)

### 1. Database Entry

```sql
INSERT INTO hotels (
    name, slug, email, phone,
    whatsapp_phone_id, whatsapp_access_token,
    twilio_account_sid, twilio_auth_token, twilio_phone_number,
    openai_api_key,
    telegram_bot_token, telegram_chat_id,
    staff_whatsapp_number, staff_phone_number,
    confidence_threshold
) VALUES (
    'New Hotel Name',
    'new-hotel-slug',
    'info@newhotel.com',
    '+355691234567',
    'whatsapp-phone-id',
    'whatsapp-token',
    'twilio-sid',
    'twilio-token',
    '+1234567890',
    'openai-key',
    'telegram-token',
    '123456789',
    '+355691234567',
    '+355691234567',
    0.75
);
```

### 2. Add Staff Members

```sql
INSERT INTO hotel_staff (
    hotel_id, name, email, phone,
    telegram_user_id, role
) VALUES (
    'hotel-uuid',
    'Staff Name',
    'staff@hotel.com',
    '+355691234567',
    '987654321',
    'receptionist'
);
```

### 3. Configure Webhooks

Each hotel can share webhooks - the system identifies hotels by:
- WhatsApp: Phone Number ID in webhook payload
- Twilio: Called number (To field)
- Telegram: User's telegram_user_id in staff table

---

*Last updated: January 2025*
