<?php

namespace App\Services\GrowthOS\AI\Exceptions;

use RuntimeException;

class GrowthAiException extends RuntimeException
{
    public function __construct(
        public readonly string $errorType,
        public readonly string $userMessage,
        public readonly bool $retryable = false,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($errorType, 0, $previous);
    }

    public static function unavailable(string $errorType, bool $retryable = true, ?\Throwable $previous = null): self
    {
        return new self(
            errorType: $errorType,
            userMessage: 'AI jest chwilowo niedostępne. Możesz kontynuować ręcznie.',
            retryable: $retryable,
            previous: $previous,
        );
    }

    public static function invalidResponse(string $errorType = 'invalid_structure'): self
    {
        return new self(
            errorType: $errorType,
            userMessage: 'Nie udało się przygotować poprawnej propozycji AI. Twoja obecna koncepcja nie została zmieniona.',
        );
    }

    public static function dataPolicyViolation(): self
    {
        return new self(
            errorType: 'data_policy_violation',
            userMessage: 'Treść wygląda na zawierającą dane osobowe lub poufne. Usuń je przed wysłaniem do AI.',
        );
    }
}
