<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\HttpFoundation\Request;

/**
 * Typed, validated access to request input: form fields, then the JSON body, then the query string.
 * Every accessor throws a 400 UserError with a German message when the value is missing or malformed,
 * so controllers never pass unchecked strings into the repositories.
 */
final class RequestInput
{
    private ?array $json = null;

    public function __construct(private readonly Request $request)
    {
    }

    public function raw(string $key): mixed
    {
        if ($this->request->isMethod('POST')) {
            $form = $this->request->request->all();
            if (\array_key_exists($key, $form)) {
                return $form[$key];
            }
            $json = $this->json();
            if (\array_key_exists($key, $json)) {
                return $json[$key];
            }
        }

        return $this->request->query->all()[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return null !== $this->raw($key);
    }

    public function int(string $key, int $min = 1, int $max = \PHP_INT_MAX): int
    {
        $value = $this->raw($key);
        if (\is_string($value)) {
            $value = trim($value);
        }
        if (null === $value || '' === $value || \is_array($value) || false === filter_var($value, \FILTER_VALIDATE_INT)) {
            throw UserError::badRequest("Feld „{$key}“ muss eine ganze Zahl sein.");
        }
        $int = (int) $value;
        if ($int < $min || $int > $max) {
            throw UserError::badRequest("Feld „{$key}“ muss zwischen {$min} und {$max} liegen.");
        }

        return $int;
    }

    public function number(string $key, float $min = 0, float $max = \PHP_FLOAT_MAX): float
    {
        $value = $this->raw($key);
        if (\is_string($value)) {
            $value = str_replace(',', '.', trim($value));
        }
        if (null === $value || '' === $value || !is_numeric($value)) {
            throw UserError::badRequest("Feld „{$key}“ muss eine Zahl sein.");
        }
        $float = (float) $value;
        if ($float < $min || $float > $max) {
            throw UserError::badRequest("Feld „{$key}“ muss zwischen ".self::plain($min).' und '.self::plain($max).' liegen.');
        }

        return $float;
    }

    /**
     * @template T of \BackedEnum
     *
     * @param class-string<T> $enumClass
     *
     * @return T
     */
    public function enum(string $key, string $enumClass): \BackedEnum
    {
        $value = $this->raw($key);
        $case = \is_string($value) ? $enumClass::tryFrom(trim($value)) : null;

        return $case ?? throw UserError::badRequest("Feld „{$key}“ hat einen ungültigen Wert.");
    }

    /** Single-line text: whitespace runs collapsed, trimmed, length-limited. */
    public function string(string $key, int $maxLength = 100, bool $required = true): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', self::text_value($this->raw($key))) ?? '');
        if ($required && '' === $value) {
            throw UserError::badRequest("Feld „{$key}“ darf nicht leer sein.");
        }
        if (mb_strlen($value) > $maxLength) {
            throw UserError::badRequest("Feld „{$key}“ darf höchstens {$maxLength} Zeichen lang sein.");
        }

        return $value;
    }

    /** Free text that keeps its line breaks (notes): trimmed, CRLF normalised, length-limited. */
    public function text(string $key, int $maxLength = 1000): string
    {
        $value = trim(str_replace(["\r\n", "\r"], "\n", self::text_value($this->raw($key))));
        if (mb_strlen($value) > $maxLength) {
            throw UserError::badRequest("Feld „{$key}“ darf höchstens {$maxLength} Zeichen lang sein.");
        }

        return $value;
    }

    private function json(): array
    {
        if (null === $this->json) {
            $this->json = [];
            if (str_contains((string) $this->request->headers->get('Content-Type'), 'application/json')) {
                $decoded = json_decode($this->request->getContent() ?: '', true);
                if (!\is_array($decoded)) {
                    throw UserError::badRequest('Ungültiges JSON.');
                }
                $this->json = $decoded;
            }
        }

        return $this->json;
    }

    private static function text_value(mixed $value): string
    {
        return \is_string($value) ? $value : (null === $value || \is_array($value) ? '' : (string) $value);
    }

    /** The bounds as the old app printed them: PHP's default float-to-string conversion. */
    private static function plain(float $value): string
    {
        return (string) $value;
    }
}
