<?php

namespace App\Http\Controllers;

use App\Models\LeadForm;
use App\Support\LeadCaptureService;
use App\Support\LeadFormFieldCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class PublicLeadFormController extends Controller
{
    public function __construct(
        private readonly LeadFormFieldCatalog $fieldCatalog,
        private readonly LeadCaptureService $leadCaptureService
    ) {
    }

    public function show(Request $request, string $token): HttpResponse
    {
        $leadForm = $this->resolveActiveForm($token);
        $normalizedSchema = $this->fieldCatalog->normalize((array) $leadForm->field_schema);
        $enabledFields = $this->fieldCatalog->enabledFields($normalizedSchema);

        $mode = $this->resolveSourceType($request->query('mode'));
        $useTurnstile = $leadForm->requires_captcha && $this->turnstileEnabled();
        $captcha = null;
        if ($leadForm->requires_captcha && ! $useTurnstile) {
            $captcha = $this->getOrCreateCaptcha($request, $token);
        }

        return response()->view('public.lead-form', [
            'leadForm' => $leadForm,
            'enabledFields' => $enabledFields,
            'mode' => $mode,
            'captcha' => $captcha,
            'useTurnstile' => $useTurnstile,
            'turnstileSiteKey' => $useTurnstile ? $this->turnstileSiteKey() : null,
            'successMessage' => session('lead_form_success'),
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function submit(Request $request, string $token): RedirectResponse
    {
        $leadForm = $this->resolveActiveForm($token);
        $normalizedSchema = $this->fieldCatalog->normalize((array) $leadForm->field_schema);
        $dynamicRules = $this->fieldCatalog->submissionRules($normalizedSchema);
        $useTurnstile = $leadForm->requires_captcha && $this->turnstileEnabled();

        $rules = array_merge($dynamicRules, [
            'website' => ['nullable', 'max:0'],
            'source_type' => ['nullable', 'string', 'in:public_page,iframe,script'],
            'source_url' => ['nullable', 'string', 'max:2048'],
        ]);

        if ($leadForm->requires_captcha) {
            if ($useTurnstile) {
                $rules['cf-turnstile-response'] = ['required', 'string', 'max:4096'];
            } else {
                $rules['captcha_answer'] = ['required', 'integer'];
            }
        }

        $validated = Validator::make($request->all(), $rules)->validate();

        if ($leadForm->requires_captcha) {
            if ($useTurnstile) {
                if (! $this->validateTurnstileToken($request, (string) ($validated['cf-turnstile-response'] ?? ''))) {
                    throw ValidationException::withMessages([
                        'cf-turnstile-response' => 'Validacao de captcha falhou. Tente novamente.',
                    ]);
                }
            } elseif (! $this->validateCaptcha($request, $token, (int) $validated['captcha_answer'])) {
                throw ValidationException::withMessages([
                    'captcha_answer' => 'Resposta de captcha invalida. Tente novamente.',
                ]);
            }
        }

        $sourceType = $this->resolveSourceType($validated['source_type'] ?? $request->query('mode'));
        $sourceUrl = $this->nullableString($validated['source_url'] ?? null);
        if ($sourceUrl === null) {
            $sourceUrl = $this->nullableString($request->headers->get('referer'));
        }

        $sourceOrigin = $this->extractOrigin(
            $this->nullableString($request->headers->get('origin')),
            $sourceUrl
        );

        $this->leadCaptureService->capture(
            $leadForm,
            $this->submissionPayload($normalizedSchema, $validated),
            $sourceType,
            $sourceUrl,
            $sourceOrigin,
            $request->ip(),
            $request->userAgent()
        );

        if (! $useTurnstile) {
            $request->session()->forget($this->captchaSessionKey($token));
        }

        return redirect()
            ->route('public.lead-forms.show', [
                'token' => $token,
                'mode' => $sourceType,
            ])
            ->with('lead_form_success', (string) $leadForm->confirmation_message);
    }

    public function embedScript(Request $request, string $token): HttpResponse
    {
        $leadForm = $this->resolveActiveForm($token);
        $iframeUrl = route('public.lead-forms.show', ['token' => $leadForm->embed_token, 'mode' => 'script']);
        $tokenJson = json_encode($leadForm->embed_token) ?: '""';
        $iframeUrlJson = json_encode($iframeUrl) ?: '""';

        $script = <<<JS
(function () {
    const token = {$tokenJson};
    const iframeUrl = {$iframeUrlJson};
    const selector = '[data-crm-lead-form="' + token + '"]';
    const scriptTag = document.currentScript;
    let targets = Array.from(document.querySelectorAll(selector));

    if (targets.length === 0 && scriptTag) {
        const autoContainer = document.createElement('div');
        autoContainer.setAttribute('data-crm-lead-form', token);
        scriptTag.insertAdjacentElement('afterend', autoContainer);
        targets = [autoContainer];
    }

    targets.forEach(function (target) {
        if (!target || target.getAttribute('data-crm-lead-form-loaded') === '1') {
            return;
        }

        const iframe = document.createElement('iframe');
        iframe.src = iframeUrl;
        iframe.width = '100%';
        iframe.height = '680';
        iframe.loading = 'lazy';
        iframe.style.border = '0';
        iframe.setAttribute('title', 'Formulário de lead');

        target.appendChild(iframe);
        target.setAttribute('data-crm-lead-form-loaded', '1');
    });
})();
JS;

        return response($script, 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'public, max-age=60',
        ]);
    }

    private function resolveActiveForm(string $token): LeadForm
    {
        $leadForm = LeadForm::withoutGlobalScopes()
            ->where('embed_token', $token)
            ->where('status', LeadForm::STATUS_ACTIVE)
            ->first();

        if ($leadForm === null) {
            throw new NotFoundHttpException();
        }

        return $leadForm;
    }

    /**
     * @return array{a: int, b: int, answer: int}
     */
    private function getOrCreateCaptcha(Request $request, string $token): array
    {
        $sessionKey = $this->captchaSessionKey($token);
        $current = $request->session()->get($sessionKey);

        if (
            is_array($current)
            && isset($current['a'], $current['b'], $current['answer'], $current['generated_at'])
            && now()->diffInMinutes($current['generated_at']) <= 30
        ) {
            /** @var array{a: int, b: int, answer: int} $current */
            return [
                'a' => (int) $current['a'],
                'b' => (int) $current['b'],
                'answer' => (int) $current['answer'],
            ];
        }

        $a = random_int(1, 9);
        $b = random_int(1, 9);
        $captcha = [
            'a' => $a,
            'b' => $b,
            'answer' => $a + $b,
            'generated_at' => now(),
        ];

        $request->session()->put($sessionKey, $captcha);

        return [
            'a' => $a,
            'b' => $b,
            'answer' => $a + $b,
        ];
    }

    private function validateCaptcha(Request $request, string $token, int $answer): bool
    {
        $captcha = $request->session()->get($this->captchaSessionKey($token));
        if (! is_array($captcha)) {
            return false;
        }

        $generatedAt = $captcha['generated_at'] ?? null;
        if ($generatedAt === null || now()->diffInMinutes($generatedAt) > 30) {
            return false;
        }

        return (int) ($captcha['answer'] ?? -1) === $answer;
    }

    private function captchaSessionKey(string $token): string
    {
        return 'lead_form_captcha.'.$token;
    }

    private function turnstileEnabled(): bool
    {
        return $this->turnstileSiteKey() !== null
            && $this->turnstileSecretKey() !== null;
    }

    private function turnstileSiteKey(): ?string
    {
        $key = trim((string) config('services.turnstile.site_key', ''));

        return $key === '' ? null : $key;
    }

    private function turnstileSecretKey(): ?string
    {
        $key = trim((string) config('services.turnstile.secret_key', ''));

        return $key === '' ? null : $key;
    }

    private function validateTurnstileToken(Request $request, string $token): bool
    {
        $secret = $this->turnstileSecretKey();
        if ($secret === null || trim($token) === '') {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(10)
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => $request->ip(),
                ]);

            if (! $response->ok()) {
                return false;
            }

            $payload = $response->json();

            return is_array($payload) && (bool) ($payload['success'] ?? false);
        } catch (Throwable) {
            return false;
        }
    }

    private function resolveSourceType(mixed $value): string
    {
        $normalized = trim((string) $value);

        return in_array($normalized, ['public_page', 'iframe', 'script'], true)
            ? $normalized
            : 'public_page';
    }

    private function extractOrigin(?string $originHeader, ?string $sourceUrl): ?string
    {
        $origin = $this->nullableString($originHeader);
        if ($origin !== null) {
            return $origin;
        }

        if ($sourceUrl === null) {
            return null;
        }

        $parsed = parse_url($sourceUrl, PHP_URL_HOST);
        if (! is_string($parsed) || trim($parsed) === '') {
            return null;
        }

        return trim($parsed);
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    /**
     * @param  array<int, array{key: string, type: string, enabled: bool}>  $schema
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function submissionPayload(array $schema, array $validated): array
    {
        $payload = [];

        foreach ($schema as $field) {
            if (! (bool) ($field['enabled'] ?? false)) {
                continue;
            }

            $key = (string) ($field['key'] ?? '');
            if ($key === '') {
                continue;
            }

            $type = (string) ($field['type'] ?? 'text');
            $value = $validated[$key] ?? null;

            if ($type === 'checkbox') {
                $payload[$key] = (bool) $value;
                continue;
            }

            $payload[$key] = $value;
        }

        return $payload;
    }
}
