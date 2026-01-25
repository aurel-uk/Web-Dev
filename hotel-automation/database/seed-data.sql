-- ============================================
-- HOTEL AUTOMATION - SEED DATA FOR TESTING
-- ============================================
-- This file creates sample data for testing the system
-- Run after schema.sql

-- Create n8n internal database if not exists
SELECT 'CREATE DATABASE n8n_internal'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'n8n_internal')\gexec

-- ============================================
-- SAMPLE HOTEL 1: Grand Hotel Tirana
-- ============================================
INSERT INTO hotels (
    id,
    name,
    slug,
    email,
    phone,
    address,
    city,
    country,
    timezone,

    -- WhatsApp Configuration (replace with real values)
    whatsapp_phone_id,
    whatsapp_access_token,

    -- Twilio Configuration (replace with real values)
    twilio_account_sid,
    twilio_auth_token,
    twilio_phone_number,

    -- OpenAI Configuration (replace with real key)
    openai_api_key,

    -- Telegram Configuration (replace with real values)
    telegram_bot_token,
    telegram_chat_id,

    -- Human Handoff Settings
    staff_whatsapp_number,
    staff_phone_number,
    confidence_threshold,

    -- Subscription
    subscription_plan,
    is_active
) VALUES (
    'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
    'Grand Hotel Tirana',
    'grand-hotel-tirana',
    'info@grandhoteltirana.al',
    '+355691234567',
    'Rruga e Kavajes 123',
    'Tirana',
    'Albania',
    'Europe/Tirane',

    -- WhatsApp (DEMO - replace in production)
    'DEMO_PHONE_NUMBER_ID',
    'DEMO_ACCESS_TOKEN',

    -- Twilio (DEMO - replace in production)
    'DEMO_ACCOUNT_SID',
    'DEMO_AUTH_TOKEN',
    '+15551234567',

    -- OpenAI (DEMO - replace in production)
    'sk-demo-replace-with-real-key',

    -- Telegram (DEMO - replace in production)
    'DEMO_BOT_TOKEN',
    'DEMO_CHAT_ID',

    -- Staff contacts
    '+355691234567',
    '+355691234567',
    0.75,

    'professional',
    true
) ON CONFLICT (slug) DO NOTHING;

-- ============================================
-- SAMPLE HOTEL 2: Beach Resort Durres
-- ============================================
INSERT INTO hotels (
    id,
    name,
    slug,
    email,
    phone,
    address,
    city,
    country,
    timezone,
    whatsapp_phone_id,
    whatsapp_access_token,
    twilio_account_sid,
    twilio_auth_token,
    twilio_phone_number,
    openai_api_key,
    telegram_bot_token,
    telegram_chat_id,
    staff_whatsapp_number,
    staff_phone_number,
    confidence_threshold,
    subscription_plan,
    is_active
) VALUES (
    'b2c3d4e5-f6a7-8901-bcde-f23456789012',
    'Beach Resort Durres',
    'beach-resort-durres',
    'info@beachresortdurres.al',
    '+355692345678',
    'Plazhi i Madh, Durres',
    'Durres',
    'Albania',
    'Europe/Tirane',
    'DEMO_PHONE_NUMBER_ID_2',
    'DEMO_ACCESS_TOKEN_2',
    'DEMO_ACCOUNT_SID_2',
    'DEMO_AUTH_TOKEN_2',
    '+15552345678',
    'sk-demo-replace-with-real-key',
    'DEMO_BOT_TOKEN_2',
    'DEMO_CHAT_ID_2',
    '+355692345678',
    '+355692345678',
    0.80,
    'basic',
    true
) ON CONFLICT (slug) DO NOTHING;

-- ============================================
-- SAMPLE STAFF MEMBERS
-- ============================================

-- Staff for Grand Hotel Tirana
INSERT INTO hotel_staff (
    hotel_id,
    name,
    email,
    phone,
    whatsapp_number,
    telegram_user_id,
    role,
    is_available
) VALUES
(
    'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
    'Artan Hoxha',
    'artan@grandhoteltirana.al',
    '+355691111111',
    '+355691111111',
    '123456789',
    'manager',
    true
),
(
    'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
    'Elena Koci',
    'elena@grandhoteltirana.al',
    '+355692222222',
    '+355692222222',
    '987654321',
    'receptionist',
    true
);

-- Staff for Beach Resort Durres
INSERT INTO hotel_staff (
    hotel_id,
    name,
    email,
    phone,
    whatsapp_number,
    telegram_user_id,
    role,
    is_available
) VALUES
(
    'b2c3d4e5-f6a7-8901-bcde-f23456789012',
    'Dritan Shehi',
    'dritan@beachresortdurres.al',
    '+355693333333',
    '+355693333333',
    '111222333',
    'owner',
    true
);

