<?php

use App\Services\Ai\AiActivitySemanticService;
use App\Services\Ai\AiNoteAnonymizer;
use Illuminate\Support\Facades\Http;

uses(Tests\TestCase::class);

function makeSemanticService(): AiActivitySemanticService
{
    return new AiActivitySemanticService(new AiNoteAnonymizer);
}

// ---------------------------------------------------------------------------
// Empty / trivial inputs (no framework needed, but grouped for consistency)
// ---------------------------------------------------------------------------

it('returns no follow-up for empty notes array', function (): void {
    $result = makeSemanticService()->analyze([]);

    expect($result['needs_follow_up'])->toBeFalse()
        ->and($result['reason'])->toBe('Sem atividade recente para analise.');
});

it('returns no follow-up for notes with only whitespace', function (): void {
    $result = makeSemanticService()->analyze(['   ', "\t", '']);

    expect($result['needs_follow_up'])->toBeFalse()
        ->and($result['reason'])->toBe('Sem atividade recente para analise.');
});

// ---------------------------------------------------------------------------
// Local fallback — no API key configured
// ---------------------------------------------------------------------------

it('uses local fallback when no API key is configured', function (): void {
    config(['services.openai.api_key' => '']);

    $result = makeSemanticService()->analyze(['Cliente sem resposta após reunião.']);

    expect($result)->toHaveKeys(['needs_follow_up', 'reason']);
});

it('detects follow-up need from risk signals alone', function (): void {
    config(['services.openai.api_key' => '']);

    $result = makeSemanticService()->analyze(['Negócio pendente, sem resposta do cliente.']);

    expect($result['needs_follow_up'])->toBeTrue();
});

it('detects follow-up need from request + risk signals combined', function (): void {
    config(['services.openai.api_key' => '']);

    $result = makeSemanticService()->analyze([
        'Cliente solicitou proposta.',
        'Sem retorno após envio.',
    ]);

    expect($result['needs_follow_up'])->toBeTrue()
        ->and($result['reason'])->not->toBeEmpty();
});

it('does not flag follow-up for request signal without risk signal', function (): void {
    config(['services.openai.api_key' => '']);

    $result = makeSemanticService()->analyze(['Cliente pediu orçamento.']);

    expect($result['needs_follow_up'])->toBeFalse();
});

it('returns false when no signals are present', function (): void {
    config(['services.openai.api_key' => '']);

    $result = makeSemanticService()->analyze(['Reunião correu bem, cliente satisfeito.']);

    expect($result['needs_follow_up'])->toBeFalse()
        ->and($result['reason'])->toBe('Nao foram detetados sinais criticos.');
});

it('is case-insensitive for signal detection', function (): void {
    config(['services.openai.api_key' => '']);

    // Signal 'pendente' in uppercase — mb_strtolower normalises it correctly
    $result = makeSemanticService()->analyze(['Negócio PENDENTE, aguarda resposta.']);

    expect($result['needs_follow_up'])->toBeTrue();
});

it('matches risk signal urgente', function (): void {
    config(['services.openai.api_key' => '']);

    $result = makeSemanticService()->analyze(['Situação urgente, cliente aguarda resposta.']);

    expect($result['needs_follow_up'])->toBeTrue();
});

it('matches risk signal sem retorno', function (): void {
    config(['services.openai.api_key' => '']);

    $result = makeSemanticService()->analyze(['Ligação feita, sem retorno até hoje.']);

    expect($result['needs_follow_up'])->toBeTrue();
});

// ---------------------------------------------------------------------------
// OpenAI API — successful response
// ---------------------------------------------------------------------------

it('returns parsed result from successful API response', function (): void {
    config(['services.openai.api_key' => 'test-key-123']);

    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'needs_follow_up' => true,
                            'reason' => 'Cliente aguarda proposta.',
                        ]),
                    ],
                ],
            ],
        ], 200),
    ]);

    $result = makeSemanticService()->analyze(['Reunião com cliente, pediu proposta.']);

    expect($result['needs_follow_up'])->toBeTrue()
        ->and($result['reason'])->toBe('Cliente aguarda proposta.');
});

it('falls back to local analysis on API error response', function (): void {
    config(['services.openai.api_key' => 'test-key-123']);

    Http::fake([
        'api.openai.com/*' => Http::response([], 500),
    ]);

    $result = makeSemanticService()->analyze(['urgente, sem resposta']);

    expect($result['needs_follow_up'])->toBeTrue();
});

it('falls back to local analysis when API returns malformed JSON', function (): void {
    config(['services.openai.api_key' => 'test-key-123']);

    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [
                ['message' => ['content' => 'not valid json at all']],
            ],
        ], 200),
    ]);

    $result = makeSemanticService()->analyze(['Reunião correu bem.']);

    expect($result)->toHaveKeys(['needs_follow_up', 'reason']);
});

it('falls back to local analysis when API throws an exception', function (): void {
    config(['services.openai.api_key' => 'test-key-123']);

    Http::fake(fn () => throw new RuntimeException('Connection refused'));

    $result = makeSemanticService()->analyze(['pendente, cliente não respondeu']);

    expect($result['needs_follow_up'])->toBeTrue();
});

// ---------------------------------------------------------------------------
// PII is anonymized before sending to API
// ---------------------------------------------------------------------------

it('sends anonymized notes to the API, not raw PII', function (): void {
    config(['services.openai.api_key' => 'test-key-123']);

    $captured = null;

    Http::fake(function ($request) use (&$captured) {
        $captured = $request->data();

        return Http::response([
            'choices' => [['message' => ['content' => '{"needs_follow_up":false,"reason":"ok"}']]],
        ], 200);
    });

    makeSemanticService()->analyze(['Reunião com João Silva (joao@empresa.pt) sobre proposta.']);

    expect($captured)->not->toBeNull();

    $sentContent = collect(data_get($captured, 'messages'))
        ->firstWhere('role', 'user')['content'] ?? '';

    expect($sentContent)
        ->not->toContain('João Silva')
        ->not->toContain('joao@empresa.pt')
        ->toContain('[PESSOA_')
        ->toContain('[EMAIL_');
});
