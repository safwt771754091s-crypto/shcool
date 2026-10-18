<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Customer service assistant (مساعد خدمة العملاء)
    |--------------------------------------------------------------------------
    |
    | A bilingual (Arabic-first) support assistant that sits alongside the
    | internal AI agents. It answers from an approved knowledge base, classifies
    | each message (Jev), drafts a reply (Claude), and escalates to a human
    | ticket when it is not confident or the user asks for a person.
    |
    | It is designed to degrade gracefully: with no keys it still answers from
    | the knowledge base using a deterministic keyword classifier and canned
    | templates, so the feature is safe to ship before the keys arrive.
    |
    */

    'enabled' => (bool) env('SUPPORT_ASSISTANT_ENABLED', true),

    // Minimum classifier confidence before an answer is trusted; below it the
    // assistant offers to open a ticket instead of guessing.
    'confidence_threshold' => (float) env('SUPPORT_CONFIDENCE_THRESHOLD', 0.55),

    // Confidence below which the assistant proactively offers a human ticket.
    'escalate_threshold' => (float) env('SUPPORT_ESCALATE_THRESHOLD', 0.4),

    // How many prior turns of the thread to replay as context.
    'history_limit' => (int) env('SUPPORT_HISTORY_LIMIT', 12),

    // How many knowledge-base passages to retrieve per question.
    'max_sources' => (int) env('SUPPORT_MAX_SOURCES', 3),

    /*
    |--------------------------------------------------------------------------
    | Jev (TypeSafe System One) — intent / sentiment classification
    |--------------------------------------------------------------------------
    |
    | Jev returns a typed decision (a Choice label plus a calibrated confidence)
    | instead of free text, which is exactly what intent routing needs. When no
    | key is set, SupportAssistantService falls back to a keyword classifier.
    |
    */
    'jev' => [
        'base_url' => env('JEV_BASE_URL', 'https://api.typesafe.ai/v1'),
        'api_key' => env('JEV_API_KEY'),
        'model' => env('JEV_MODEL', 'jev-latest'),
        'timeout' => (int) env('JEV_TIMEOUT', 20),
    ],

    /*
    |--------------------------------------------------------------------------
    | Claude (Anthropic Messages API) — reply drafting
    |--------------------------------------------------------------------------
    |
    | Used to draft a grounded, Arabic-first reply from the retrieved knowledge
    | passages. When no key is set, the top knowledge passage is returned as the
    | answer instead.
    |
    */
    'claude' => [
        'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'),
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-5'),
        'version' => env('ANTHROPIC_VERSION', '2023-06-01'),
        'max_tokens' => (int) env('SUPPORT_CLAUDE_MAX_TOKENS', 1024),
        'timeout' => (int) env('SUPPORT_CLAUDE_TIMEOUT', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Intent catalogue (Arabic labels)
    |--------------------------------------------------------------------------
    |
    | Each intent maps to a set of knowledge-base categories it should retrieve
    | from, and whether it can be fully answered by the assistant. `handoff`
    | always escalates to a human ticket.
    |
    */
    'intents' => [
        'login' => ['label' => 'تسجيل الدخول والحساب', 'categories' => ['account', 'auth']],
        'billing' => ['label' => 'الرسوم والمدفوعات', 'categories' => ['billing', 'payments']],
        'attendance' => ['label' => 'الحضور والغياب', 'categories' => ['attendance']],
        'grades' => ['label' => 'الدرجات والنتائج', 'categories' => ['grades', 'exams']],
        'admission' => ['label' => 'القبول والتسجيل', 'categories' => ['admission']],
        'technical' => ['label' => 'مشكلة تقنية', 'categories' => ['technical']],
        'general' => ['label' => 'استفسار عام', 'categories' => ['general']],
        'handoff' => ['label' => 'تحويل إلى موظف', 'categories' => [], 'handoff' => true],
    ],

    /*
    |--------------------------------------------------------------------------
    | Topics that must never be auto-answered
    |--------------------------------------------------------------------------
    |
    | These are always routed to a human agent regardless of classifier
    | confidence: legal threats, data/privacy requests, and safeguarding.
    |
    */
    'always_handoff' => [
        'شكوى قانونية', 'مقاضاة', 'محامي', 'تسريب بيانات', 'اختراق', 'تنمر', 'تحرش', 'عنف',
    ],
];