-- ============================================
-- SAMPLE STATISTICS (for testing reports)
-- ============================================

-- Yesterday's stats for Grand Hotel Tirana
INSERT INTO daily_statistics (
    hotel_id,
    date,
    whatsapp_messages_received,
    whatsapp_messages_sent,
    whatsapp_conversations_started,
    whatsapp_conversations_resolved,
    whatsapp_ai_handled,
    whatsapp_human_handled,
    whatsapp_avg_response_time_ms,
    phone_calls_received,
    phone_calls_completed,
    phone_calls_transferred,
    phone_ai_handled,
    phone_human_handled,
    phone_avg_duration_seconds,
    total_handoffs,
    handoffs_by_low_confidence,
    handoffs_by_complex_request,
    handoffs_by_user_request,
    handoffs_by_angry_customer,
    language_distribution,
    intent_distribution,
    sentiment_distribution
) VALUES (
    'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
    CURRENT_DATE - INTERVAL '1 day',
    45,
    42,
    15,
    12,
    35,
    7,
    2500,
    8,
    7,
    2,
    5,
    2,
    120,
    9,
    3,
    4,
    1,
    1,
    '{"sq": 20, "en": 18, "it": 7}',
    '{"room_availability": 12, "price_inquiry": 8, "booking": 6, "amenities": 5, "greeting": 10, "other": 4}',
    '{"positive": 25, "neutral": 15, "negative": 4, "angry": 1}'
) ON CONFLICT (hotel_id, date) DO NOTHING;

-- Last 7 days stats (simplified)
INSERT INTO daily_statistics (hotel_id, date, whatsapp_messages_received, whatsapp_ai_handled, whatsapp_human_handled, phone_calls_received, total_handoffs, language_distribution)
SELECT
    'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
    CURRENT_DATE - (n || ' days')::interval,
    30 + (random() * 20)::int,
    25 + (random() * 10)::int,
    5 + (random() * 5)::int,
    5 + (random() * 5)::int,
    3 + (random() * 5)::int,
    '{"sq": 15, "en": 12, "it": 5}'
FROM generate_series(2, 7) AS n
ON CONFLICT (hotel_id, date) DO NOTHING;

-- ============================================
-- SAMPLE CONVERSATION (for testing)
-- ============================================

-- Create a sample conversation
INSERT INTO whatsapp_conversations (
    id,
    hotel_id,
    phone_number,
    customer_name,
    status,
    current_handler,
    detected_language,
    started_at,
    last_message_at
) VALUES (
    'conv-1234-5678-90ab-cdef12345678',
    'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
    '+355694567890',
    'Test Customer',
    'active',
    'ai',
    'sq',
    NOW() - INTERVAL '10 minutes',
    NOW() - INTERVAL '2 minutes'
) ON CONFLICT DO NOTHING;

-- Sample messages in the conversation
INSERT INTO whatsapp_messages (
    conversation_id,
    hotel_id,
    message_id,
    direction,
    message_type,
    content,
    detected_language,
    intent,
    confidence_score,
    sentiment,
    handled_by,
    created_at
) VALUES
(
    'conv-1234-5678-90ab-cdef12345678',
    'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
    'wamid-001',
    'inbound',
    'text',
    'Mirëdita, a keni dhoma të lira për fundjavë?',
    'sq',
    'room_availability',
    0.92,
    'neutral',
    'ai',
    NOW() - INTERVAL '10 minutes'
),
(
    'conv-1234-5678-90ab-cdef12345678',
    'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
    'wamid-002',
    'outbound',
    'text',
    'Mirëdita! Po, kemi dhoma të disponueshme për këtë fundjavë. Sa netë dëshironi të qëndroni?',
    'sq',
    NULL,
    NULL,
    NULL,
    'ai',
    NOW() - INTERVAL '9 minutes'
),
(
    'conv-1234-5678-90ab-cdef12345678',
    'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
    'wamid-003',
    'inbound',
    'text',
    'Dy netë, të premte dhe të shtunë. Sa kushton?',
    'sq',
    'price_inquiry',
    0.88,
    'neutral',
    'ai',
    NOW() - INTERVAL '5 minutes'
);

-- ============================================
-- UPDATE SEQUENCES (if needed)
-- ============================================
-- PostgreSQL should handle this automatically with uuid_generate_v4()

COMMIT;

-- ============================================
-- VERIFICATION QUERIES
-- ============================================
-- Run these to verify the setup:

-- SELECT COUNT(*) as hotel_count FROM hotels;
-- SELECT COUNT(*) as staff_count FROM hotel_staff;
-- SELECT COUNT(*) as stats_count FROM daily_statistics;
-- SELECT name, slug, subscription_plan FROM hotels;
