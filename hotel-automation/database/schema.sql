-- ============================================
-- HOTEL AUTOMATION SAAS - DATABASE SCHEMA
-- Multi-tenant architecture for hotel automation
-- Supports: WhatsApp, Phone Calls, Telegram Stats
-- ============================================

-- Enable UUID extension for PostgreSQL
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- ============================================
-- CORE TABLES
-- ============================================

-- Hotels (Tenants)
CREATE TABLE hotels (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,

    -- Contact Information
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50),
    address TEXT,
    city VARCHAR(100),
    country VARCHAR(100),
    timezone VARCHAR(50) DEFAULT 'Europe/Tirane',

    -- API Credentials (encrypted in production)
    whatsapp_phone_id VARCHAR(100),
    whatsapp_access_token TEXT,
    twilio_account_sid VARCHAR(100),
    twilio_auth_token TEXT,
    twilio_phone_number VARCHAR(50),
    openai_api_key TEXT,
    telegram_bot_token TEXT,
    telegram_chat_id VARCHAR(100),

    -- Human Handoff Settings
    staff_whatsapp_number VARCHAR(50),
    staff_phone_number VARCHAR(50),
    confidence_threshold DECIMAL(3,2) DEFAULT 0.75,

    -- Subscription & Status
    subscription_plan VARCHAR(50) DEFAULT 'basic',
    is_active BOOLEAN DEFAULT true,

    -- Timestamps
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Hotel Staff Members
CREATE TABLE hotel_staff (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    hotel_id UUID NOT NULL REFERENCES hotels(id) ON DELETE CASCADE,

    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50),
    whatsapp_number VARCHAR(50),
    telegram_user_id VARCHAR(100),

    role VARCHAR(50) DEFAULT 'receptionist', -- receptionist, manager, owner
    is_available BOOLEAN DEFAULT true,
    notification_preferences JSONB DEFAULT '{"whatsapp": true, "telegram": true, "email": false}',

    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,

    UNIQUE(hotel_id, email)
);

-- ============================================
-- CONVERSATION TABLES
-- ============================================

-- WhatsApp Conversations
CREATE TABLE whatsapp_conversations (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    hotel_id UUID NOT NULL REFERENCES hotels(id) ON DELETE CASCADE,

    -- Customer Information
    phone_number VARCHAR(50) NOT NULL,
    customer_name VARCHAR(255),

    -- Conversation Status
    status VARCHAR(50) DEFAULT 'active', -- active, resolved, handed_off, archived
    current_handler VARCHAR(50) DEFAULT 'ai', -- ai, human
    assigned_staff_id UUID REFERENCES hotel_staff(id),

    -- Language & Context
    detected_language VARCHAR(10) DEFAULT 'en', -- sq, en, it
    conversation_context JSONB DEFAULT '{}',

    -- Timestamps
    started_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    last_message_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP WITH TIME ZONE,

    UNIQUE(hotel_id, phone_number)
);

-- WhatsApp Messages
CREATE TABLE whatsapp_messages (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    conversation_id UUID NOT NULL REFERENCES whatsapp_conversations(id) ON DELETE CASCADE,
    hotel_id UUID NOT NULL REFERENCES hotels(id) ON DELETE CASCADE,

    -- Message Details
    message_id VARCHAR(255), -- WhatsApp message ID
    direction VARCHAR(10) NOT NULL, -- inbound, outbound
    message_type VARCHAR(50) DEFAULT 'text', -- text, image, audio, document
    content TEXT,
    media_url TEXT,

    -- AI Analysis
    detected_language VARCHAR(10),
    intent VARCHAR(100),
    confidence_score DECIMAL(4,3),
    sentiment VARCHAR(50), -- positive, neutral, negative, angry
    entities JSONB DEFAULT '{}',

    -- Handling
    handled_by VARCHAR(50) DEFAULT 'ai', -- ai, human
    response_time_ms INTEGER,

    -- Status
    status VARCHAR(50) DEFAULT 'delivered', -- sent, delivered, read, failed
    error_message TEXT,

    -- Timestamps
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP WITH TIME ZONE
);

-- ============================================
-- PHONE CALL TABLES
-- ============================================

