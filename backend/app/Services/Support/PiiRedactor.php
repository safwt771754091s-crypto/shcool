<?php

namespace App\Services\Support;

/**
 * Redacts personally identifiable information before any text leaves the
 * platform to a third-party model provider (Jev / Claude).
 *
 * The customer-service assistant must never forward national IDs, phone
 * numbers, emails, IBANs or card-like digit runs to an external API. The
 * redaction is best-effort and reversible only in the sense that the original
 * text is kept in our own database — never in the outbound payload.
 */
class PiiRedactor
{
    /**
     * @return array{text: string, found: list<string>}
     */
    public function redact(string $text): array
    {
        $found = [];

        $patterns = [
            'email' => '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/u',
            // Saudi mobile: 05xxxxxxxx, +9665xxxxxxxx, 9665xxxxxxxx.
            'phone' => '/(?:\+?966|0)?5\d{8}\b/u',
            // IBAN (Saudi + generic) and long digit runs (national id / card).
            'iban' => '/\bSA\d{22}\b/iu',
            'card' => '/\b\d{13,19}\b/u',
            'national_id' => '/\b[12]\d{9}\b/u',
        ];

        $redacted = $text;

        foreach ($patterns as $label => $pattern) {
            $redacted = preg_replace_callback($pattern, function ($m) use ($label, &$found) {
                $found[] = $label;

                return '[محجوب:'.$label.']';
            }, $redacted) ?? $redacted;
        }

        return ['text' => $redacted, 'found' => array_values(array_unique($found))];
    }

    /** True when the text contains anything that looks like PII. */
    public function hasPii(string $text): bool
    {
        return $this->redact($text)['found'] !== [];
    }
}
