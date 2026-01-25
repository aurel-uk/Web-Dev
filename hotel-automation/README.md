# Hotel Automation System

A complete n8n-based automation system for hotels, providing AI-powered WhatsApp replies, voice call handling, and real-time statistics via Telegram.

## Features

- **WhatsApp Auto-Reply** - AI-powered responses to customer inquiries
- **Phone Call Automation** - Voice bot with speech recognition and transfer
- **Multilingual Support** - Albanian, English, and Italian
- **Human Handoff** - Smart routing to staff for complex requests
- **Statistics Bot** - Telegram bot for real-time metrics
- **Multi-Tenant** - Support multiple hotels on single instance

## Quick Start

### Prerequisites

- n8n (self-hosted)
- PostgreSQL 14+
- OpenAI API key
- WhatsApp Business API access
- Twilio account (for voice)
- Telegram Bot

### Installation

1. **Clone and setup database**
   ```bash
   cd hotel-automation/database
   psql -U postgres -c "CREATE DATABASE hotel_automation;"
   psql -U postgres -d hotel_automation -f schema.sql
   ```

2. **Configure environment**
   ```bash
   cp config/.env.example config/.env
   # Edit .env with your API keys
   ```

3. **Import n8n workflows**
   - Open n8n at `http://localhost:5678`
   - Import workflows from `workflows/` directory
   - Configure credentials

4. **Set up webhooks**
   - WhatsApp: `https://your-domain/webhook/whatsapp-webhook`
   - Twilio: `https://your-domain/webhook/twilio-voice-webhook`

## Project Structure

```
hotel-automation/
├── workflows/              # n8n workflow JSON files
│   ├── whatsapp-auto-reply.json
│   ├── phone-call-automation.json
│   ├── telegram-stats-bot.json
│   └── scheduled-stats-report.json
├── database/
│   └── schema.sql          # PostgreSQL schema
├── config/
│   ├── .env.example        # Environment template
│   └── n8n-credentials.json
├── prompts/                # AI prompt templates
│   ├── language-detection.md
│   └── response-generation.md
├── examples/
│   └── webhook-payloads.json
└── docs/
    └── SETUP_GUIDE.md      # Detailed setup instructions
```

## Human Handoff Rules

The system transfers to a human when:

| Trigger | Example |
|---------|---------|
| Low confidence score | AI confidence < 0.75 |
| Complex intent | Booking modifications, complaints |
| User request | "Speak to receptionist" |
| Angry sentiment | Frustrated customer messages |
| Special requests | Group bookings, refunds |

## Telegram Bot Commands

| Command | Description |
|---------|-------------|
| `/stats_today` | Today's statistics |
| `/stats_week` | This week's statistics |
| `/stats_month` | This month's statistics |
| `/pending` | Pending handoff requests |
| `/help` | Show available commands |

## Cost Estimation

| Service | Approximate Cost |
|---------|-----------------|
| n8n | Free (self-hosted) |
| OpenAI | ~$0.01 per message |
| WhatsApp | Free tier: 1,000 conversations/month |
| Twilio Voice | ~$0.02 per minute |
| Telegram | Free |

## Security Considerations

- Store API keys in environment variables
- Use HTTPS for all webhooks
- Enable n8n authentication
- Regular database backups
- Rate limiting on webhooks

## Support

For detailed setup instructions, see [docs/SETUP_GUIDE.md](docs/SETUP_GUIDE.md)

## License

MIT License - See LICENSE file for details
