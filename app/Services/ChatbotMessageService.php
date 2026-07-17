<?php

namespace App\Services;

use App\Models\ChatbotFaq;
use Illuminate\Http\Request;

class ChatbotMessageService
{
    public function __construct(
        private readonly ChatbotSettingsService $settings,
        private readonly ChatbotFaqSearchService $faqSearch,
        private readonly ChatbotConversationService $conversations,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function handle(array $payload, Request $request): array
    {
        $sessionId = (string) ($payload['session_id'] ?? '');
        $language = $this->normalizeLanguage((string) ($payload['language'] ?? 'en'));
        $message = trim((string) ($payload['message'] ?? ''));
        $action = (string) ($payload['action'] ?? 'message');
        $visitorName = isset($payload['visitor_name']) ? (string) $payload['visitor_name'] : null;
        $userRole = $this->normalizeUserRole(isset($payload['user_role']) ? (string) $payload['user_role'] : null);

        if ($sessionId === '') {
            $sessionId = (string) str()->uuid();
        }

        $response = match ($action) {
            'welcome' => $this->welcomeResponse($language, $userRole),
            'quick_reply' => $this->quickReplyResponse((string) ($payload['quick_reply'] ?? $message), $language, $userRole),
            default => $this->messageResponse($message, $language, isset($payload['category']) ? (string) $payload['category'] : null, $userRole),
        };

        if ($message !== '' || $action === 'quick_reply') {
            $storedMessage = $action === 'quick_reply'
                ? (string) ($payload['quick_reply'] ?? $message)
                : $message;

            $this->conversations->record(
                $sessionId,
                $storedMessage,
                is_string($response['text'] ?? null) ? $response['text'] : json_encode($response),
                $language,
                $visitorName,
                $request,
            );
        }

        $response['session_id'] = $sessionId;

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    public function welcomeResponse(string $language = 'en', ?string $userRole = null): array
    {
        $config = $this->settings->publicConfig();
        $text = $config['welcome_message'];

        if ($userRole !== null) {
            $portalHint = match ($userRole) {
                'admin' => $this->t(
                    $language,
                    "\n\nYou're logged in as Admin. Ask about users, products, orders, subscriptions, chatbot settings, and more.",
                    "\n\nतुम्ही Admin म्हणून login आहात. Users, products, orders, subscriptions, chatbot settings विषयी विचारा.",
                    "\n\nआप Admin के रूप में login हैं। Users, products, orders, subscriptions, chatbot settings के बारे में पूछें।",
                ),
                'employee' => $this->t(
                    $language,
                    "\n\nYou're logged in as Employee. Ask how to mark attendance, create tasks, apply leave, submit timesheets, and more.",
                    "\n\nतुम्ही Employee म्हणून login आहात. Attendance mark करणे, tasks तयार करणे, leave apply करणे, timesheets submit करणे — असे विचारा.",
                    "\n\nआप Employee के रूप में login हैं। Attendance mark करना, tasks बनाना, leave apply करना, timesheets submit करना — पूछें।",
                ),
                'client' => $this->t(
                    $language,
                    "\n\nYou're logged in as Client. Ask about orders, subscriptions, invoices, license keys, and support tickets.",
                    "\n\nतुम्ही Client म्हणून login आहात. Orders, subscriptions, invoices, license keys, support tickets — विषयी विचारा.",
                    "\n\nआप Client के रूप में login हैं। Orders, subscriptions, invoices, license keys, support tickets — पूछें।",
                ),
                'hr' => $this->t(
                    $language,
                    "\n\nYou're logged in as HR. Ask about employees, leave approval, attendance review, job openings, and applications.",
                    "\n\nतुम्ही HR म्हणून login आहात. Employees, leave approval, attendance review, job openings, applications — विषयी विचारा.",
                    "\n\nआप HR के रूप में login हैं। Employees, leave approval, attendance review, job openings, applications — पूछें।",
                ),
                default => '',
            };
            $text .= $portalHint;
        }

        return [
            'type' => 'welcome',
            'text' => $text,
            'quick_replies' => $this->localizedQuickReplies($language),
            'language_options' => $this->languageOptions(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function quickReplyResponse(string $key, string $language, ?string $userRole = null): array
    {
        $key = strtolower(trim($key));
        $company = $this->settings->publicConfig()['company'];

        return match ($key) {
            'products' => [
                'type' => 'products',
                'text' => $this->t($language, 'Here are our software products:', 'आमची सॉफ्टवेअर उत्पादने:', 'हमारे सॉफ्टवेयर उत्पाद:'),
                'items' => $this->productItems($language),
                'actions' => [
                    ['type' => 'link', 'label' => $this->t($language, 'View all products', 'सर्व उत्पादने', 'सभी उत्पाद'), 'href' => '/products'],
                ],
                'quick_replies' => $this->localizedQuickReplies($language),
            ],
            'pricing' => [
                'type' => 'pricing',
                'text' => $this->pricingSummary($language),
                'contact' => $company,
                'actions' => [
                    ['type' => 'link', 'label' => $this->t($language, 'View pricing page', 'किंमत पहा', 'मूल्य देखें'), 'href' => '/pricing'],
                    ['type' => 'link', 'label' => $this->t($language, 'Request quote', 'कोट विनंती', 'कोट अनुरोध'), 'href' => '/contact'],
                ],
                'quick_replies' => $this->localizedQuickReplies($language),
            ],
            'book_demo' => [
                'type' => 'lead_form',
                'form' => 'book_demo',
                'text' => $this->t($language, 'Please share your details to book a demo:', 'डेमो बुक करण्यासाठी तपशील भरा:', 'डेमो बुक करने के लिए विवरण भरें:'),
                'fields' => ['name', 'phone', 'email', 'company_name', 'product', 'preferred_datetime', 'message'],
            ],
            'contact' => [
                'type' => 'contact',
                'text' => $this->t($language, 'Reach SoftKatta Solutions:', 'SoftKatta Solutions संपर्क:', 'SoftKatta Solutions संपर्क:'),
                'contact' => $company,
                'quick_replies' => $this->localizedQuickReplies($language),
            ],
            'support' => [
                'type' => 'lead_form',
                'form' => 'technical_support',
                'text' => $this->t($language, 'Tell us about your technical issue:', 'तांत्रिक समस्येचा तपशील द्या:', 'तकनीकी समस्या का विवरण दें:'),
                'fields' => ['name', 'phone', 'product', 'message'],
            ],
            'faq' => $this->popularFaqsResponse(
                $language,
                '/faq',
                'Here are popular questions:',
                'लोकप्रिय प्रश्न:',
                'लोकप्रिय प्रश्न:',
                'View all FAQ',
                'सर्व FAQ',
                'सभी FAQ',
            ),
            'careers' => $this->categoryBrowseResponse(
                $language,
                'careers',
                '/careers',
                'Career opportunities at SoftKatta:',
                'SoftKatta मध्ये करिअर:',
                'SoftKatta में करियर:',
                'View careers',
                'करिअर पहा',
                'करियर देखें',
            ),
            'business_hours' => [
                'type' => 'business_hours',
                'text' => $this->settings->all()['business_hours'] ?? '',
                'quick_replies' => $this->localizedQuickReplies($language),
            ],
            'language_en', 'language_mr', 'language_hi' => [
                'type' => 'language_changed',
                'language' => str_replace('language_', '', $key),
                ...$this->welcomeResponse(str_replace('language_', '', $key), $userRole),
            ],
            default => $this->resolveQuickReplyOrMessage($key, $language, $userRole),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveQuickReplyOrMessage(string $key, string $language, ?string $userRole = null): array
    {
        if (ctype_digit($key)) {
            return $this->faqByIdResponse((int) $key, $language);
        }

        if (str_starts_with($key, 'faq:') && ctype_digit(substr($key, 4))) {
            return $this->faqByIdResponse((int) substr($key, 4), $language);
        }

        if (preg_match('/^portal:(admin|employee|client|hr):([a-z_]+)$/', $key, $matches) === 1) {
            $topic = $matches[2] === 'attendance' ? 'attendance_mark' : $matches[2];

            return $this->portalTopicResponse($matches[1], $topic, $language);
        }

        return $this->messageResponse($key, $language, null, $userRole);
    }

    /**
     * @return array<string, mixed>
     */
    private function faqByIdResponse(int $id, string $language): array
    {
        $faq = ChatbotFaq::query()
            ->where('is_active', true)
            ->where('id', $id)
            ->where('language', $language)
            ->first()
            ?? ChatbotFaq::query()
                ->where('is_active', true)
                ->where('id', $id)
                ->first();

        if ($faq === null) {
            return $this->fallbackResponse($language);
        }

        return [
            'type' => 'faq_answer',
            'text' => $faq->answer,
            'faq' => [
                'id' => $faq->id,
                'question' => $faq->question,
                'answer' => $faq->answer,
            ],
            'quick_replies' => $this->localizedQuickReplies($language),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function messageResponse(string $message, string $language, ?string $category = null, ?string $userRole = null): array
    {
        if ($message === '') {
            return $this->welcomeResponse($language, $userRole);
        }

        $normalized = strtolower(trim($message));

        if ($this->isGreeting($normalized)) {
            return $this->greetingResponse($language, $userRole);
        }

        if ($userRole !== null) {
            $portalIntent = $this->detectPortalIntent($normalized, $userRole);
            if ($portalIntent !== null) {
                return $this->quickReplyResponse($portalIntent, $language, $userRole);
            }
        }

        $conversationalIntent = $this->detectConversationalIntent($normalized, $userRole);
        if ($conversationalIntent !== null) {
            return $this->quickReplyResponse($conversationalIntent, $language, $userRole);
        }

        $exact = ChatbotFaq::query()
            ->where('is_active', true)
            ->where('language', $language)
            ->whereRaw('LOWER(question) = ?', [$normalized])
            ->when($userRole === null, fn ($builder) => $builder->where('category', 'not like', 'portal\_%'))
            ->first();

        if ($exact !== null) {
            return [
                'type' => 'faq_answer',
                'text' => $exact->answer,
                'faq' => [
                    'id' => $exact->id,
                    'question' => $exact->question,
                    'answer' => $exact->answer,
                ],
                'quick_replies' => $this->localizedQuickReplies($language),
            ];
        }

        $matches = $this->faqSearch->search($message, $language, $category, 5, $userRole);

        if ($matches !== []) {
            $top = $matches[0];
            $secondScore = $matches[1]['score'] ?? 0.0;
            $minScore = $this->minimumMatchScore($normalized, $matches);

            if ($top['score'] >= $minScore && (count($matches) === 1 || $top['score'] >= 35 || ($top['score'] >= 24 && $top['score'] >= ($secondScore * 1.6)))) {
                return [
                    'type' => 'faq_answer',
                    'text' => $top['answer'],
                    'faq' => [
                        'id' => $top['id'],
                        'question' => $top['question'],
                        'answer' => $top['answer'],
                    ],
                    'quick_replies' => $this->localizedQuickReplies($language),
                ];
            }

            return [
                'type' => 'faq_matches',
                'text' => $this->t($language, 'I found these related answers:', 'संबंधित उत्तरे:', 'संबंधित उत्तर:'),
                'matches' => array_map(fn (array $item): array => [
                    'id' => $item['id'],
                    'question' => $item['question'],
                    'answer' => $item['answer'],
                ], $matches),
                'quick_replies' => $this->localizedQuickReplies($language),
            ];
        }

        $intent = $this->detectIntent($message, $userRole);
        if ($intent !== null) {
            return $this->quickReplyResponse($intent, $language, $userRole);
        }

        return $this->fallbackResponse($language, $userRole);
    }

    private function isGreeting(string $normalized): bool
    {
        $normalized = preg_replace('/[!?.,\s]+/u', '', $normalized) ?? $normalized;

        $greetings = [
            'hi', 'hii', 'hiii', 'hey', 'hello', 'helo', 'howdy', 'yo',
            'namaste', 'namaskar', 'hola', 'sup',
            'hika', 'hikka', 'hii ka', 'hi ka', 'kaay', 'kay',
            'good morning', 'good afternoon', 'good evening', 'good night',
            'gm', 'gn',
        ];

        if (in_array($normalized, $greetings, true)) {
            return true;
        }

        return (bool) preg_match('/^(hi+|hey+|hello+|namaste+|namaskar+)$/u', $normalized);
    }

    /**
     * @return array<string, mixed>
     */
    private function greetingResponse(string $language, ?string $userRole = null): array
    {
        $portalHint = match ($userRole) {
            'employee' => $this->t(
                $language,
                "\n\nYou can ask me in English or Marathi — e.g. \"How to mark attendance?\", \"task kas create karu?\", or tap a topic below.",
                "\n\nतुम्ही English किंवा Marathi मध्ये विचारू शकता — \"attendance kase mark karu?\", \"task kas create karu?\" किंवा खालील topic निवडा.",
                "\n\nआप English या Marathi में पूछ सकते हैं — \"attendance kaise mark kare?\" या नीचे topic चुनें।",
            ),
            'client' => $this->t(
                $language,
                "\n\nAsk about orders, invoices, subscriptions, or support tickets.",
                "\n\nOrders, invoices, subscriptions, support tickets — विचारा.",
                "\n\nOrders, invoices, subscriptions, support — पूछें।",
            ),
            'admin' => $this->t(
                $language,
                "\n\nAsk about users, products, orders, subscriptions, or chatbot settings.",
                "\n\nUsers, products, orders, subscriptions, chatbot — विचारा.",
                "\n\nUsers, products, orders, subscriptions — पूछें।",
            ),
            'hr' => $this->t(
                $language,
                "\n\nAsk about employees, leave approval, attendance, or job applications.",
                "\n\nEmployees, leave, attendance, job applications — विचारा.",
                "\n\nEmployees, leave, attendance, applications — पूछें।",
            ),
            default => $this->t(
                $language,
                "\n\nAsk about products, pricing, demo, support, or careers — or pick a quick option below.",
                "\n\nProducts, pricing, demo, support, careers — विचारा किंवा खालील option निवडा.",
                "\n\nProducts, pricing, demo, support — पूछें या नीचे option चुनें।",
            ),
        };

        return [
            'type' => 'welcome',
            'text' => $this->t(
                $language,
                "Hello! 👋 I'm here to help.{$portalHint}",
                "नमस्कार! 👋 मी मदतीसाठी आहे.{$portalHint}",
                "नमस्ते! 👋 मैं मदद के लिए यहाँ हूँ।{$portalHint}",
            ),
            'quick_replies' => $this->localizedQuickReplies($language),
        ];
    }

    private function detectConversationalIntent(string $normalized, ?string $userRole = null): ?string
    {
        $chatUsage = [
            'how to use chat', 'how to chat', 'use chatbot', 'use the chatbot',
            'chat kas', 'chat kase', 'chatbot kas', 'chatbot kase',
            'chat kaise', 'chatbot kaise', 'mi chat', 'chat karu', 'chat karaycha',
            'how do i chat', 'how can i chat', 'chatbot cha use',
        ];

        foreach ($chatUsage as $phrase) {
            if (str_contains($normalized, $phrase)) {
                return $userRole !== null
                    ? 'portal:'.$userRole.':chat_help'
                    : 'faq';
            }
        }

        return null;
    }

    /**
     * @param  list<array{score: float}>  $matches
     */
    private function minimumMatchScore(string $normalized, array $matches): float
    {
        $compact = preg_replace('/\s+/u', '', $normalized) ?? $normalized;
        $length = mb_strlen($compact);

        if ($length <= 3) {
            return 80.0;
        }

        if ($length <= 6) {
            return 50.0;
        }

        return 24.0;
    }

    /**
     * @return array<string, mixed>
     */
    private function fallbackResponse(string $language, ?string $userRole = null): array
    {
        $portalHint = $userRole !== null
            ? $this->t(
                $language,
                ' Try asking about your portal — e.g. attendance, tasks, leave, orders, or users.',
                ' तुमच्या portal बद्दल विचारा — attendance, tasks, leave, orders, users.',
                ' अपने portal के बारे में पूछें — attendance, tasks, leave, orders, users.',
            )
            : '';

        return [
            'type' => 'fallback',
            'text' => $this->t(
                $language,
                "I couldn't find an exact answer. Please choose an option below or contact our team.{$portalHint}",
                "अचूक उत्तर सापडले नाही. खालील पर्याय निवडा किंवा आमच्याशी संपर्क साधा.{$portalHint}",
                "सटीक उत्तर नहीं मिला। नीचे विकल्प चुनें या हमसे संपर्क करें।{$portalHint}",
            ),
            'quick_replies' => $this->localizedQuickReplies($language),
        ];
    }

    private function detectIntent(string $message, ?string $userRole = null): ?string
    {
        $normalized = strtolower(trim($message));

        if ($userRole !== null) {
            $portalIntent = $this->detectPortalIntent($normalized, $userRole);
            if ($portalIntent !== null) {
                return $portalIntent;
            }
        }

        $intents = [
            'book_demo' => ['book demo', 'book a demo', 'schedule demo', 'live demo', 'demo booking'],
            'business_hours' => ['business hours', 'working hours', 'office hours', 'what time', 'when open', 'when close'],
            'contact' => ['how to contact', 'contact us', 'contact you', 'get in touch', 'phone number', 'email address', 'call you', 'reach you', 'reach softkatta'],
            'pricing' => ['how much', 'how can i get pricing', 'pricing details', 'price list', 'cost of', 'subscription price', 'monthly price'],
            'products' => ['what products', 'software products', 'your products', 'product list', 'which erp', 'study point', 'medical store software', 'nursery school software'],
            'support' => ['technical support', 'tech support', 'support ticket', 'report issue', 'report a bug', 'not working', 'login problem', 'payment failed'],
            'careers' => ['are you hiring', 'job opening', 'job vacancy', 'apply for job', 'send resume', 'career opportunity'],
            'faq' => ['frequently asked', 'common questions', 'help topics'],
        ];

        foreach ($intents as $intent => $phrases) {
            foreach ($phrases as $phrase) {
                if (str_contains($normalized, $phrase)) {
                    return $intent;
                }
            }
        }

        $singleWordIntents = [
            'products' => ['products', 'product'],
            'pricing' => ['pricing', 'price', 'cost'],
            'contact' => ['contact', 'phone', 'email'],
            'support' => ['support', 'help'],
            'careers' => ['careers', 'career', 'hiring', 'jobs', 'job'],
            'book_demo' => ['demo'],
        ];

        foreach ($singleWordIntents as $intent => $words) {
            if (in_array($normalized, $words, true)) {
                return $intent;
            }
        }

        return null;
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    private function localizedQuickReplies(string $language): array
    {
        return array_map(function (array $option) use ($language): array {
            return [
                'key' => $option['key'],
                'label' => $option['label'][$language] ?? $option['label']['en'],
            ];
        }, $this->settings->quickReplyOptions());
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    private function languageOptions(): array
    {
        return [
            ['key' => 'language_en', 'label' => 'English'],
            ['key' => 'language_mr', 'label' => 'मराठी'],
            ['key' => 'language_hi', 'label' => 'हिंदी'],
        ];
    }

    private function normalizeLanguage(string $language): string
    {
        return in_array($language, ['en', 'mr', 'hi'], true) ? $language : 'en';
    }

    private function t(string $language, string $en, string $mr, string $hi): string
    {
        return match ($language) {
            'mr' => $mr,
            'hi' => $hi,
            default => $en,
        };
    }

    /** @return list<string> */
    private function productItems(string $language): array
    {
        return match ($language) {
            'mr' => [
                'Study Point Management Software — ₹2,999/महिना पासून (शाळा/कोचिंग)',
                'Medical Store Management Software — ₹1,999/महिना पासून (pharmacy/GST)',
                'Nursery School Management Software — ₹1,499/महिना पासून',
                'Custom Software Development — विनंतीवर कोट',
            ],
            'hi' => [
                'Study Point Management Software — ₹2,999/माह से (स्कूल/कोचिंग)',
                'Medical Store Management Software — ₹1,999/माह से (फार्मेसी/GST)',
                'Nursery School Management Software — ₹1,499/माह से',
                'Custom Software Development — अनुरोध पर कोट',
            ],
            default => [
                'Study Point Management Software — from ₹2,999/mo (schools & coaching)',
                'Medical Store Management Software — from ₹1,999/mo (pharmacy & GST)',
                'Nursery School Management Software — from ₹1,499/mo',
                'Custom Software Development — quote on request',
            ],
        };
    }

    private function pricingSummary(string $language): string
    {
        return match ($language) {
            'mr' => "उत्पाद किंमत:\n• Study Point: ₹2,999/महिना पासून\n• Medical Store: ₹1,999/महिना पासून\n• Nursery School: ₹1,499/महिना पासून\n\nवार्षिक योजनेवर ~20% बचत. 14-दिवस विनामूल्य ट्रायल.",
            'hi' => "उत्पाद मूल्य:\n• Study Point: ₹2,999/माह से\n• Medical Store: ₹1,999/माह से\n• Nursery School: ₹1,499/माह से\n\nवार्षिक योजना पर ~20% बचत. 14-दिन का मुफ्त ट्रायल.",
            default => "Product pricing:\n• Study Point: from ₹2,999/month\n• Medical Store: from ₹1,999/month\n• Nursery School: from ₹1,499/month\n\nSave ~20% on yearly plans. 14-day free trial on all products.",
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function popularFaqsResponse(
        string $language,
        string $pageHref,
        string $headingEn,
        string $headingMr,
        string $headingHi,
        string $linkLabelEn,
        string $linkLabelMr,
        string $linkLabelHi,
    ): array {
        $faqs = ChatbotFaq::query()
            ->where('is_active', true)
            ->where('language', $language)
            ->whereIn('category', ['company', 'products', 'pricing', 'support', 'general'])
            ->orderBy('sort_order')
            ->limit(5)
            ->get();

        return $this->buildFaqBrowseResponse(
            $faqs,
            $language,
            $pageHref,
            $headingEn,
            $headingMr,
            $headingHi,
            $linkLabelEn,
            $linkLabelMr,
            $linkLabelHi,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function categoryBrowseResponse(
        string $language,
        ?string $category,
        string $pageHref,
        string $headingEn,
        string $headingMr,
        string $headingHi,
        string $linkLabelEn,
        string $linkLabelMr,
        string $linkLabelHi,
    ): array {
        $faqs = ChatbotFaq::query()
            ->where('is_active', true)
            ->where('language', $language)
            ->when($category, fn ($builder) => $builder->where('category', $category))
            ->orderBy('sort_order')
            ->limit(5)
            ->get();

        return $this->buildFaqBrowseResponse(
            $faqs,
            $language,
            $pageHref,
            $headingEn,
            $headingMr,
            $headingHi,
            $linkLabelEn,
            $linkLabelMr,
            $linkLabelHi,
        );
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ChatbotFaq>  $faqs
     * @return array<string, mixed>
     */
    private function buildFaqBrowseResponse(
        $faqs,
        string $language,
        string $pageHref,
        string $headingEn,
        string $headingMr,
        string $headingHi,
        string $linkLabelEn,
        string $linkLabelMr,
        string $linkLabelHi,
    ): array {
        $actions = [
            [
                'type' => 'link',
                'label' => $this->t($language, $linkLabelEn, $linkLabelMr, $linkLabelHi),
                'href' => $pageHref,
            ],
        ];

        if ($faqs->isEmpty()) {
            return [
                'type' => 'faq_empty',
                'text' => $this->t(
                    $language,
                    'Browse more on our website:',
                    'अधिक माहिती आमच्या वेबसाइटवर:',
                    'अधिक जानकारी हमारी वेबसाइट पर:',
                ),
                'actions' => $actions,
                'quick_replies' => $this->localizedQuickReplies($language),
            ];
        }

        if ($faqs->count() === 1) {
            $faq = $faqs->first();

            return [
                'type' => 'faq_answer',
                'text' => $faq->answer,
                'faq' => [
                    'id' => $faq->id,
                    'question' => $faq->question,
                    'answer' => $faq->answer,
                ],
                'actions' => $actions,
                'quick_replies' => $this->localizedQuickReplies($language),
            ];
        }

        return [
            'type' => 'faq_matches',
            'text' => $this->t($language, $headingEn, $headingMr, $headingHi),
            'matches' => $faqs->map(fn (ChatbotFaq $faq): array => [
                'id' => $faq->id,
                'question' => $faq->question,
                'answer' => $faq->answer,
            ])->all(),
            'actions' => $actions,
            'quick_replies' => $this->localizedQuickReplies($language),
        ];
    }

    private function normalizeUserRole(?string $role): ?string
    {
        if ($role === null || $role === '') {
            return null;
        }

        return in_array($role, ['admin', 'employee', 'client', 'hr'], true) ? $role : null;
    }

    private function detectPortalIntent(string $normalized, string $userRole): ?string
    {
        $portalIntents = match ($userRole) {
            'employee' => [
                'portal:employee:attendance_mark' => [
                    'mark attendance', 'how to mark attendance', 'how do i mark attendance',
                    'submit attendance', 'daily attendance', 'check in', 'check out',
                    'attendance mark', 'attendance kas mark', 'attendance kase mark',
                    'attendance kaise mark', 'hajeri mark', 'hajeri kas', 'hajeri kase',
                    'attendance submit', 'attendance entry', 'attendance ghalaycha', 'attendance takaycha',
                ],
                'portal:employee:attendance_view' => [
                    'get attendance', 'view attendance', 'how to get attendance', 'check attendance',
                    'attendance record', 'attendance paha', 'attendance dis', 'attendance history',
                    'my attendance', 'attendance bagha', 'attendance records',
                ],
                'portal:employee:attendance' => [
                    'attendance',
                ],
                'portal:employee:tasks' => [
                    'create task', 'how to create task', 'how do i create a task', 'add task',
                    'my tasks', 'view tasks', 'manage tasks', 'new task',
                    'task kas', 'task kase', 'task create', 'task add', 'task kaise',
                ],
                'portal:employee:leave' => [
                    'apply leave', 'apply for leave', 'how to apply leave', 'leave application',
                    'request leave', 'submit leave', 'leave kas', 'leave kase', 'rja kas', 'raja kas',
                ],
                'portal:employee:timesheets' => [
                    'timesheet', 'submit timesheet', 'how to submit timesheet', 'log hours', 'time sheet',
                    'timesheet kas', 'timesheet kase', 'vel register', 'kaamacha vel',
                ],
                'portal:employee:documents' => [
                    'upload document', 'upload documents', 'my documents', 'employee documents',
                ],
                'portal:employee:helpdesk' => [
                    'helpdesk', 'help desk', 'raise ticket', 'it support employee',
                ],
                'portal:employee:resignation' => [
                    'resignation', 'apply resignation', 'submit resignation', 'leave job',
                ],
                'portal:employee:projects' => [
                    'my projects', 'view projects', 'employee projects',
                ],
            ],
            'client' => [
                'portal:client:orders' => [
                    'my orders', 'view orders', 'order history', 'how to view orders', 'check order',
                ],
                'portal:client:subscriptions' => [
                    'my subscription', 'manage subscription', 'renew subscription', 'subscription plan',
                ],
                'portal:client:invoices' => [
                    'download invoice', 'my invoices', 'view invoices', 'gst invoice', 'get invoice',
                ],
                'portal:client:licenses' => [
                    'license key', 'license keys', 'product license', 'activation key',
                ],
                'portal:client:support' => [
                    'support ticket', 'raise ticket', 'create ticket', 'client support', 'open ticket',
                ],
                'portal:client:profile' => [
                    'update profile', 'my profile', 'change profile', 'client profile',
                ],
            ],
            'admin' => [
                'portal:admin:users' => [
                    'manage users', 'add user', 'create user', 'user management', 'admin users',
                ],
                'portal:admin:products' => [
                    'add product', 'manage products', 'create product', 'admin products',
                ],
                'portal:admin:subscriptions' => [
                    'manage subscriptions', 'admin subscriptions', 'subscription management',
                ],
                'portal:admin:orders' => [
                    'admin orders', 'manage orders', 'view payments', 'order management',
                ],
                'portal:admin:chatbot' => [
                    'chatbot settings', 'manage chatbot', 'chatbot faq', 'train chatbot',
                ],
                'portal:admin:notifications' => [
                    'broadcast', 'send notification', 'broadcast notification', 'announce to users',
                ],
                'portal:admin:roles' => [
                    'manage roles', 'permissions', 'role management', 'assign role',
                ],
                'portal:admin:tenants' => [
                    'manage tenants', 'add tenant', 'tenant management',
                ],
            ],
            'hr' => [
                'portal:hr:employees' => [
                    'manage employees', 'employee list', 'add employee', 'hr employees',
                ],
                'portal:hr:leave' => [
                    'approve leave', 'leave requests', 'leave approval', 'review leave',
                ],
                'portal:hr:attendance' => [
                    'review attendance', 'employee attendance', 'attendance report', 'hr attendance',
                ],
                'portal:hr:openings' => [
                    'job opening', 'post job', 'create opening', 'vacancy', 'recruitment',
                ],
                'portal:hr:applications' => [
                    'job applications', 'review applications', 'applicant', 'resume review',
                ],
            ],
            default => [],
        };

        foreach ($portalIntents as $intent => $phrases) {
            foreach ($phrases as $phrase) {
                if (str_contains($normalized, $phrase)) {
                    return $intent;
                }
            }
        }

        if (in_array($normalized, ['attendance', 'tasks', 'leave', 'timesheets', 'orders', 'invoices', 'users', 'products'], true)) {
            return match ($userRole) {
                'employee' => match ($normalized) {
                    'attendance' => 'portal:employee:attendance_mark',
                    'tasks' => 'portal:employee:tasks',
                    'leave' => 'portal:employee:leave',
                    'timesheets' => 'portal:employee:timesheets',
                    default => null,
                },
                'client' => match ($normalized) {
                    'orders' => 'portal:client:orders',
                    'invoices' => 'portal:client:invoices',
                    default => null,
                },
                'admin' => match ($normalized) {
                    'users' => 'portal:admin:users',
                    'products' => 'portal:admin:products',
                    default => null,
                },
                'hr' => match ($normalized) {
                    'attendance' => 'portal:hr:attendance',
                    'leave' => 'portal:hr:leave',
                    default => null,
                },
                default => null,
            };
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function portalTopicResponse(string $role, string $topic, string $language): array
    {
        if ($topic === 'help') {
            return $this->portalBrowseResponse($role, $language);
        }

        $category = 'portal_'.$role;
        $topicKey = 'topic:'.$topic;

        $faq = ChatbotFaq::query()
            ->where('is_active', true)
            ->where('language', $language)
            ->where('category', $category)
            ->where('keywords', 'like', '%'.$topicKey.'%')
            ->orderBy('sort_order')
            ->first()
            ?? ChatbotFaq::query()
                ->where('is_active', true)
                ->where('category', $category)
                ->where('keywords', 'like', '%'.$topicKey.'%')
                ->orderBy('sort_order')
                ->first();

        if ($faq === null) {
            return $this->portalBrowseResponse($role, $language);
        }

        return [
            'type' => 'faq_answer',
            'text' => $faq->answer,
            'faq' => [
                'id' => $faq->id,
                'question' => $faq->question,
                'answer' => $faq->answer,
            ],
            'quick_replies' => $this->localizedQuickReplies($language),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function portalBrowseResponse(string $role, string $language): array
    {
        $category = 'portal_'.$role;
        $portalPath = match ($role) {
            'admin' => '/admin',
            'employee' => '/employee',
            'client' => '/dashboard',
            'hr' => '/hr',
            default => '/',
        };

        $faqs = ChatbotFaq::query()
            ->where('is_active', true)
            ->where('language', $language)
            ->where('category', $category)
            ->where('keywords', 'not like', '%topic:help%')
            ->orderBy('sort_order')
            ->limit(8)
            ->get();

        $roleLabel = ucfirst($role === 'admin' ? 'Admin' : $role);

        return $this->buildFaqBrowseResponse(
            $faqs,
            $language,
            $portalPath,
            "{$roleLabel} portal help — choose a topic:",
            "{$roleLabel} portal मदत — विषय निवडा:",
            "{$roleLabel} portal सहायता — विषय चुनें:",
            "Open {$roleLabel} portal",
            "{$roleLabel} portal उघडा",
            "{$roleLabel} portal खोलें",
        );
    }
}
