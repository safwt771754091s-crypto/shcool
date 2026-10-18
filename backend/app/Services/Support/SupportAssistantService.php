<?php

namespace App\Services\Support;

use App\Models\Support\SupportConversation;
use App\Models\Support\SupportMessage;
use App\Models\Support\SupportTicket;
use App\Models\User;
use App\Support\Tenancy\Scopes\TenantScope;
use Illuminate\Support\Collection;
use Throwable;

/**
 * The customer-service assistant (مساعد خدمة العملاء).
 *
 * One turn:
 *   1. classify the message into an intent (Jev, else keyword fallback),
 *   2. retrieve the most relevant approved knowledge passages,
 *   3. draft a grounded, Arabic-first reply (Claude, else the top passage),
 *   4. decide whether to answer or escalate to a human ticket.
 *
 * Every external call is best-effort: a provider outage degrades to the
 * deterministic path instead of failing, and PII is redacted before any text is
 * sent off-platform. The full text is always stored locally for audit.
 */
class SupportAssistantService
{
    public function __construct(
        protected JevClient $jev,
        protected ClaudeClient $claude,
        protected KbRetriever $retriever,
        protected PiiRedactor $redactor,
        protected SupportTicketService $tickets,
    ) {}

    public function isAvailable(): bool
    {
        return (bool) config('support.enabled', true);
    }

    /**
     * Answer one user message inside a conversation.
     *
     * @return array{
     *     reply: SupportMessage,
     *     intent: string,
     *     confidence: float,
     *     sources: list<array>,
     *     escalate: bool,
     *     ticket: ?SupportTicket,
     *     providers: array<string, bool>
     * }
     */
    public function respond(SupportConversation $conversation, User $user, string $userMessage): array
    {
        $locale = $conversation->locale ?: 'ar';

        $userTurn = SupportMessage::create([
            'conversation_id' => $conversation->getKey(),
            'role' => SupportMessage::ROLE_USER,
            'content' => $userMessage,
        ]);

        $conversation->forceFill([
            'last_message_at' => now(),
            'title' => $conversation->title ?: mb_substr($userMessage, 0, 60),
        ])->save();

        $classification = $this->classify($userMessage, $locale);
        $intent = $classification['intent'];
        $confidence = $classification['confidence'];

        $sources = $intent === 'handoff'
            ? []
            : $this->retriever->search(
                $userMessage,
                config("support.intents.{$intent}.categories", []),
                (int) config('support.max_sources', 3),
            );

        $escalate = $this->shouldEscalate($userMessage, $intent, $confidence, $sources);

        [$content, $meta] = $this->draft($conversation, $user, $userMessage, $intent, $confidence, $sources, $escalate, $locale);

        $ticket = null;

        if ($escalate) {
            $ticket = $this->tickets->open($user, [
                'subject' => mb_substr($userMessage, 0, 80),
                'body' => $userMessage,
                'category' => $intent,
                'conversation_id' => $conversation->getKey(),
            ]);
            $this->tickets->acknowledge($ticket);
            $content .= "\n\n".$this->ticketNotice($ticket);
        }

        $reply = SupportMessage::create([
            'conversation_id' => $conversation->getKey(),
            'role' => SupportMessage::ROLE_ASSISTANT,
            'content' => $content,
            'intent' => $intent,
            'confidence' => $confidence,
            'sources' => $this->publicSources($sources),
            'meta' => $meta + ['escalated' => $escalate, 'ticket_reference' => $ticket?->reference],
        ]);

        $conversation->forceFill(['intent' => $intent])->save();

        return [
            'reply' => $reply,
            'intent' => $intent,
            'confidence' => $confidence,
            'sources' => $this->publicSources($sources),
            'escalate' => $escalate,
            'ticket' => $ticket,
            'providers' => [
                'jev' => $this->jev->isConfigured(),
                'claude' => $this->claude->isConfigured(),
            ],
        ];
    }

    /**
     * @return array{intent: string, confidence: float, provider: string}
     */
    protected function classify(string $text, string $locale): array
    {
        $intents = array_keys(config('support.intents', []));

        try {
            $result = $this->jev->classify($text, $intents, $locale);

            if ($result !== null && filled($result['intent'])) {
                return [
                    'intent' => (string) $result['intent'],
                    'confidence' => (float) $result['confidence'],
                    'provider' => 'jev',
                ];
            }
        } catch (Throwable) {
            // Fall through to the keyword classifier.
        }

        return $this->keywordClassify($text);
    }

