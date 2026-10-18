<?php

namespace App\Services\Support;

use App\Models\Support\KbArticle;
use Illuminate\Support\Str;

/**
 * Retrieves the most relevant approved knowledge-base passages for a question.
 *
 * Deliberately dependency-free: it uses an Arabic-aware tokeniser plus a small
 * TF weighting instead of an external vector store, so it works with zero
 * infrastructure and never leaves the tenant. The scoring is intentionally
 * transparent (auditable) rather than a black box.
 */
class KbRetriever
{
    /**
     * @return list<array{id: int, question: string, answer: string, category: string, score: float}>
     */
    public function search(string $query, array $categories = [], int $limit = 3): array
    {
        $tokens = $this->tokens($query);

        if ($tokens === []) {
            return [];
        }

        $articles = KbArticle::query()
            ->published()
            ->when($categories !== [], fn ($q) => $q->whereIn('category', $categories))
            ->get();

        $scored = [];

        foreach ($articles as $article) {
            $score = $this->score($tokens, $article);

            if ($score <= 0) {
                continue;
            }

            $scored[] = [
                'id' => (int) $article->getKey(),
                'question' => (string) $article->question,
                'answer' => (string) $article->answer,
                'category' => (string) $article->category,
                'score' => $score,
            ];
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, max(1, $limit));
    }

    /**
     * Weighted overlap: the question and the explicit keywords matter most, the
     * answer body least. Priority is a small tie-breaker.
     */
    protected function score(array $queryTokens, KbArticle $article): float
    {
        $question = $this->tokens((string) $article->question);
        $keywords = $this->tokens(implode(' ', (array) ($article->keywords ?? [])));
        $answer = $this->tokens((string) $article->answer);

        $query = array_unique($queryTokens);

        $questionHits = count(array_intersect($query, $question));
        $keywordHits = count(array_intersect($query, $keywords));
        $answerHits = count(array_intersect($query, $answer));

        $score = ($questionHits * 3.0) + ($keywordHits * 2.5) + ($answerHits * 1.0);

        if ($score <= 0) {
            return 0.0;
        }

        // Normalise by the query length so long questions do not dominate, then
        // nudge by priority. Kept in a 0..1-ish band for the confidence gate.
        $normalised = $score / max(1, count($query));
        $boost = 1 + (((int) $article->priority) / 100);

        return round(min(1.0, $normalised / 3) * $boost, 4);
    }

    /**
     * Arabic-aware tokenisation: strip diacritics/tatweel, unify alef/yaa/taa
     * marbuta forms, drop stop-words, keep tokens of length >= 2.
     *
     * @return list<string>
     */
    public function tokens(string $text): array
    {
        $text = $this->normalise($text);

        $parts = preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $tokens = [];

        foreach ($parts as $part) {
            if (mb_strlen($part) < 2 || in_array($part, $this->stopWords(), true)) {
                continue;
            }

            $tokens[] = $part;
        }

        return $tokens;
    }

    public function normalise(string $text): string
    {
        $text = Str::lower($text);

        // Diacritics (tashkeel) and tatweel.
        $text = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}\x{0640}]/u', '', $text) ?? $text;

        // Unify alef variants, yaa variants, taa marbuta.
        $text = str_replace(['أ', 'إ', 'آ', 'ٱ'], 'ا', $text);
        $text = str_replace(['ى'], 'ي', $text);
        $text = str_replace(['ة'], 'ه', $text);

        // Arabic-Indic digits to ASCII.
        $text = str_replace(
            ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'],
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            $text
        );

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /**
     * @return list<string>
     */
    protected function stopWords(): array
    {
        return [
            'من', 'في', 'على', 'عن', 'الى', 'إلى', 'مع', 'هل', 'ما', 'ماذا', 'كيف', 'لماذا',
            'اين', 'متى', 'هو', 'هي', 'انا', 'انت', 'نحن', 'هذا', 'هذه', 'ذلك', 'التي', 'الذي',
            'ان', 'او', 'و', 'ثم', 'لكن', 'اذا', 'كل', 'بعد', 'قبل', 'عند', 'لدي', 'عندي',
            'the', 'and', 'for', 'you', 'are', 'can', 'how', 'what', 'why', 'where', 'when',
        ];
    }
}
