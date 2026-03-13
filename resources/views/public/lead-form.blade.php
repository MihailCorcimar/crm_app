<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $leadForm->name }}</title>
    <style>
        :root {
            color-scheme: light;
        }
        body {
            margin: 0;
            background: #f4f4f5;
            color: #111827;
            font-family: "Segoe UI", Tahoma, sans-serif;
            line-height: 1.4;
        }
        .wrap {
            max-width: 760px;
            margin: 0 auto;
            padding: 24px;
        }
        .card {
            background: #fff;
            border: 1px solid #e4e4e7;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.04);
        }
        h1 {
            margin: 0 0 6px 0;
            font-size: 22px;
        }
        .muted {
            margin: 0 0 18px 0;
            color: #6b7280;
            font-size: 14px;
        }
        .grid {
            display: grid;
            gap: 14px;
        }
        label {
            display: block;
            margin-bottom: 6px;
            font-size: 14px;
            font-weight: 600;
        }
        input, textarea, select {
            box-sizing: border-box;
            width: 100%;
            border: 1px solid #d4d4d8;
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 15px;
            background: #fff;
        }
        textarea {
            min-height: 120px;
            resize: vertical;
        }
        .error {
            margin-top: 6px;
            color: #b91c1c;
            font-size: 13px;
        }
        .alert-success {
            border: 1px solid #86efac;
            background: #f0fdf4;
            color: #166534;
            border-radius: 8px;
            padding: 10px 12px;
            margin-bottom: 14px;
        }
        .actions {
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        button {
            border: 0;
            border-radius: 8px;
            background: #111827;
            color: #fff;
            font-weight: 600;
            padding: 10px 14px;
            cursor: pointer;
        }
        button:hover {
            background: #000;
        }
        .required {
            color: #b91c1c;
        }
        .foot {
            margin-top: 14px;
            color: #71717a;
            font-size: 12px;
        }
        .hide {
            position: absolute;
            left: -9999px;
            width: 1px;
            height: 1px;
            opacity: 0;
            pointer-events: none;
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <h1>{{ $leadForm->name }}</h1>

            @if ($successMessage)
                <div class="alert-success">{{ $successMessage }}</div>
            @endif

            @if ($errors->any())
                <div class="error" style="margin-bottom: 12px;">Verifica os campos assinalados e tenta novamente.</div>
            @endif

            <form method="POST" action="{{ route('public.lead-forms.submit', ['token' => $leadForm->embed_token, 'mode' => $mode]) }}">
                @csrf
                <input type="hidden" name="source_type" value="{{ $mode }}">
                <input type="hidden" name="source_url" id="source_url" value="{{ old('source_url') }}">

                <div class="hide" aria-hidden="true">
                    <label for="website">Website</label>
                    <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
                </div>

                <div class="grid">
                    @foreach ($enabledFields as $field)
                        @php
                            $fieldKey = $field['key'];
                            $fieldLabel = $field['label'];
                            $fieldType = $field['type'];
                            $fieldRequired = (bool) $field['required'];
                            $oldValue = old($fieldKey);
                        @endphp

                        <div>
                            @if ($fieldType !== 'checkbox')
                                <label for="{{ $fieldKey }}">
                                    {{ $fieldLabel }}
                                    @if ($fieldRequired)
                                        <span class="required">*</span>
                                    @endif
                                </label>
                            @endif

                            @if ($fieldType === 'textarea')
                                <textarea
                                    id="{{ $fieldKey }}"
                                    name="{{ $fieldKey }}"
                                    @if ($fieldRequired) required @endif
                                >{{ is_string($oldValue) ? $oldValue : '' }}</textarea>
                            @elseif ($fieldType === 'select')
                                @php
                                    $fieldOptions = is_array($field['options'] ?? null) ? $field['options'] : [];
                                    $selectedValue = is_string($oldValue) ? $oldValue : '';
                                @endphp
                                <select id="{{ $fieldKey }}" name="{{ $fieldKey }}" @if ($fieldRequired) required @endif>
                                    <option value="">Selecionar</option>
                                    @foreach ($fieldOptions as $option)
                                        @php
                                            $optionValue = trim((string) $option);
                                        @endphp
                                        @if ($optionValue !== '')
                                            <option value="{{ $optionValue }}" @selected($selectedValue === $optionValue)>
                                                {{ $optionValue }}
                                            </option>
                                        @endif
                                    @endforeach
                                </select>
                            @elseif ($fieldType === 'checkbox')
                                @php
                                    $checked = old($fieldKey) === '1' || old($fieldKey) === 1 || old($fieldKey) === true || old($fieldKey) === 'on';
                                @endphp
                                <label for="{{ $fieldKey }}" style="display:flex;align-items:center;gap:8px;font-weight:400;margin-bottom:0;">
                                    <input
                                        id="{{ $fieldKey }}"
                                        name="{{ $fieldKey }}"
                                        type="checkbox"
                                        value="1"
                                        @checked($checked)
                                        style="width:auto;"
                                    >
                                    <span>
                                        {{ $fieldLabel }}
                                        @if ($fieldRequired)
                                            <span class="required">*</span>
                                        @endif
                                    </span>
                                </label>
                            @else
                                <input
                                    id="{{ $fieldKey }}"
                                    name="{{ $fieldKey }}"
                                    type="{{ $fieldType }}"
                                    value="{{ is_string($oldValue) ? $oldValue : '' }}"
                                    @if ($fieldRequired) required @endif
                                >
                            @endif

                            @error($fieldKey)
                                <div class="error">{{ $message }}</div>
                            @enderror
                        </div>
                    @endforeach

                    @if ($leadForm->requires_captcha)
                        @if (($useTurnstile ?? false) && ! empty($turnstileSiteKey))
                            <div>
                                <label>
                                    Captcha obrigatorio <span class="required">*</span>
                                </label>
                                <div
                                    class="cf-turnstile"
                                    data-sitekey="{{ $turnstileSiteKey }}"
                                    data-theme="light"
                                    data-size="normal"
                                    data-appearance="always"
                                ></div>
                                @error('cf-turnstile-response')
                                    <div class="error">{{ $message }}</div>
                                @enderror
                            </div>
                        @elseif (is_array($captcha))
                            <div>
                                <label for="captcha_answer">
                                    Captcha obrigatorio <span class="required">*</span>
                                </label>
                                <input
                                    id="captcha_answer"
                                    name="captcha_answer"
                                    type="number"
                                    required
                                    placeholder="Quanto e {{ $captcha['a'] }} + {{ $captcha['b'] }}?"
                                    value="{{ old('captcha_answer') }}"
                                >
                                @error('captcha_answer')
                                    <div class="error">{{ $message }}</div>
                                @enderror
                            </div>
                        @endif
                    @endif
                </div>

                <div class="actions">
                    <button type="submit">Enviar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function () {
            const sourceInput = document.getElementById('source_url');
            if (!sourceInput || sourceInput.value) {
                return;
            }

            const referrer = document.referrer || '';
            sourceInput.value = referrer || window.location.href;
        })();
    </script>
    @if (($useTurnstile ?? false) && ! empty($turnstileSiteKey))
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    @endif
</body>
</html>
