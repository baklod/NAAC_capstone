<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProfitAnalysisInsightController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'summary' => ['required', 'array'],
            'summary.packed_label' => ['nullable', 'string', 'max:60'],
            'summary.best_selling_method' => ['nullable', 'string', 'max:80'],
            'summary.total_sales_pack' => ['nullable', 'numeric'],
            'summary.total_sales_kilo' => ['nullable', 'numeric'],
            'summary.profit_difference' => ['nullable', 'numeric'],
            'summary.total_weight_sold_kg' => ['nullable', 'numeric'],
            'summary.rows' => ['nullable', 'array'],
            'summary.rows.*.product' => ['nullable', 'string', 'max:120'],
            'summary.rows.*.type' => ['nullable', 'string', 'max:80'],
            'summary.rows.*.qty_sold' => ['nullable', 'numeric'],
            'summary.rows.*.average_price' => ['nullable', 'numeric'],
            'summary.rows.*.total' => ['nullable', 'numeric'],
            'fallback_insights' => ['nullable', 'array'],
            'fallback_insights.*' => ['string', 'max:300'],
            'language' => ['nullable', 'string', 'max:40'],
        ]);

        $summary = (array) ($validated['summary'] ?? []);

        $language = $this->resolveInsightLanguage((string) ($validated['language'] ?? 'english'));

        $fallbackInsights = collect($validated['fallback_insights'] ?? [])
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->values()
            ->all();

        $apiKey = (string) config('services.groq.api_key', '');

        if ($apiKey === '') {
            $fallbackParagraph = $this->ensureReadableParagraph(
                $this->buildFallbackParagraph($fallbackInsights),
                $summary,
                (string) $language['key'],
            );
            $suggestionMode = $this->computeSuggestionMode($summary);
            $suggestions = $this->buildGuidedSuggestions($summary, (string) $language['key'], $suggestionMode);

            return response()->json([
                'data' => [
                    'source' => 'fallback',
                    'message' => 'GROQ_API_KEY is not configured.',
                    'language' => $language['label'],
                    'insights' => $fallbackParagraph !== '' ? [$fallbackParagraph] : $fallbackInsights,
                    'suggestion_mode' => $suggestionMode,
                    'suggestions' => $suggestions,
                ],
            ]);
        }

        $baseUrl = rtrim((string) config('services.groq.base_url', 'https://api.groq.com/openai/v1'), '/');
        $model = (string) config('services.groq.model', 'llama-3.1-8b-instant');
        $summaryJson = json_encode($validated['summary'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $systemPrompt = 'You are a concise sales analyst for an agriculture ERP dashboard.';
        $userPrompt = implode("\n", [
            'Generate one cohesive insight paragraph and 4 actionable suggestions for admin.',
            'Rules:',
            '- Write 3 to 4 connected sentences in one paragraph.',
            '- Keep the full paragraph concise (around 60 to 90 words).',
            '- Use plain text only. No markdown, no numbering, no bullet points, no line breaks.',
            '- Use Philippine peso sign (₱) for currency. Never use $ or dollar.',
            '- Language: '.$language['instruction'],
            '- Local style: '.$language['style_guide'],
            '- Avoid this style: '.$language['avoid_words'],
            '- Tone: casual, natural, and conversational. Avoid formal or textbook wording.',
            '- Sound like a real teammate talking to admin, not an AI report.',
            '- Use direct everyday wording and vary sentence openings.',
            '- Keep sentence flow natural and avoid repetitive clause patterns.',
            '- Never use awkward literal phrases like "anong kaini" or "sa pagkakataon sa pagbenta".',
            '- Respect unit labels exactly as provided. Never rename bag into sack or sack into bag.',
            '- Mention revenue vs volume tradeoff when relevant.',
            '- Mention best selling method when relevant.',
            '- Return valid JSON only using this exact shape: {"insight_paragraph":"...","suggestion_mode":"improve|maintain","suggestions":["...","...","...","..."]}',
            '- suggestion_mode must be either improve or maintain.',
            '- suggestions must contain exactly 4 concise actionable items.',
            '- Each suggestion must be one sentence only and easy to execute.',
            'Input summary JSON:',
            $summaryJson ?: '{}',
        ]);

        try {
            $response = Http::timeout(15)
                ->acceptJson()
                ->withToken($apiKey)
                ->post("{$baseUrl}/chat/completions", [
                    'model' => $model,
                    'temperature' => 0.35,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                ]);

            if ($response->failed()) {
                throw new \RuntimeException('Groq request failed with status ' . $response->status());
            }

            $content = trim((string) data_get($response->json(), 'choices.0.message.content', ''));
            $parsed = $this->parseAiResponse($content, (string) $language['key']);
            $paragraph = $this->ensureReadableParagraph(
                (string) ($parsed['insight_paragraph'] ?? ''),
                $summary,
                (string) $language['key'],
            );
            $insights = $paragraph !== '' ? [$paragraph] : [];
            $suggestionMode = $this->normalizeSuggestionMode((string) ($parsed['suggestion_mode'] ?? ''), $summary);
            $suggestions = $this->normalizeSuggestionItems(
                (array) ($parsed['suggestions'] ?? []),
                (string) $language['key'],
            );

            if (count($suggestions) === 0) {
                $suggestions = $this->buildGuidedSuggestions($summary, (string) $language['key'], $suggestionMode);
            }

            if (count($insights) === 0) {
                $fallbackParagraph = $this->buildFallbackParagraph($fallbackInsights);
                $fallbackParagraph = $this->ensureReadableParagraph($fallbackParagraph, $summary, (string) $language['key']);
                $insights = $fallbackParagraph !== '' ? [$fallbackParagraph] : $fallbackInsights;
            }

            return response()->json([
                'data' => [
                    'source' => 'groq',
                    'language' => $language['label'],
                    'insights' => $insights,
                    'suggestion_mode' => $suggestionMode,
                    'suggestions' => $suggestions,
                ],
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Profit analysis AI insight generation failed.', [
                'error' => $exception->getMessage(),
            ]);

            $fallbackParagraph = $this->ensureReadableParagraph(
                $this->buildFallbackParagraph($fallbackInsights),
                $summary,
                (string) $language['key'],
            );
            $suggestionMode = $this->computeSuggestionMode($summary);
            $suggestions = $this->buildGuidedSuggestions($summary, (string) $language['key'], $suggestionMode);

            return response()->json([
                'data' => [
                    'source' => 'fallback',
                    'message' => 'AI insight is temporarily unavailable.',
                    'language' => $language['label'],
                    'insights' => $fallbackParagraph !== '' ? [$fallbackParagraph] : $fallbackInsights,
                    'suggestion_mode' => $suggestionMode,
                    'suggestions' => $suggestions,
                ],
            ]);
        }
    }

    private function resolveInsightLanguage(string $language): array
    {
        $normalized = strtolower(trim($language));

        return match ($normalized) {
            'tagalog', 'filipino', 'tl' => [
                'key' => 'tagalog',
                'label' => 'Tagalog',
                'instruction' => 'Write all insights in casual Tagalog (Filipino), like everyday local business conversation.',
                'style_guide' => 'Use everyday spoken Tagalog used in shops/markets. Keep it warm, direct, and easy to understand.',
                'avoid_words' => 'Avoid formal Filipino like "iminumungkahi", "nararapat", "samakatuwid", "sa kabuuan", and "makabubuti".',
            ],
            'naga_bicol', 'naga bicol', 'central_bikol', 'central bikol', 'bicol', 'bicolano', 'bcl' => [
                'key' => 'naga_bicol',
                'label' => 'Naga Bicol (Central Bikol)',
                'instruction' => 'Write all insights in casual Naga Bicol (Central Bikol) used in Naga City, using natural everyday wording.',
                'style_guide' => 'Use casual Naga Bicol from daily conversation in Naga City. Keep it simple, friendly, and natural. If unsure, use a clear Tagalog-Bicol mix that sounds human.',
                'avoid_words' => 'Avoid formal report tone, deep literary Bikol, overly technical wording, and awkward phrases like "anong kaini" or repetitive template wording.',
            ],
            default => [
                'key' => 'english',
                'label' => 'English',
                'instruction' => 'Write all insights in simple conversational English.',
                'style_guide' => 'Write like quick everyday advice between coworkers.',
                'avoid_words' => 'Avoid corporate buzzwords and overly formal report language.',
            ],
        };
    }

    private function normalizeInsightParagraph(string $content, string $languageKey = 'english'): string
    {
        $paragraph = collect(preg_split('/\r\n|\r|\n/', $content))
            ->map(fn ($line) => $this->normalizeInsightLine((string) $line, $languageKey))
            ->filter()
            ->implode(' ');

        $paragraph = preg_replace('/\s+/u', ' ', trim($paragraph)) ?? trim($paragraph);
        $paragraph = $this->refineDialectParagraph($paragraph, $languageKey);

        return trim($paragraph);
    }

    private function ensureReadableParagraph(string $paragraph, array $summary, string $languageKey): string
    {
        $guided = $this->buildGuidedParagraph($summary, $languageKey);

        // Naga Bicol output is more reliable when guided by a local-first template.
        if ($languageKey === 'naga_bicol' && $guided !== '') {
            return $guided;
        }

        if ($paragraph === '') {
            return $guided;
        }

        if ($this->looksAwkwardParagraph($paragraph, $languageKey) && $guided !== '') {
            return $guided;
        }

        return $paragraph;
    }

    private function parseAiResponse(string $content, string $languageKey): array
    {
        $clean = trim($content);
        $clean = preg_replace('/^```(?:json)?\s*/iu', '', $clean) ?? $clean;
        $clean = preg_replace('/\s*```$/u', '', $clean) ?? $clean;
        $decoded = json_decode($clean, true);

        if (is_array($decoded)) {
            $rawSuggestions = data_get($decoded, 'suggestions', []);

            if (is_string($rawSuggestions)) {
                $rawSuggestions = preg_split('/\r\n|\r|\n/', $rawSuggestions) ?: [];
            }

            return [
                'insight_paragraph' => $this->normalizeInsightParagraph(
                    (string) (data_get($decoded, 'insight_paragraph') ?? data_get($decoded, 'insight') ?? ''),
                    $languageKey,
                ),
                'suggestion_mode' => strtolower(trim((string) (data_get($decoded, 'suggestion_mode') ?? data_get($decoded, 'mode') ?? ''))),
                'suggestions' => is_array($rawSuggestions) ? $rawSuggestions : [],
            ];
        }

        return [
            'insight_paragraph' => $this->normalizeInsightParagraph($content, $languageKey),
            'suggestion_mode' => '',
            'suggestions' => [],
        ];
    }

    private function normalizeSuggestionMode(string $mode, array $summary): string
    {
        $normalized = strtolower(trim($mode));

        if (in_array($normalized, ['improve', 'maintain'], true)) {
            return $normalized;
        }

        return $this->computeSuggestionMode($summary);
    }

    private function computeSuggestionMode(array $summary): string
    {
        $pack = (float) data_get($summary, 'total_sales_pack', 0);
        $kilo = (float) data_get($summary, 'total_sales_kilo', 0);
        $profitDiff = (float) data_get($summary, 'profit_difference', $pack - $kilo);
        $totalRevenue = $pack + $kilo;

        if ($totalRevenue <= 0) {
            return 'improve';
        }

        if ($profitDiff < 0) {
            return 'improve';
        }

        return 'maintain';
    }

    private function normalizeSuggestionItems(array $suggestions, string $languageKey): array
    {
        return collect($suggestions)
            ->map(fn ($item) => $this->normalizeInsightLine((string) $item, $languageKey))
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->map(fn ($item) => preg_match('/[.!?]$/u', $item) ? $item : "{$item}.")
            ->unique()
            ->take(4)
            ->values()
            ->all();
    }

    private function buildGuidedSuggestions(array $summary, string $languageKey, string $mode): array
    {
        $bestMethod = trim((string) data_get($summary, 'best_selling_method', 'best method'));

        if ($languageKey === 'tagalog') {
            if ($mode === 'improve') {
                return [
                    "Tutukan ang products na mabenta sa {$bestMethod}, tapos mag light promo sa mahihinang items.",
                    'Mag-test ng maliit na price adjustment (₱10-₱20) at bantayan ang effect kada araw.',
                    'I-bundle ang slow-moving products sa top sellers para tumaas ang total kada transaksyon.',
                    'Mag weekly review ng pinakamahinang products para mabilis ma-adjust ang stock at display.',
                ];
            }

            return [
                'Panatilihin ang stock buffer ng top-selling products para iwas out-of-stock sa peak days.',
                'I-keep muna ang current presyo na gumagana at mag controlled small tests lang.',
                "I-monitor weekly ang {$bestMethod} laban sa ibang method para maagapan agad kung may dip.",
                'Magbigay ng simpleng repeat-buyer perks para steady ang balik-bili at volume ng sales.',
            ];
        }

        if ($languageKey === 'naga_bicol') {
            if ($mode === 'improve') {
                return [
                    "Tutukan ta an products na kusog an benta sa {$bestMethod}, tapos mag simple promo sa mga mahina.",
                    'Mag test nin gamay na pag-adjust sa presyo (₱10-₱20) asin bantayan an epekto kada adlaw.',
                    'Ipares an slow-moving items sa top sellers tanganing tumaas an total kada transaksyon.',
                    'Mag weekly review sa pinaka-mahinay na products para mabilis an stock asin display adjustment.',
                ];
            }

            return [
                'Panatilihon an stock buffer sa top-selling products para dai maubusan sa peak na adlaw.',
                'I-keep an presyo na nagana asin mag gamay na controlled tests sana kun kailangan.',
                "I-monitor kada semana an {$bestMethod} kontra ibang method para maagapan an posibleng dip.",
                'Mag offer nin simple reward sa repeat customers para tuloy-tuloy an volume nin benta.',
            ];
        }

        if ($mode === 'improve') {
            return [
                "Push products with stronger demand in {$bestMethod}, then run light promos on slower items.",
                'Test small price changes (₱10-₱20) and track daily conversion before scaling.',
                'Bundle slow-moving products with top sellers to raise average transaction value.',
                'Review low-performing rows weekly and prioritize restock for fast-rotation products.',
            ];
        }

        return [
            'Maintain stock buffer on top sellers to avoid missed sales during peak hours.',
            'Keep current winning price points and run only controlled micro-tests when needed.',
            "Track {$bestMethod} versus alternative methods weekly so you can respond early to dips.",
            'Use light loyalty offers for repeat buyers to sustain volume and retention.',
        ];
    }

    private function looksAwkwardParagraph(string $paragraph, string $languageKey): bool
    {
        $lower = mb_strtolower($paragraph, 'UTF-8');
        $awkwardPhrases = [
            'anong kaini',
            'sa pagkakataon sa pagbenta',
            'nagkakaiba anin',
            'mas halang',
            'ini nagpapahiling',
            'kita natin mas lalo',
            'nagresulta sa pagkawala',
        ];

        foreach ($awkwardPhrases as $phrase) {
            if (str_contains($lower, $phrase)) {
                return true;
            }
        }

        if (substr_count($lower, 'kita natin') >= 3) {
            return true;
        }

        if (substr_count($lower, 'pero') >= 3) {
            return true;
        }

        if ($languageKey === 'naga_bicol' && str_contains($lower, 'kita natin')) {
            return true;
        }

        return false;
    }

    private function buildGuidedParagraph(array $summary, string $languageKey): string
    {
        $packedLabel = trim((string) data_get($summary, 'packed_label', 'bag/sack'));
        $pack = (float) data_get($summary, 'total_sales_pack', 0);
        $kilo = (float) data_get($summary, 'total_sales_kilo', 0);
        $diff = $pack - $kilo;
        $absDiff = abs($diff);
        $packValue = $this->formatPeso($pack);
        $kiloValue = $this->formatPeso($kilo);
        $diffValue = $this->formatPeso($absDiff);

        if ($languageKey === 'tagalog') {
            if ($pack <= 0 && $kilo <= 0) {
                return 'Admin, sa ngayon kulang pa ang sales data sa napiling filter kaya hindi pa klaro kung alin ang mas malakas. Mag-record pa tayo ng dagdag na benta para mas solid ang susunod na insight.';
            }

            if ($absDiff < 0.01) {
                return "Admin, halos tabla ang benta ng {$packedLabel} at per kilo ngayon. Sa {$packedLabel}, nasa ₱{$packValue}; sa per kilo, nasa ₱{$kiloValue}. I-maintain muna natin ang setup tapos mag small promo test para makita kung alin ang mas aangat.";
            }

            if ($diff > 0) {
                return "Admin, sa ngayon mas malakas ang benta sa {$packedLabel} kaysa per kilo. Sa {$packedLabel}, naka ₱{$packValue} tayo habang sa per kilo ay ₱{$kiloValue}. May lamang na ₱{$diffValue} ang {$packedLabel}, kaya doon muna tayo tumutok habang inaangat ang per kilo.";
            }

            return "Admin, sa ngayon mas malakas ang benta sa per kilo kaysa {$packedLabel}. Sa per kilo, naka ₱{$kiloValue} tayo habang sa {$packedLabel} ay ₱{$packValue}. May lamang na ₱{$diffValue} ang per kilo, kaya magandang doon muna tumutok habang pinapalakas ang {$packedLabel}.";
        }

        if ($languageKey === 'naga_bicol') {
            if ($pack <= 0 && $kilo <= 0) {
                return 'Admin, sa ngunyan kulang pa an sales data sa napiling filter, kaya dai pa klaro kun sain na method an mas maray. Magdugang pa kita nin records tanganing mas masaligan an sunod na insight.';
            }

            if ($absDiff < 0.01) {
                return "Admin, halos tabla an benta ta sa {$packedLabel} asin per kilo ngunyan. Sa {$packedLabel}, nasa ₱{$packValue}; sa per kilo, nasa ₱{$kiloValue}. Maray na i-maintain an setup tapos mag gamay na promo test para mahiling kun sain an mas paspas mag-angat.";
            }

            if ($diff > 0) {
                return "Admin, sa ngunyan mas maray an benta sa {$packedLabel} kaysa per kilo. Sa {$packedLabel}, naka ₱{$packValue} kita, habang sa per kilo naka ₱{$kiloValue}. May lamang na ₱{$diffValue} an {$packedLabel}, kaya didto muna ta i-focus an stock asin promo habang pinapataas ta an per kilo.";
            }

            return "Admin, sa ngunyan mas maray an benta sa per kilo kaysa {$packedLabel}. Sa per kilo, naka ₱{$kiloValue} kita, habang sa {$packedLabel} naka ₱{$packValue}. May lamang na ₱{$diffValue} an per kilo, kaya maray na didto ta tutukan an pricing asin display habang pinapalakas ta an {$packedLabel}.";
        }

        return '';
    }

    private function formatPeso(float $amount): string
    {
        return number_format($amount, 2, '.', ',');
    }

    private function refineDialectParagraph(string $paragraph, string $languageKey): string
    {
        $refined = trim($paragraph);

        if ($refined === '') {
            return '';
        }

        if ($languageKey === 'naga_bicol') {
            $replacements = [
                '/\banong\s+kaini\s+sa\s+atin\b/iu' => 'kamusta an benta ta ngunyan',
                '/\banong\s+kaini\b/iu' => 'sa ngunyan',
                '/\bnagkakaiba\s+anin\b/iu' => 'magkaiba an',
                '/\bsa\s+pagkakataon\s+sa\s+pagbenta\b/iu' => 'sa pagbaligya',
                '/\bsa\s+pagkakataon\b/iu' => 'may chance',
                '/\bpagbenta\b/iu' => 'pagbaligya',
                '/\bini\s+may\s+pagkakataon\b/iu' => 'igwa pa kita chance',
                '/\bini\s+nagpapahiling\s+na\b/iu' => 'Ibig sabihon',
                '/\bnagresulta\s+sa\s+pagkawala\b/iu' => 'nagdara nin bawas',
                '/\bmas\s+halang\b/iu' => 'mas maray',
                '/\btotal\s+sales\b/iu' => 'total na benta',
                '/\bsa\s+profit\b/iu' => 'sa kita',
                '/\bkita\s+natin\b/iu' => 'kita ta',
                '/\bpero\s+ini\b/iu' => 'pero',
            ];

            foreach ($replacements as $pattern => $replacement) {
                $refined = preg_replace($pattern, $replacement, $refined) ?? $refined;
            }
        }

        $refined = preg_replace('/\s+/u', ' ', trim($refined)) ?? trim($refined);
        $refined = preg_replace('/\s+([,.;!?])/u', '$1', $refined) ?? $refined;

        return trim($refined);
    }

    private function buildFallbackParagraph(array $insights): string
    {
        $sentences = collect($insights)
            ->map(fn ($line) => trim((string) $line))
            ->filter()
            ->map(fn ($line) => preg_match('/[.!?]$/u', $line) ? $line : "{$line}.")
            ->values()
            ->all();

        if (count($sentences) === 0) {
            return '';
        }

        $paragraph = implode(' ', $sentences);

        return trim(preg_replace('/\s+/u', ' ', $paragraph) ?? $paragraph);
    }

    private function normalizeInsightLine(string $line, string $languageKey = 'english'): string
    {
        $normalized = trim($line);
        $normalized = preg_replace('/^[-*\d\.)\s]+/u', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/^(insight|punto)\s*\d*[:\-]\s*/iu', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/US\$\s*/iu', '₱ ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\$\s*/u', '₱ ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\bUSD\b/iu', 'PHP', $normalized) ?? $normalized;
        $normalized = preg_replace('/\bUS\s*dollars?\b/iu', 'pesos', $normalized) ?? $normalized;
        $normalized = preg_replace('/\bdollars?\b/iu', 'pesos', $normalized) ?? $normalized;

        if ($languageKey === 'tagalog' || $languageKey === 'naga_bicol') {
            $normalized = preg_replace('/\bSamakatuwid\b/iu', 'Kaya', $normalized) ?? $normalized;
            $normalized = preg_replace('/\bGayunpaman\b/iu', 'Pero', $normalized) ?? $normalized;
            $normalized = preg_replace('/\bSubalit\b/iu', 'Pero', $normalized) ?? $normalized;
            $normalized = preg_replace('/\bSa\s+kabuuan\b/iu', 'Sa ngayon', $normalized) ?? $normalized;
            $normalized = preg_replace('/\bDahil\s+dito\b/iu', 'Kaya', $normalized) ?? $normalized;
            $normalized = preg_replace('/\bMaaaring\b/iu', 'Puwede', $normalized) ?? $normalized;
            $normalized = preg_replace('/\bKinakailangan\b/iu', 'Kailangan', $normalized) ?? $normalized;
            $normalized = preg_replace('/\b(Iminumungkahi|Inirerekomenda|Nararapat|Makabubuti)\b/iu', 'Mas okay', $normalized) ?? $normalized;
        }

        return trim($normalized);
    }
}