-- Phone Calls
CREATE TABLE phone_calls (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    hotel_id UUID NOT NULL REFERENCES hotels(id) ON DELETE CASCADE,

    -- Call Details
    twilio_call_sid VARCHAR(100) UNIQUE,
    caller_number VARCHAR(50) NOT NULL,
    caller_name VARCHAR(255),

    -- Call Status
    status VARCHAR(50) DEFAULT 'in_progress', -- in_progress, completed, transferred, failed
    direction VARCHAR(20) DEFAULT 'inbound', -- inbound, outbound

    -- Language & Intent
    detected_language VARCHAR(10),
    greeting_language VARCHAR(10),

    -- Handling
    handled_by VARCHAR(50) DEFAULT 'ai', -- ai, human
    transferred_to VARCHAR(50),
    transfer_reason TEXT,

    -- Duration & Timing
    duration_seconds INTEGER DEFAULT 0,
    ring_duration_seconds INTEGER,

    -- Timestamps
    started_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    answered_at TIMESTAMP WITH TIME ZONE,
    ended_at TIMESTAMP WITH TIME ZONE
);

-- Call Transcripts (Speech-to-Text segments)
CREATE TABLE call_transcripts (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    call_id UUID NOT NULL REFERENCES phone_calls(id) ON DELETE CASCADE,

    -- Transcript Details
    speaker VARCHAR(20) NOT NULL, -- caller, bot
    transcript TEXT NOT NULL,
    audio_url TEXT,

    -- AI Analysis
    detected_language VARCHAR(10),
    intent VARCHAR(100),
    confidence_score DECIMAL(4,3),
    sentiment VARCHAR(50),

    -- Timing
    start_time_seconds DECIMAL(10,3),
    end_time_seconds DECIMAL(10,3),

    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- HUMAN HANDOFF TABLES
-- ============================================

-- Handoff Events
CREATE TABLE handoff_events (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    hotel_id UUID NOT NULL REFERENCES hotels(id) ON DELETE CASCADE,

    -- Source Reference
    source_type VARCHAR(50) NOT NULL, -- whatsapp, phone_call
    source_id UUID NOT NULL, -- conversation_id or call_id

    -- Handoff Details
    reason VARCHAR(100) NOT NULL, -- low_confidence, complex_request, user_requested, angry_customer, etc.
    reason_details TEXT,
    confidence_score DECIMAL(4,3),
    intent VARCHAR(100),

    -- Customer Context
    customer_phone VARCHAR(50),
    customer_language VARCHAR(10),
    conversation_summary TEXT,

    -- Assignment
    assigned_staff_id UUID REFERENCES hotel_staff(id),
    notification_sent BOOLEAN DEFAULT false,
    notification_channels JSONB DEFAULT '[]', -- ["whatsapp", "telegram", "email"]

    -- Resolution
    status VARCHAR(50) DEFAULT 'pending', -- pending, acknowledged, resolved, expired
    acknowledged_at TIMESTAMP WITH TIME ZONE,
    resolved_at TIMESTAMP WITH TIME ZONE,
    resolution_notes TEXT,

    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- INTENT CLASSIFICATION TABLES
-- ============================================

-- Intent Definitions (customizable per hotel)
CREATE TABLE intent_definitions (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    hotel_id UUID REFERENCES hotels(id) ON DELETE CASCADE, -- NULL for global intents

    intent_code VARCHAR(100) NOT NULL,
    intent_name JSONB NOT NULL, -- {"en": "Room Booking", "sq": "Rezervim Dhome", "it": "Prenotazione Camera"}
    description TEXT,

    -- Handling Rules
    is_complex BOOLEAN DEFAULT false,
    requires_human BOOLEAN DEFAULT false,
    priority VARCHAR(20) DEFAULT 'normal', -- low, normal, high, urgent

    -- Response Templates
    response_templates JSONB DEFAULT '{}', -- {"en": "...", "sq": "...", "it": "..."}

    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Pre-populate common hotel intents
INSERT INTO intent_definitions (intent_code, intent_name, description, is_complex, requires_human) VALUES
('room_availability', '{"en": "Room Availability", "sq": "Disponueshmëria e Dhomës", "it": "Disponibilità Camera"}', 'Check room availability', false, false),
('room_booking', '{"en": "Room Booking", "sq": "Rezervim Dhome", "it": "Prenotazione Camera"}', 'Make a room reservation', false, false),
('booking_modification', '{"en": "Booking Modification", "sq": "Modifikim Rezervimi", "it": "Modifica Prenotazione"}', 'Change existing booking', true, true),
('booking_cancellation', '{"en": "Booking Cancellation", "sq": "Anulim Rezervimi", "it": "Cancellazione Prenotazione"}', 'Cancel a booking', true, true),
('price_inquiry', '{"en": "Price Inquiry", "sq": "Pyetje për Çmimin", "it": "Richiesta Prezzo"}', 'Ask about prices', false, false),
('special_pricing', '{"en": "Special Pricing", "sq": "Çmim Special", "it": "Prezzo Speciale"}', 'Request special rates', true, true),
('group_reservation', '{"en": "Group Reservation", "sq": "Rezervim Grupi", "it": "Prenotazione Gruppo"}', 'Book for groups', true, true),
('amenities_info', '{"en": "Amenities Info", "sq": "Info Komoditete", "it": "Info Servizi"}', 'Hotel facilities info', false, false),
('location_directions', '{"en": "Location & Directions", "sq": "Vendndodhja & Udhëzimet", "it": "Posizione e Indicazioni"}', 'How to reach hotel', false, false),
('check_in_out', '{"en": "Check-in/Check-out", "sq": "Check-in/Check-out", "it": "Check-in/Check-out"}', 'Check-in/out times', false, false),
('complaint', '{"en": "Complaint", "sq": "Ankesë", "it": "Reclamo"}', 'Customer complaint', true, true),
('refund_request', '{"en": "Refund Request", "sq": "Kërkesë Rimbursimi", "it": "Richiesta Rimborso"}', 'Request refund', true, true),
('human_request', '{"en": "Human Request", "sq": "Kërkesë për Njeri", "it": "Richiesta Operatore"}', 'User wants human agent', true, true),
('greeting', '{"en": "Greeting", "sq": "Përshëndetje", "it": "Saluto"}', 'Hello/Hi messages', false, false),
('thank_you', '{"en": "Thank You", "sq": "Faleminderit", "it": "Grazie"}', 'Gratitude expressions', false, false),
('unknown', '{"en": "Unknown", "sq": "E Panjohur", "it": "Sconosciuto"}', 'Unrecognized intent', true, true);

-- ============================================
-- STATISTICS & ANALYTICS TABLES
-- ============================================

-- Daily Statistics (aggregated for performance)
CREATE TABLE daily_statistics (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    hotel_id UUID NOT NULL REFERENCES hotels(id) ON DELETE CASCADE,
    date DATE NOT NULL,

    -- WhatsApp Stats
    whatsapp_messages_received INTEGER DEFAULT 0,
    whatsapp_messages_sent INTEGER DEFAULT 0,
    whatsapp_conversations_started INTEGER DEFAULT 0,
    whatsapp_conversations_resolved INTEGER DEFAULT 0,
    whatsapp_ai_handled INTEGER DEFAULT 0,
    whatsapp_human_handled INTEGER DEFAULT 0,
    whatsapp_avg_response_time_ms INTEGER,

    -- Phone Call Stats
    phone_calls_received INTEGER DEFAULT 0,
    phone_calls_completed INTEGER DEFAULT 0,
    phone_calls_transferred INTEGER DEFAULT 0,
    phone_ai_handled INTEGER DEFAULT 0,
    phone_human_handled INTEGER DEFAULT 0,
    phone_avg_duration_seconds INTEGER,

    -- Handoff Stats
    total_handoffs INTEGER DEFAULT 0,
    handoffs_by_low_confidence INTEGER DEFAULT 0,
    handoffs_by_complex_request INTEGER DEFAULT 0,
    handoffs_by_user_request INTEGER DEFAULT 0,
    handoffs_by_angry_customer INTEGER DEFAULT 0,

    -- Language Distribution (JSON)
    language_distribution JSONB DEFAULT '{"sq": 0, "en": 0, "it": 0}',

    -- Intent Distribution (JSON)
    intent_distribution JSONB DEFAULT '{}',

    -- Sentiment Distribution
    sentiment_distribution JSONB DEFAULT '{"positive": 0, "neutral": 0, "negative": 0, "angry": 0}',

    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,

    UNIQUE(hotel_id, date)
);

-- ============================================
-- TELEGRAM BOT TABLES
-- ============================================

-- Telegram Bot Commands Log
CREATE TABLE telegram_command_logs (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    hotel_id UUID REFERENCES hotels(id) ON DELETE SET NULL,

    telegram_user_id VARCHAR(100) NOT NULL,
    telegram_chat_id VARCHAR(100) NOT NULL,
    command VARCHAR(100) NOT NULL,
    parameters JSONB DEFAULT '{}',

    response_sent BOOLEAN DEFAULT false,
    response_text TEXT,

    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- AUDIT & LOGGING TABLES
-- ============================================

-- System Logs
CREATE TABLE system_logs (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    hotel_id UUID REFERENCES hotels(id) ON DELETE SET NULL,

    log_level VARCHAR(20) NOT NULL, -- debug, info, warning, error, critical
    source VARCHAR(100) NOT NULL, -- workflow_whatsapp, workflow_phone, workflow_telegram, etc.
    message TEXT NOT NULL,
    details JSONB DEFAULT '{}',

    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- API Request Logs (for debugging and rate limiting)
CREATE TABLE api_request_logs (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    hotel_id UUID REFERENCES hotels(id) ON DELETE SET NULL,

    api_name VARCHAR(100) NOT NULL, -- openai, whatsapp, twilio, telegram
    endpoint VARCHAR(255),
    method VARCHAR(10),
    request_payload JSONB,
    response_status INTEGER,
    response_payload JSONB,
    duration_ms INTEGER,

    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- INDEXES FOR PERFORMANCE
-- ============================================

-- Hotels
CREATE INDEX idx_hotels_slug ON hotels(slug);
CREATE INDEX idx_hotels_is_active ON hotels(is_active);

-- WhatsApp Conversations
CREATE INDEX idx_whatsapp_conv_hotel ON whatsapp_conversations(hotel_id);
CREATE INDEX idx_whatsapp_conv_phone ON whatsapp_conversations(phone_number);
CREATE INDEX idx_whatsapp_conv_status ON whatsapp_conversations(status);
CREATE INDEX idx_whatsapp_conv_last_msg ON whatsapp_conversations(last_message_at);

-- WhatsApp Messages
CREATE INDEX idx_whatsapp_msg_conv ON whatsapp_messages(conversation_id);
CREATE INDEX idx_whatsapp_msg_hotel ON whatsapp_messages(hotel_id);
CREATE INDEX idx_whatsapp_msg_created ON whatsapp_messages(created_at);
CREATE INDEX idx_whatsapp_msg_intent ON whatsapp_messages(intent);

-- Phone Calls
CREATE INDEX idx_phone_calls_hotel ON phone_calls(hotel_id);
CREATE INDEX idx_phone_calls_caller ON phone_calls(caller_number);
CREATE INDEX idx_phone_calls_started ON phone_calls(started_at);

-- Handoff Events
CREATE INDEX idx_handoff_hotel ON handoff_events(hotel_id);
CREATE INDEX idx_handoff_status ON handoff_events(status);
CREATE INDEX idx_handoff_created ON handoff_events(created_at);

-- Daily Statistics
CREATE INDEX idx_daily_stats_hotel_date ON daily_statistics(hotel_id, date);

-- System Logs
CREATE INDEX idx_system_logs_hotel ON system_logs(hotel_id);
CREATE INDEX idx_system_logs_level ON system_logs(log_level);
CREATE INDEX idx_system_logs_created ON system_logs(created_at);

-- ============================================
-- FUNCTIONS & TRIGGERS
-- ============================================

-- Function to update 'updated_at' timestamp
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Apply trigger to relevant tables
CREATE TRIGGER update_hotels_updated_at
    BEFORE UPDATE ON hotels
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_hotel_staff_updated_at
    BEFORE UPDATE ON hotel_staff
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_whatsapp_conv_updated_at
    BEFORE UPDATE ON whatsapp_conversations
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_daily_stats_updated_at
    BEFORE UPDATE ON daily_statistics
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();

-- Function to update daily statistics
CREATE OR REPLACE FUNCTION update_daily_whatsapp_stats()
RETURNS TRIGGER AS $$
DECLARE
    stat_date DATE;
BEGIN
    stat_date := DATE(NEW.created_at);

    INSERT INTO daily_statistics (hotel_id, date, whatsapp_messages_received)
    VALUES (NEW.hotel_id, stat_date, 1)
    ON CONFLICT (hotel_id, date)
    DO UPDATE SET
        whatsapp_messages_received = daily_statistics.whatsapp_messages_received +
            CASE WHEN NEW.direction = 'inbound' THEN 1 ELSE 0 END,
        whatsapp_messages_sent = daily_statistics.whatsapp_messages_sent +
            CASE WHEN NEW.direction = 'outbound' THEN 1 ELSE 0 END,
        whatsapp_ai_handled = daily_statistics.whatsapp_ai_handled +
            CASE WHEN NEW.handled_by = 'ai' AND NEW.direction = 'inbound' THEN 1 ELSE 0 END,
        whatsapp_human_handled = daily_statistics.whatsapp_human_handled +
            CASE WHEN NEW.handled_by = 'human' AND NEW.direction = 'inbound' THEN 1 ELSE 0 END,
        updated_at = CURRENT_TIMESTAMP;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trigger_update_daily_whatsapp_stats
    AFTER INSERT ON whatsapp_messages
    FOR EACH ROW
    EXECUTE FUNCTION update_daily_whatsapp_stats();

-- ============================================
-- VIEWS FOR REPORTING
-- ============================================

-- View: Active Conversations Summary
CREATE VIEW v_active_conversations AS
SELECT
    h.name as hotel_name,
    h.slug as hotel_slug,
    wc.phone_number,
    wc.customer_name,
    wc.detected_language,
    wc.current_handler,
    wc.started_at,
    wc.last_message_at,
    (SELECT COUNT(*) FROM whatsapp_messages wm WHERE wm.conversation_id = wc.id) as message_count
FROM whatsapp_conversations wc
JOIN hotels h ON wc.hotel_id = h.id
WHERE wc.status = 'active'
ORDER BY wc.last_message_at DESC;

-- View: Pending Handoffs
CREATE VIEW v_pending_handoffs AS
SELECT
    h.name as hotel_name,
    he.source_type,
    he.reason,
    he.confidence_score,
    he.customer_phone,
    he.customer_language,
    he.conversation_summary,
    hs.name as assigned_staff_name,
    he.created_at
FROM handoff_events he
JOIN hotels h ON he.hotel_id = h.id
LEFT JOIN hotel_staff hs ON he.assigned_staff_id = hs.id
WHERE he.status = 'pending'
ORDER BY he.created_at DESC;

-- View: Weekly Statistics
CREATE VIEW v_weekly_statistics AS
SELECT
    h.name as hotel_name,
    DATE_TRUNC('week', ds.date) as week_start,
    SUM(ds.whatsapp_messages_received) as total_whatsapp_messages,
    SUM(ds.phone_calls_received) as total_phone_calls,
    SUM(ds.whatsapp_ai_handled + ds.phone_ai_handled) as total_ai_handled,
    SUM(ds.whatsapp_human_handled + ds.phone_human_handled) as total_human_handled,
    SUM(ds.total_handoffs) as total_handoffs,
    ROUND(
        SUM(ds.whatsapp_ai_handled + ds.phone_ai_handled)::DECIMAL /
        NULLIF(SUM(ds.whatsapp_messages_received + ds.phone_calls_received), 0) * 100,
        2
    ) as ai_success_rate
FROM daily_statistics ds
JOIN hotels h ON ds.hotel_id = h.id
GROUP BY h.name, DATE_TRUNC('week', ds.date)
ORDER BY week_start DESC;

-- ============================================
-- SAMPLE DATA (for testing)
-- ============================================

-- Insert sample hotel
INSERT INTO hotels (
    name, slug, email, phone, address, city, country,
    staff_whatsapp_number, staff_phone_number,
    confidence_threshold, subscription_plan
) VALUES (
    'Grand Hotel Tirana',
    'grand-hotel-tirana',
    'info@grandhoteltirana.al',
    '+355691234567',
    'Rruga e Kavajes 123',
    'Tirana',
    'Albania',
    '+355691234567',
    '+355691234567',
    0.75,
    'professional'
);

-- Note: In production, API keys should be added securely via admin panel or environment variables

COMMENT ON TABLE hotels IS 'Multi-tenant hotel clients for the SaaS platform';
COMMENT ON TABLE whatsapp_conversations IS 'WhatsApp conversation threads per hotel';
COMMENT ON TABLE whatsapp_messages IS 'Individual WhatsApp messages with AI analysis';
COMMENT ON TABLE phone_calls IS 'Phone call records with transcription and analysis';
COMMENT ON TABLE handoff_events IS 'Human handoff tracking for quality and SLA';
COMMENT ON TABLE daily_statistics IS 'Pre-aggregated daily stats for fast reporting';
