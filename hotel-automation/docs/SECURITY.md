# Security & Scalability Guide

## Security Considerations

### 1. API Key Management

**Never hardcode API keys** - Use environment variables or secrets management.

```bash
# Good: Environment variables
export OPENAI_API_KEY="sk-..."
export WHATSAPP_ACCESS_TOKEN="EAA..."

# Better: Secrets manager (AWS Secrets Manager, HashiCorp Vault)
aws secretsmanager get-secret-value --secret-id hotel/openai-key
```

**Key rotation schedule:**
| Service | Rotation Period |
|---------|----------------|
| OpenAI API | Every 90 days |
| WhatsApp Token | When compromised |
| Database Password | Every 90 days |
| n8n Encryption Key | Annually |

### 2. Database Security

```sql
-- Create read-only user for reporting
CREATE USER hotel_readonly WITH PASSWORD 'readonly-password';
GRANT SELECT ON ALL TABLES IN SCHEMA public TO hotel_readonly;

-- Encrypt sensitive columns (store encrypted, decrypt in app)
-- Use pgcrypto extension
CREATE EXTENSION pgcrypto;

-- Example: Store encrypted API keys
UPDATE hotels SET
    whatsapp_access_token = pgp_sym_encrypt(
        'actual-token',
        'encryption-key'
    )::text
WHERE id = 'hotel-uuid';
```

### 3. Webhook Security

**Verify webhook signatures:**

```javascript
// WhatsApp signature verification
const crypto = require('crypto');

function verifyWhatsAppSignature(payload, signature, appSecret) {
    const expectedSignature = crypto
        .createHmac('sha256', appSecret)
        .update(payload)
        .digest('hex');

    return `sha256=${expectedSignature}` === signature;
}
```

**Twilio signature verification:**
```javascript
const twilio = require('twilio');

function verifyTwilioSignature(url, params, signature, authToken) {
    return twilio.validateRequest(
        authToken,
        signature,
        url,
        params
    );
}
```

### 4. Input Validation

Always validate and sanitize inputs:

```javascript
// Sanitize phone numbers
function sanitizePhone(phone) {
    return phone.replace(/[^0-9+]/g, '');
}

// Escape SQL (but use parameterized queries!)
function escapeSQL(input) {
    return input.replace(/'/g, "''");
}

// Validate hotel ID format (UUID)
function isValidUUID(id) {
    const uuidRegex = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;
    return uuidRegex.test(id);
}
```

### 5. Rate Limiting

**n8n webhook rate limiting:**

```nginx
# Nginx rate limiting
limit_req_zone $binary_remote_addr zone=webhooks:10m rate=10r/s;

server {
    location /webhook/ {
        limit_req zone=webhooks burst=20 nodelay;
        proxy_pass http://localhost:5678;
    }
}
```

**Application-level rate limiting:**

```javascript
// Per-hotel rate limiting
const hotelLimits = {
    basic: 100,      // 100 messages/day
    professional: 1000,
    enterprise: Infinity
};

async function checkRateLimit(hotelId) {
    const today = new Date().toISOString().split('T')[0];
    const count = await db.query(`
        SELECT COUNT(*) FROM whatsapp_messages
        WHERE hotel_id = $1 AND DATE(created_at) = $2
    `, [hotelId, today]);

    const hotel = await getHotel(hotelId);
    return count < hotelLimits[hotel.subscription_plan];
}
```

### 6. Data Privacy (GDPR Compliance)

**Data retention policies:**

```sql
-- Auto-delete old messages (configurable per hotel)
DELETE FROM whatsapp_messages
WHERE created_at < NOW() - INTERVAL '90 days'
AND hotel_id IN (
    SELECT id FROM hotels WHERE data_retention_days = 90
);

-- Anonymize after period
UPDATE whatsapp_conversations
SET
    phone_number = 'ANONYMIZED',
    customer_name = 'ANONYMIZED'
WHERE resolved_at < NOW() - INTERVAL '1 year';
```

**Right to deletion:**

```sql
-- Delete all customer data
DELETE FROM whatsapp_messages WHERE phone_number = '+355...';
DELETE FROM whatsapp_conversations WHERE phone_number = '+355...';
DELETE FROM phone_calls WHERE caller_number = '+355...';
DELETE FROM handoff_events WHERE customer_phone = '+355...';
```

### 7. Audit Logging

```sql
-- All API requests are logged
CREATE TABLE api_request_logs (
    id UUID PRIMARY KEY,
    hotel_id UUID,
    api_name VARCHAR(100),
    endpoint VARCHAR(255),
    method VARCHAR(10),
    request_payload JSONB,
    response_status INTEGER,
    ip_address INET,
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);

-- Index for quick lookups
CREATE INDEX idx_api_logs_hotel_date
ON api_request_logs(hotel_id, created_at);
```

---

## Scalability Considerations

### 1. n8n Scaling

**Horizontal scaling with queue mode:**

```yaml
# docker-compose.yml for scaled deployment
version: '3.8'

services:
  n8n-main:
    image: n8nio/n8n
    environment:
      - EXECUTIONS_MODE=queue
      - QUEUE_BULL_REDIS_HOST=redis
    depends_on:
      - redis
      - postgres

  n8n-worker-1:
    image: n8nio/n8n
    command: worker
    environment:
      - EXECUTIONS_MODE=queue
      - QUEUE_BULL_REDIS_HOST=redis
    depends_on:
      - redis
      - n8n-main

  n8n-worker-2:
    image: n8nio/n8n
    command: worker
    environment:
      - EXECUTIONS_MODE=queue
      - QUEUE_BULL_REDIS_HOST=redis

  redis:
    image: redis:7

  postgres:
    image: postgres:14
```