    /**
     * Deterministic Arabic/English keyword classifier used when Jev is absent
     * or errors. Confidence is a soft signal, capped below the trust threshold
     * so a low-signal match still offers a human.
     *
     * @return array{intent: string, confidence: float, provider: string}
     */
    public function keywordClassify(string $text): array
    {
        $tokens = $this->retriever->tokens($text);
        $stems = $this->stems($tokens);
        // Punctuation-free, space-padded token stream for multi-word cues.
        $tokenString = ' '.implode(' ', $tokens).' ';

        $cues = [
            'billing' => ['فاتوره', 'رسوم', 'سداد', 'سدد', 'دفع', 'مبلغ', 'استرداد', 'رصيد', 'مدفوع', 'payment', 'invoice', 'refund'],
            'attendance' => ['حضور', 'غياب', 'غائب', 'تاخير', 'انصراف', 'attendance', 'absent', 'late'],
            'grades' => ['درجه', 'درجات', 'نتيجه', 'نتائج', 'اختبار', 'علامه', 'رسوب', 'نجاح', 'grade', 'grades', 'exam', 'exams', 'result', 'results'],
            'admission' => ['تسجيل', 'قبول', 'التحاق', 'ملف', 'transfer', 'admission', 'enroll'],
            'login' => ['دخول', 'كلمه المرور', 'حساب', 'رمز', 'مصادقه', 'ثنائيه', '2fa', 'login', 'password', 'account', 'verify'],
            'technical' => ['خطا', 'مشكله', 'تعطل', 'بطيء', 'error', 'bug', 'crash', 'slow'],
            'handoff' => ['موظف', 'انسان', 'شخص', 'مسؤول', 'ممثل', 'agent', 'human', 'representative'],
        ];

        $best = 'general';
        $bestHits = 0;

        foreach ($cues as $intent => $words) {
            $hits = 0;

            foreach ($words as $word) {
                $cue = $this->retriever->normalise($word);

                // Multi-word cues match on token boundaries; single words match
                // on stems (so "الرسوم" ~ "رسوم", "أسدد" ~ "سدد").
                $hits += str_contains($cue, ' ')
                    ? (str_contains($tokenString, ' '.$cue.' ') ? 1 : 0)
                    : ($this->stemsMatch($stems, $cue) ? 1 : 0);
            }

            if ($hits > $bestHits) {
                $bestHits = $hits;
                $best = $intent;
            }
        }

        // 2+ cue hits -> moderately confident; a single hit stays low.
        $confidence = match (true) {
            $bestHits >= 3 => 0.7,
            $bestHits === 2 => 0.55,
            $bestHits === 1 => 0.35,
            default => 0.15,
        };

        return ['intent' => $best, 'confidence' => $confidence, 'provider' => 'keyword'];
    }

    /**
     * Map tokens to light stems (definite-article prefixes removed).
     *
     * @param  list<string>  $tokens
     * @return array<string, true>
     */
    protected function stems(array $tokens): array
    {
        $out = [];

        foreach ($tokens as $token) {
            $out[$this->stem($token)] = true;
        }

        return $out;
    }

    protected function stem(string $token): string
    {
        foreach (['وال', 'بال', 'كال', 'فال', 'ال'] as $prefix) {
            $length = mb_strlen($prefix);

            if (mb_strlen($token) > $length + 2 && str_starts_with($token, $prefix)) {
                return mb_substr($token, $length);
            }
        }

        return $token;
    }

    /**
     * A cue matches a token when their stems are equal, or (Arabic only, to
     * avoid English false positives like "exam" in "example") the token stem
     * begins with the cue stem.
     */
    protected function stemsMatch(array $stems, string $cue): bool
    {
        $cueStem = $this->stem($cue);

        if (isset($stems[$cueStem])) {
            return true;
        }

        if (mb_strlen($cueStem) < 3 || ! preg_match('/\p{Arabic}/u', $cueStem)) {
            return false;
        }

        foreach (array_keys($stems) as $stem) {
            if (str_starts_with($stem, $cueStem)) {
                return true;
            }
        }

        return false;
    }

    protected function shouldEscalate(string $text, string $intent, float $confidence, array $sources): bool
    {
        // Explicit request for a human.
        if ($intent === 'handoff') {
            return true;
        }

        // Sensitive topics always go to a human.
        $normalised = $this->retriever->normalise($text);

        foreach ((array) config('support.always_handoff', []) as $topic) {
            if (str_contains($normalised, $this->retriever->normalise($topic))) {
                return true;
            }
        }

        // No approved knowledge to answer from.
        if ($sources === []) {
            return true;
        }

        // The classifier is unsure and nothing scored well.
        $bestScore = (float) ($sources[0]['score'] ?? 0.0);
        $floor = (float) config('support.escalate_threshold', 0.4);

        return $confidence < $floor && $bestScore < 0.5;
    }

