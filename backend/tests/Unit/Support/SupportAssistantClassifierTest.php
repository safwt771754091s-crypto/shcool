<?php

namespace Tests\Unit\Support;

use App\Services\Support\ClaudeClient;
use App\Services\Support\JevClient;
use App\Services\Support\KbRetriever;
use App\Services\Support\PiiRedactor;
use App\Services\Support\SupportAssistantService;
use App\Services\Support\SupportTicketService;
use PHPUnit\Framework\TestCase;

class SupportAssistantClassifierTest extends TestCase
{
    protected SupportAssistantService $assistant;

    protected function setUp(): void
    {
        parent::setUp();

        // The keyword classifier touches none of the collaborators.
        $this->assistant = new SupportAssistantService(
            $this->createMock(JevClient::class),
            $this->createMock(ClaudeClient::class),
            new KbRetriever,
            new PiiRedactor,
            $this->createMock(SupportTicketService::class),
        );
    }

    public function test_it_classifies_billing(): void
    {
        $result = $this->assistant->keywordClassify('كيف أسدد الرسوم الدراسية؟');

        $this->assertSame('billing', $result['intent']);
        $this->assertSame('keyword', $result['provider']);
    }

    public function test_it_classifies_attendance(): void
    {
        $this->assertSame('attendance', $this->assistant->keywordClassify('ابني غائب اليوم')['intent']);
    }

    public function test_it_classifies_a_handoff_request(): void
    {
        $this->assertSame('handoff', $this->assistant->keywordClassify('أريد التحدث مع موظف بشري')['intent']);
    }

    public function test_it_classifies_technical_issues(): void
    {
        $this->assertSame('technical', $this->assistant->keywordClassify('التطبيق لا يعمل ويظهر خطأ')['intent']);
    }

    public function test_english_words_do_not_false_match_a_substring(): void
    {
        // "example.com" contains "exam" — must not be read as a grades issue.
        $result = $this->assistant->keywordClassify('my email is student@example.com');

        $this->assertNotSame('grades', $result['intent']);
    }

    public function test_unknown_text_falls_back_to_general_with_low_confidence(): void
    {
        $result = $this->assistant->keywordClassify('مرحبا');

        $this->assertSame('general', $result['intent']);
        $this->assertLessThan(0.4, $result['confidence']);
    }
}
