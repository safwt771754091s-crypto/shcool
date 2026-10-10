<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI agents (وكلاء الذكاء الاصطناعي)
    |--------------------------------------------------------------------------
    |
    | The platform is provider-agnostic: any OpenAI-compatible chat-completions
    | endpoint works (OpenAI, Groq, Together, OpenRouter, a local vLLM/Ollama
    | server, ...). When no API key is configured the AI features degrade
    | gracefully instead of breaking — the API reports `available: false` and
    | the assistant answers with a helpful notice rather than failing.
    |
    */

    'enabled' => (bool) env('AI_ENABLED', true),

    'base_url' => env('AI_BASE_URL', 'https://api.openai.com/v1'),

    'api_key' => env('AI_API_KEY'),

    'model' => env('AI_MODEL', 'gpt-4o-mini'),

    // Seconds to wait for the upstream model.
    'timeout' => (int) env('AI_TIMEOUT', 60),

    // How many of the most recent messages to replay as context.
    'history_limit' => (int) env('AI_HISTORY_LIMIT', 20),

    // Safety valve for the agent loop: never call the model more than this many
    // times while resolving one user message.
    'max_steps' => (int) env('AI_MAX_STEPS', 4),

    /*
    |--------------------------------------------------------------------------
    | Agent personas
    |--------------------------------------------------------------------------
    |
    | Each agent is a system prompt + the tools it is allowed to call. Tools are
    | resolved by name from App\Services\Ai\AiToolbox and filtered by the signed
    | in user's permissions before use, so a parent can never reach a
    | management tool even if the prompt mentions it.
    |
    */
    'agents' => [

        'manager_assistant' => [
            'label' => 'مساعد الإدارة',
            'description' => 'مساعد مدير المدرسة: تقارير، إحصاءات، حضور، مالية، وترتيب.',
            'roles' => ['school_manager', 'branch_manager', 'vice_principal', 'owner', 'super_admin', 'ministry_admin', 'directorate_admin', 'governorate_admin', 'minister'],
            'tools' => ['school_overview', 'student_count', 'attendance_summary', 'list_students', 'finance_summary', 'list_unpaid_invoices', 'top_students'],
            'system' => 'أنت "مساعد الإدارة" في منصة المدرسة الرقمية. تتحدث بالعربية الفصحى المختصرة. '
                .'تساعد مدير المدرسة في قراءة البيانات الحقيقية عبر الأدوات المتاحة. '
                .'لا تخترع أرقاماً أبداً: إن احتجت معلومة فاستدعِ الأداة المناسبة، وإذا لم تُرجع الأداة بيانات فاذكر ذلك. '
                .'التزم بالبيانات الخاصة بمدرستك فقط. اعرض النتائج كجداول أو قوائم عربية موجزة مع الأرقام.',
        ],

        'teacher_assistant' => [
            'label' => 'مساعد المعلم',
            'description' => 'مساعد المعلم: الحضور، الدرجات، ومتابعة الشعب.',
            'roles' => ['teacher', 'teacher_assistant'],
            'tools' => ['my_assignments', 'attendance_summary', 'student_count', 'top_students'],
            'system' => 'أنت "مساعد المعلم" في منصة المدرسة الرقمية. تساعد المعلم في متابعة نصابه، '
                .'حضور شعبه، ونتائج طلابه عبر الأدوات المتاحة. لا تخترع أرقاماً؛ استدعِ الأداة ثم لخّص بالعربية.',
        ],

        'parent_assistant' => [
            'label' => 'مساعد ولي الأمر',
            'description' => 'مساعد ولي الأمر: نتائج الأبناء، الحضور، والرسوم.',
            'roles' => ['parent'],
            'tools' => ['my_children'],
            'system' => 'أنت "مساعد ولي الأمر". تجيب عن أسئلة ولي الأمر حول أبنائه فقط (النتائج، الحضور، الرسوم المتبقية) '
                .'عبر أداة my_children. التزم ببيانات أبناء هذا المستخدم فقط ولا تكشف أي بيانات لطلاب آخرين. بالعربية.',
        ],

        'student_assistant' => [
            'label' => 'مساعد الطالب',
            'description' => 'مساعد الطالب: نتيجتي، حضوري، وموقعي في الترتيب.',
            'roles' => ['student'],
            'tools' => ['my_children'],
            'system' => 'أنت "مساعد الطالب". تجيب الطالب عن بياناته الشخصية فقط (النتائج، الحضور، الرسوم) عبر أداة my_children. '
                .'لا تكشف بيانات أي طالب آخر. شجّعه بلطف ولخّص بالعربية.',
        ],

        'reports_assistant' => [
            'label' => 'مستشار التقارير',
            'description' => 'تحليل ومقارنة بين المدارس والمديريات (لمستوى الوزارة).',
            'roles' => ['minister', 'ministry_admin', 'owner', 'super_admin', 'governorate_admin', 'directorate_admin'],
            'tools' => ['national_overview', 'top_students'],
            'system' => 'أنت "مستشار التقارير" لمستوى الوزارة/المديرية. تقدّم تحليلات ومقارنات مبنية على الأداة national_overview. '
                .'لا تخترع أرقاماً، ولخّص بالعربية مع أفضل/أضعف الجهات.',
        ],
    ],

];