    /**
     * Draft the reply. Claude when available, otherwise the top passage.
     *
     * @param  list<array>  $sources
     * @return array{0: string, 1: array<string, mixed>}
     */
    protected function draft(
        SupportConversation $conversation,
        User $user,
        string $userMessage,
        string $intent,
        float $confidence,
        array $sources,
        bool $escalate,
        string $locale,
    ): array {
        if ($escalate && $sources === []) {
            return [$this->escalationMessage($intent), ['provider' => 'template']];
        }

        $redacted = $this->redactor->redact($userMessage);

        try {
            if ($this->claude->isConfigured()) {
                $result = $this->claude->complete(
                    $this->systemPrompt($locale),
                    $this->buildClaudeMessages($conversation, $redacted['text'], $sources, $userMessage),
                );

                if (filled($result['content'])) {
                    return [
                        $result['content'],
                        [
                            'provider' => 'claude',
                            'model' => $result['model'],
                            'pii_redacted' => $redacted['found'],
                        ],
                    ];
                }
            }
        } catch (Throwable $e) {
            // Degrade to the knowledge passage.
        }

        if ($sources !== []) {
            $top = $sources[0];

            return [
                $top['answer'],
                [
                    'provider' => 'kb',
                    'pii_redacted' => $redacted['found'],
                ],
            ];
        }

        return [$this->escalationMessage($intent), ['provider' => 'template']];
    }

    protected function systemPrompt(string $locale): string
    {
        return 'أنت "مساعد خدمة العملاء" في منصة المدرسة الرقمية. '
            .'تجيب بالعربية الفصحى المختصرة والمهذبة. '
            .'اعتمد حصراً على المقاطع المعرفية المرفقة؛ لا تخترع أي معلومة أو رقم. '
            .'إذا لم تكفِ المقاطع، فاذكر ذلك بوضوح واقترح تحويل الطلب إلى موظف دعم بشري. '
            .'لا تطلب ولا تكرر أي بيانات شخصية حساسة (رقم هوية، هاتف، بريد، بطاقة). '
            .'الرسالة النهائية موجزة (٣ إلى ٦ أسطر) ومهيّأة للعرض في محادثة.';
    }

    /**
     * @param  list<array>  $sources
     * @return list<array{role: string, content: string}>
     */
    protected function buildClaudeMessages(SupportConversation $conversation, string $message, array $sources, string $rawUserMessage): array
    {
        $messages = [];

        $history = $conversation->messages()
            ->withoutGlobalScope(TenantScope::class)
            ->whereIn('role', [SupportMessage::ROLE_USER, SupportMessage::ROLE_ASSISTANT])
            ->orderByDesc('id')
            ->limit((int) config('support.history_limit', 12))
            ->get()
            ->reverse()
            ->values();

        foreach ($history as $turn) {
            // The just-stored user turn is appended below with the passages.
            if ($turn->role === SupportMessage::ROLE_USER && $turn->content === $rawUserMessage) {
                continue;
            }

            // History is redacted too: prior turns must never leak PII either.
            $messages[] = [
                'role' => $turn->role === SupportMessage::ROLE_ASSISTANT ? 'assistant' : 'user',
                'content' => $this->redactor->redact((string) $turn->content)['text'],
            ];
        }

        $context = collect($sources)
            ->map(fn ($s) => "- (المصدر #{$s['id']}) {$s['question']}: {$s['answer']}")
            ->implode("\n");

        $messages[] = [
            'role' => 'user',
            'content' => "المقاطع المعرفية المعتمدة:\n{$context}\n\nرسالة العميل: {$message}",
        ];

        return $messages;
    }

    protected function escalationMessage(string $intent): string
    {
        return 'لم أجد إجابة موثوقة في قاعدة المعرفة لهذا الطلب، لذلك سأحوّله إلى أحد موظفي الدعم لمساعدتك مباشرةً.';
    }

    protected function ticketNotice(SupportTicket $ticket): string
    {
        return "🎫 تم إنشاء تذكرة دعم برقم المرجع: {$ticket->reference}. سيتواصل معك أحد موظفي الدعم قريباً.";
    }

    /**
     * @param  list<array>  $sources
     * @return list<array{id: int, question: string, category: string, score: float}>
     */
    protected function publicSources(array $sources): array
    {
        return Collection::make($sources)->map(fn ($s) => [
            'id' => $s['id'],
            'question' => $s['question'],
            'category' => $s['category'],
            'score' => $s['score'],
        ])->all();
    }
}
