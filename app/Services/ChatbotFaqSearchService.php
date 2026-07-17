<?php

namespace App\Services;

use App\Models\ChatbotFaq;
use Illuminate\Support\Str;

class ChatbotFaqSearchService
{
    /** @var list<string> */
    private const STOP_WORDS = [
        'a', 'an', 'the', 'is', 'are', 'was', 'were', 'be', 'been', 'being',
        'i', 'you', 'we', 'they', 'he', 'she', 'it', 'my', 'your', 'our',
        'do', 'does', 'did', 'can', 'could', 'will', 'would', 'should',
        'how', 'what', 'when', 'where', 'why', 'which', 'who',
        'to', 'for', 'of', 'in', 'on', 'at', 'by', 'with', 'from', 'about',
        'get', 'give', 'tell', 'me', 'please', 'want', 'need', 'know',
        // Marathi / Hindi conversational fillers
        'mi', 'mala', 'mujhe', 'mujhko', 'kas', 'kase', 'kaise', 'ka', 'ke', 'ki', 'ko',
        'karu', 'karaycha', 'karnaa', 'karna', 'shakto', 'shakte', 'sakto', 'sakte',
        'aahe', 'ahe', 'hai', 'hain', 'hot', 'hoto', 'hote', 'cha', 'che', 'chi',
        'pan', 'ani', 'and', 'or', 'the', 'na', 'nahi', 'nahin',
    ];

    /**
     * @return list<array{id: int, question: string, answer: string, category: string|null, score: float}>
     */
    public function search(
        string $query,
        string $language = 'en',
        ?string $category = null,
        int $limit = 5,
        ?string $audience = null,
    ): array {
        $query = trim(Str::lower($query));
        if ($query === '') {
            return [];
        }

        $tokens = $this->tokenize($query);
        $portalCategory = $this->portalCategoryForRole($audience);

        $faqs = ChatbotFaq::query()
            ->where('is_active', true)
            ->where('language', $language)
            ->when($category, fn ($builder) => $builder->where('category', $category))
            ->when($portalCategory === null, fn ($builder) => $builder->where('category', 'not like', 'portal\_%'))
            ->orderBy('sort_order')
            ->get();

        return $faqs
            ->map(function (ChatbotFaq $faq) use ($query, $tokens, $portalCategory): array {
                $score = $this->scoreFaq($faq, $query, $tokens, $portalCategory);

                return [
                    'id' => $faq->id,
                    'question' => $faq->question,
                    'answer' => $faq->answer,
                    'category' => $faq->category,
                    'score' => $score,
                ];
            })
            ->filter(fn (array $item): bool => $item['score'] > 0)
            ->sortByDesc('score')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function tokenize(string $query): array
    {
        $parts = array_values(array_filter(preg_split('/\s+/u', $query) ?: []));

        return array_values(array_filter(
            $parts,
            fn (string $token): bool => ! in_array($token, self::STOP_WORDS, true) && mb_strlen($token) > 1,
        ));
    }

    /**
     * @param  list<string>  $tokens
     */
    private function scoreFaq(ChatbotFaq $faq, string $query, array $tokens, ?string $portalCategory): float
    {
        $question = Str::lower($faq->question);
        $answer = Str::lower($faq->answer);
        $keywords = Str::lower((string) $faq->keywords);
        $category = (string) $faq->category;

        $score = 0.0;

        if ($question === $query) {
            $score += 100;
        }

        if (mb_strlen($query) >= 4 && Str::contains($question, $query)) {
            $score += 45;
        }

        if (mb_strlen($query) >= 4 && $this->containsPhrase($keywords, $query)) {
            $score += 40;
        }

        foreach ($tokens as $token) {
            if ($this->containsWord($question, $token)) {
                $score += 14;
            }
            if ($this->containsWord($keywords, $token) || $this->containsDelimitedKeyword($keywords, $token)) {
                $score += 22;
            }
            if (mb_strlen($token) >= 4 && $this->containsWord($answer, $token)) {
                $score += 5;
            }
        }

        if ($tokens !== []) {
            $matchedTokens = 0;
            foreach ($tokens as $token) {
                if ($this->containsWord($question, $token)
                    || $this->containsWord($keywords, $token)
                    || $this->containsDelimitedKeyword($keywords, $token)) {
                    $matchedTokens++;
                }
            }

            if ($matchedTokens === count($tokens) && count($tokens) >= 2) {
                $score += 20;
            }
        }

        if ($portalCategory !== null) {
            if ($category === $portalCategory) {
                $score += 40;
            } elseif (str_starts_with($category, 'portal_')) {
                $score -= 35;
            }
        }

        return max(0.0, $score);
    }

    private function containsWord(string $haystack, string $needle): bool
    {
        if ($needle === '' || $haystack === '') {
            return false;
        }

        $pattern = '/(?<![\p{L}\p{N}_])'.preg_quote($needle, '/').'(?![\p{L}\p{N}_])/u';

        return (bool) preg_match($pattern, $haystack);
    }

    private function containsPhrase(string $haystack, string $phrase): bool
    {
        if ($phrase === '' || $haystack === '') {
            return false;
        }

        return $this->containsWord($haystack, $phrase) || Str::contains($haystack, $phrase);
    }

    /** Keywords are comma-separated — match whole keyword segments only. */
    private function containsDelimitedKeyword(string $keywords, string $token): bool
    {
        foreach (array_map('trim', explode(',', $keywords)) as $segment) {
            if ($segment === '') {
                continue;
            }
            if ($segment === $token || $this->containsWord($segment, $token)) {
                return true;
            }
        }

        return false;
    }

    private function portalCategoryForRole(?string $role): ?string
    {
        if ($role === null || $role === '') {
            return null;
        }

        return in_array($role, ['admin', 'employee', 'client', 'hr'], true)
            ? 'portal_'.$role
            : null;
    }
}
