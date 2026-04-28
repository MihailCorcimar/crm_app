<?php

use App\Services\Ai\AiNoteAnonymizer;

describe('AiNoteAnonymizer', function (): void {
    beforeEach(function (): void {
        $this->anonymizer = new AiNoteAnonymizer;
    });

    it('replaces full names with person tokens', function (): void {
        $result = $this->anonymizer->anonymize(['Reunião com António Silva sobre proposta.']);

        expect($result[0])->toBe('Reunião com [PESSOA_1] sobre proposta.');
    });

    it('replaces email addresses', function (): void {
        $result = $this->anonymizer->anonymize(['Enviei email para joao.silva@empresa.pt ontem.']);

        expect($result[0])->toBe('Enviei email para [EMAIL_1] ontem.');
    });

    it('replaces portuguese mobile numbers', function (): void {
        $result = $this->anonymizer->anonymize(['Ligar para 912 345 678 amanhã.']);

        expect($result[0])->toBe('Ligar para [TELEFONE_1] amanhã.');
    });

    it('replaces monetary values with euro symbol', function (): void {
        $result = $this->anonymizer->anonymize(['Proposta de 50.000€ enviada.']);

        expect($result[0])->toBe('Proposta de [VALOR_1] enviada.');
    });

    it('replaces monetary values with EUR suffix', function (): void {
        $result = $this->anonymizer->anonymize(['Negócio no valor de 25k EUR fechado.']);

        expect($result[0])->toBe('Negócio no valor de [VALOR_1] fechado.');
    });

    it('assigns same token to repeated values across notes', function (): void {
        $result = $this->anonymizer->anonymize([
            'Reunião com Maria Ferreira.',
            'Maria Ferreira pediu follow-up.',
        ]);

        expect($result[0])->toBe('Reunião com [PESSOA_1].')
            ->and($result[1])->toBe('[PESSOA_1] pediu follow-up.');
    });

    it('assigns different tokens to different names', function (): void {
        $result = $this->anonymizer->anonymize(['António Silva falou com Maria João.']);

        expect($result[0])
            ->toContain('[PESSOA_1]')
            ->toContain('[PESSOA_2]')
            ->not->toContain('António Silva')
            ->not->toContain('Maria João');
    });

    it('preserves semantic content after anonymisation', function (): void {
        $result = $this->anonymizer->anonymize(['Cliente pediu orçamento urgente.']);

        expect($result[0])->toBe('Cliente pediu orçamento urgente.');
    });

    it('handles empty notes list', function (): void {
        $result = $this->anonymizer->anonymize([]);

        expect($result)->toBe([]);
    });

    it('resets state between anonymize calls', function (): void {
        $this->anonymizer->anonymize(['António Silva']);
        $result = $this->anonymizer->anonymize(['António Silva']);

        expect($result[0])->toBe('[PESSOA_1]');
    });
});
