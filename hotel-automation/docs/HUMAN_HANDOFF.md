# Human Handoff Logic Documentation

## Overview

The human handoff system ensures that complex or sensitive customer interactions are smoothly transferred to hotel staff. This document explains the logic, thresholds, and implementation details.

## Handoff Decision Flow

```
┌─────────────────────────────────────────────────────────────┐
│                    Incoming Message                          │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│              AI Analysis (GPT-4)                             │
│  - Language Detection                                        │
│  - Intent Classification                                     │
│  - Confidence Scoring                                        │
│  - Sentiment Analysis                                        │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│              Handoff Decision Engine                         │
│                                                              │
│  IF any of these conditions are TRUE:                       │
│    ┌──────────────────────────────────────────────────────┐ │
│    │ • confidence < threshold (default 0.75)              │ │
│    │ • intent is "complex" type                           │ │
│    │ • user explicitly requests human                     │ │
│    │ • sentiment is "angry"                               │ │
│    │ • multiple frustrated follow-ups                     │ │
│    └──────────────────────────────────────────────────────┘ │
│                              │                               │
│              THEN → TRIGGER HANDOFF                          │
└─────────────────────────────────────────────────────────────┘
                              │
              ┌───────────────┴───────────────┐
              ▼                               ▼
┌──────────────────────┐         ┌──────────────────────┐
│   Continue with AI   │         │   Human Handoff      │
│   Response           │         │   - Notify staff     │
│                      │         │   - Log event        │
│                      │         │   - Inform customer  │
└──────────────────────┘         └──────────────────────┘
```

## Handoff Triggers

### 1. Low Confidence Score

**Threshold**: Configurable per hotel (default: 0.75)

```javascript
// Configuration in database
hotel.confidence_threshold = 0.75;

// Decision logic
if (analysis.confidence < hotel.confidence_threshold) {
    triggerHandoff('low_confidence');
}
```

**Why this matters**: If the AI isn't confident it understood the request, it's better to involve a human than to give an incorrect response.

### 2. Complex Intent Types

These intents always require human attention:

| Intent | Reason |
|--------|--------|
| `booking_modification` | Requires access to booking system |
| `booking_cancellation` | Potential revenue impact |
| `complaint` | Customer satisfaction critical |
| `refund_request` | Financial decision required |
| `special_pricing` | Negotiation required |
| `group_reservation` | Complex coordination needed |
| `unknown` | AI couldn't classify the request |

```javascript
const complexIntents = [
    'complaint',
    'refund_request',
    'human_request',
    'booking_modification',
    'special_pricing',
    'group_reservation',
    'unknown'
];

if (complexIntents.includes(analysis.intent)) {
    triggerHandoff('complex_request');
}
```

### 3. Explicit Human Request

Keywords detected in multiple languages:

**English:**
- "human", "person", "receptionist", "operator"
- "real person", "speak to someone", "talk to someone"
- "agent", "staff", "manager"

**Albanian:**
- "njeri", "person", "recepsionist", "operator"
- "dua të flas me dikë" (I want to speak with someone)
- "stafi", "menaxher"

**Italian:**
- "umano", "persona", "receptionist", "operatore"
- "parlare con qualcuno" (speak with someone)
- "personale", "manager"

```javascript
if (analysis.wants_human === true) {
    triggerHandoff('user_requested');
}
```

### 4. Angry/Frustrated Sentiment

Detected through:
- ALL CAPS text
- Multiple exclamation marks (!!!)
- Strong negative language
- Repeated messages without resolution

```javascript
if (analysis.sentiment === 'angry') {
    triggerHandoff('angry_customer');
}
```

### 5. Multiple Follow-Up Messages

If customer sends 3+ messages without resolution:

```javascript
// Check message count in last 5 minutes
const recentMessages = await getRecentMessages(
    conversationId,
    timeWindow: 5 * 60 * 1000
);

if (recentMessages.length >= 3 && !anyResolved) {
    triggerHandoff('frustrated_customer');
}
```

## Handoff Methods

### WhatsApp Handoff

