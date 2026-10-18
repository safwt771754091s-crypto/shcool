<?php

namespace Tests\Unit\Support;

use App\Services\Support\PiiRedactor;
use PHPUnit\Framework\TestCase;

class PiiRedactorTest extends TestCase
{
    public function test_it_redacts_saudi_mobile_numbers(): void
    {
        $result = (new PiiRedactor)->redact('رقمي 0555123456 اتصل بي');

        $this->assertStringNotContainsString('0555123456', $result['text']);
        $this->assertContains('phone', $result['found']);
    }

    public function test_it_redacts_international_saudi_numbers(): void
    {
        $result = (new PiiRedactor)->redact('+966512345678');

        $this->assertStringNotContainsString('512345678', $result['text']);
        $this->assertContains('phone', $result['found']);
    }

    public function test_it_redacts_emails(): void
    {
        $result = (new PiiRedactor)->redact('راسلني على student@example.com');

        $this->assertStringNotContainsString('student@example.com', $result['text']);
        $this->assertContains('email', $result['found']);
    }

    public function test_it_redacts_national_ids_and_ibans(): void
    {
        $result = (new PiiRedactor)->redact('هويتي 1234567890 وآيبان SA0380000000608010167519');

        $this->assertStringNotContainsString('1234567890', $result['text']);
        $this->assertStringNotContainsString('SA0380000000608010167519', $result['text']);
        $this->assertContains('national_id', $result['found']);
        $this->assertContains('iban', $result['found']);
    }

    public function test_it_leaves_ordinary_text_untouched(): void
    {
        $result = (new PiiRedactor)->redact('كيف أسدد الرسوم الدراسية؟');

        $this->assertSame('كيف أسدد الرسوم الدراسية؟', $result['text']);
        $this->assertSame([], $result['found']);
    }
}