### 2. Database Optimization

**Connection pooling:**

```bash
# Use PgBouncer for connection pooling
apt install pgbouncer

# /etc/pgbouncer/pgbouncer.ini
[databases]
hotel_automation = host=localhost dbname=hotel_automation

[pgbouncer]
pool_mode = transaction
max_client_conn = 1000
default_pool_size = 20
```

**Partitioning for large tables:**

```sql
-- Partition messages by month
CREATE TABLE whatsapp_messages_partitioned (
    LIKE whatsapp_messages INCLUDING ALL
) PARTITION BY RANGE (created_at);

-- Create monthly partitions
CREATE TABLE whatsapp_messages_2024_01
    PARTITION OF whatsapp_messages_partitioned
    FOR VALUES FROM ('2024-01-01') TO ('2024-02-01');

CREATE TABLE whatsapp_messages_2024_02
    PARTITION OF whatsapp_messages_partitioned
    FOR VALUES FROM ('2024-02-01') TO ('2024-03-01');
```

### 3. Caching Strategy

```javascript
// Redis caching for frequently accessed data
const redis = require('redis');
const client = redis.createClient();

// Cache hotel configuration (expires in 5 min)
async function getHotelConfig(hotelId) {
    const cached = await client.get(`hotel:${hotelId}`);
    if (cached) return JSON.parse(cached);

    const hotel = await db.query('SELECT * FROM hotels WHERE id = $1', [hotelId]);
    await client.setEx(`hotel:${hotelId}`, 300, JSON.stringify(hotel));
    return hotel;
}

// Cache intent definitions
async function getIntentDefinitions() {
    const cached = await client.get('intents:all');
    if (cached) return JSON.parse(cached);

    const intents = await db.query('SELECT * FROM intent_definitions');
    await client.setEx('intents:all', 3600, JSON.stringify(intents));
    return intents;
}
```

### 4. Load Balancing

```nginx
# Nginx load balancer for multiple n8n instances
upstream n8n_cluster {
    least_conn;
    server n8n-1:5678 weight=1;
    server n8n-2:5678 weight=1;
    server n8n-3:5678 weight=1;
}

server {
    listen 443 ssl;
    server_name n8n.hotel-automation.com;

    location / {
        proxy_pass http://n8n_cluster;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
    }

    # Sticky sessions for webhooks
    location /webhook/ {
        proxy_pass http://n8n_cluster;
        ip_hash;
    }
}
```

### 5. Cost Optimization

**OpenAI cost reduction:**

| Strategy | Savings |
|----------|---------|
| Use GPT-4o-mini for classification | ~90% cheaper than GPT-4 |
| Cache common responses | Reduce API calls by 30% |
| Batch similar requests | Reduce overhead |
| Set max tokens appropriately | Avoid wasted tokens |

```javascript
// Use cheaper model for simple tasks
const model = intent === 'greeting' || intent === 'thank_you'
    ? 'gpt-4o-mini'  // $0.15/1M tokens
    : 'gpt-4o';       // $5/1M tokens
```

**WhatsApp cost optimization:**
- Free tier: 1,000 service conversations/month
- Use template messages when possible
- Batch notifications

**Twilio cost optimization:**
- Use regional phone numbers
- Optimize call duration with clear prompts
- Use callbacks instead of holding

---

## Monitoring & Alerting

### Key Metrics to Monitor

```yaml
# Prometheus metrics
metrics:
  - name: webhook_requests_total
    type: counter
    labels: [hotel_id, channel]

  - name: ai_response_latency_seconds
    type: histogram
    labels: [model, intent]

  - name: handoff_rate
    type: gauge
    labels: [hotel_id, reason]

  - name: api_errors_total
    type: counter
    labels: [api, error_type]
```

### Alert Rules

```yaml
# Prometheus alerting rules
groups:
  - name: hotel-automation
    rules:
      - alert: HighHandoffRate
        expr: handoff_rate > 0.3
        for: 1h
        labels:
          severity: warning
        annotations:
          summary: "High handoff rate for {{ $labels.hotel_id }}"

      - alert: APIErrorSpike
        expr: rate(api_errors_total[5m]) > 10
        for: 5m
        labels:
          severity: critical
        annotations:
          summary: "API error spike: {{ $labels.api }}"

      - alert: SlowAIResponse
        expr: ai_response_latency_seconds > 5
        for: 10m
        labels:
          severity: warning
        annotations:
          summary: "AI responses are slow"
```

---

## Disaster Recovery

### Backup Strategy

```bash
#!/bin/bash
# Daily backup script

# Database backup
pg_dump -U hotel_admin hotel_automation | gzip > \
    /backups/db/hotel_automation_$(date +%Y%m%d).sql.gz

# n8n workflows backup
docker exec n8n-hotel n8n export:workflow --all > \
    /backups/n8n/workflows_$(date +%Y%m%d).json

# Retain 30 days
find /backups -mtime +30 -delete

# Upload to S3
aws s3 sync /backups s3://hotel-automation-backups/
```

### Recovery Procedure

1. **Database recovery:**
   ```bash
   gunzip -c backup.sql.gz | psql -U hotel_admin hotel_automation
   ```

2. **n8n workflow recovery:**
   ```bash
   n8n import:workflow --input=workflows_backup.json
   ```

3. **Verify all services:**
   - Test webhook endpoints
   - Verify credentials
   - Check database connections