**Method 1: Forward to Staff WhatsApp**
```javascript
// Send conversation context to staff
await sendWhatsAppMessage(hotel.staff_whatsapp_number, {
    type: 'text',
    text: formatHandoffNotification(context)
});
```

**Method 2: Telegram Notification**
```javascript
await sendTelegramMessage(hotel.telegram_chat_id, {
    text: formatTelegramNotification(context),
    parse_mode: 'Markdown'
});
```

### Phone Call Handoff

**Method 1: Live Transfer**
```xml
<Response>
    <Say>I'm transferring you to our reception. Please hold.</Say>
    <Dial timeout="30">
        <Number>+355691234567</Number>
    </Dial>
</Response>
```

**Method 2: Callback Request**
```xml
<Response>
    <Say>
        Our reception is currently busy.
        We will call you back within 15 minutes.
        Thank you for your patience.
    </Say>
    <Hangup/>
</Response>
<!-- Staff notified via Telegram -->
```

## Customer Notification Messages

When handoff occurs, customer receives confirmation:

### Albanian
```
Faleminderit për mesazhin tuaj.
Një nga stafi ynë do t'ju kontaktojë së shpejti.
```

### English
```
Thank you for your message.
A member of our team will contact you shortly.
```

### Italian
```
Grazie per il tuo messaggio.
Un membro del nostro team ti contatterà a breve.
```

## Staff Notification Format

When a handoff is triggered, staff receives:

```
🚨 *New Handoff Required*

🏨 *Hotel:* Grand Hotel Tirana
📱 *Customer:* +355692345678
👤 *Name:* John Doe
🌐 *Language:* EN

💬 *Message:*
"I want to cancel my booking and get a refund"

🎯 *Intent:* refund_request
📊 *Confidence:* 92%
😊 *Sentiment:* negative

⚠️ *Reason:* Complex request requiring staff

⏰ *Time:* 2024-01-25 14:32:15
```

## Database Logging

Every handoff is logged for analytics:

```sql
INSERT INTO handoff_events (
    hotel_id,
    source_type,           -- 'whatsapp' or 'phone_call'
    source_id,             -- conversation or call ID
    reason,                -- 'low_confidence', 'complex_request', etc.
    reason_details,
    confidence_score,
    intent,
    customer_phone,
    customer_language,
    conversation_summary,
    notification_sent,
    notification_channels, -- ['telegram', 'whatsapp']
    status,                -- 'pending', 'acknowledged', 'resolved'
    created_at
) VALUES (...);
```

## Handoff Resolution

Staff can resolve handoffs:

1. **Acknowledge**: Staff takes over the conversation
2. **Resolve**: Issue is resolved, conversation closed
3. **Expire**: Auto-expire after 24 hours if no action

```sql
UPDATE handoff_events
SET
    status = 'resolved',
    resolved_at = NOW(),
    resolution_notes = 'Booking modified as requested'
WHERE id = 'handoff-uuid';
```

## Statistics Tracking

Daily statistics include handoff breakdown:

```sql
SELECT
    date,
    total_handoffs,
    handoffs_by_low_confidence,
    handoffs_by_complex_request,
    handoffs_by_user_request,
    handoffs_by_angry_customer
FROM daily_statistics
WHERE hotel_id = 'hotel-uuid'
ORDER BY date DESC;
```

## Customization per Hotel

Each hotel can customize:

```sql
UPDATE hotels SET
    confidence_threshold = 0.80,  -- Higher threshold = more handoffs
    staff_whatsapp_number = '+355...',
    staff_phone_number = '+355...'
WHERE id = 'hotel-uuid';
```

## Best Practices

1. **Start with lower threshold** (0.70-0.75) and adjust based on feedback
2. **Monitor handoff reasons** to identify AI improvement opportunities
3. **Set up multiple notification channels** (Telegram + WhatsApp)
4. **Train staff on handoff format** so they have context immediately
5. **Track resolution times** to ensure SLA compliance

## Metrics to Monitor

| Metric | Target | Action if Exceeded |
|--------|--------|-------------------|
| Handoff rate | < 20% | Improve AI prompts |
| Avg response time | < 5 min | Add staff shifts |
| Resolution rate | > 90% | Improve training |
| Angry handoffs | < 5% | Review AI tone |
