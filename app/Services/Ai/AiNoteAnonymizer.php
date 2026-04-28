<?php

namespace App\Services\Ai;

class AiNoteAnonymizer
{
    private int $personCounter = 0;
    private int $valueCounter = 0;
    private int $emailCounter = 0;
    private int $phoneCounter = 0;

    /** @var array<string, string> */
    private array $tokenMap = [];

    /**
     * Anonymizes a batch of notes in-place, replacing PII with stable tokens.
     * The same value appearing in multiple notes gets the same token,
     * preserving semantic coherence without exposing real data.
     *
     * @param  list<string>  $notes
     * @return list<string>
     */
    public function anonymize(array $notes): array
    {
        $this->reset();

        return array_values(
            array_map(fn (string $note): string => $this->processNote($note), $notes)
        );
    }

    private function processNote(string $note): string
    {
        // Order matters: emails before phones (emails contain @),
        // monetary values before names (avoid tokenising digits inside values)
        $note = $this->maskEmails($note);
        $note = $this->maskPhones($note);
        $note = $this->maskMonetaryValues($note);
        $note = $this->maskProperNames($note);

        return $note;
    }

    private function maskEmails(string $text): string
    {
        return (string) preg_replace_callback(
            '/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/u',
            fn (array $m): string => $this->token($m[0], 'email'),
            $text
        );
    }

    private function maskPhones(string $text): string
    {
        // Matches Portuguese mobiles (9XX) and landlines (2XX), with optional +351 prefix
        return (string) preg_replace_callback(
            '/\b(?:\+351[\s.]?)?(?:2\d|9[1236]\d)[\s.\-]?\d{3}[\s.\-]?\d{3}\b/u',
            fn (array $m): string => $this->token($m[0], 'phone'),
            $text
        );
    }

    private function maskMonetaryValues(string $text): string
    {
        return (string) preg_replace_callback(
            '/(?:€\s*[\d.,]+(?:\s*[kKmM])?|[\d.,]+\s*[kKmM]?\s*(?:€|EUR|eur|euros?))/u',
            fn (array $m): string => $this->token($m[0], 'value'),
            $text
        );
    }

    private function maskProperNames(string $text): string
    {
        // Matches sequences of 2+ title-case words, e.g. "António Silva", "Maria João Ferreira"
        return (string) preg_replace_callback(
            '/\b[A-ZÁÉÍÓÚÀÂÊÔÃÕÜÇ][a-záéíóúàâêôãõüç]+(?:\s+[A-ZÁÉÍÓÚÀÂÊÔÃÕÜÇ][a-záéíóúàâêôãõüç]+)+\b/u',
            fn (array $m): string => $this->token($m[0], 'person'),
            $text
        );
    }

    private function token(string $original, string $type): string
    {
        if (isset($this->tokenMap[$original])) {
            return $this->tokenMap[$original];
        }

        $token = match ($type) {
            'email'  => '[EMAIL_'.(++$this->emailCounter).']',
            'phone'  => '[TELEFONE_'.(++$this->phoneCounter).']',
            'value'  => '[VALOR_'.(++$this->valueCounter).']',
            default  => '[PESSOA_'.(++$this->personCounter).']',
        };

        $this->tokenMap[$original] = $token;

        return $token;
    }

    private function reset(): void
    {
        $this->personCounter = 0;
        $this->valueCounter  = 0;
        $this->emailCounter  = 0;
        $this->phoneCounter  = 0;
        $this->tokenMap      = [];
    }
}
