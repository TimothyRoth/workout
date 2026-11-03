<?php

namespace App\Http;

/**
 * Typed, validated access to request input. Every accessor throws a 400
 * HttpException when the value is missing or malformed, so controllers
 * never pass unchecked strings into the database layer.
 */
class Request
{
    private array $json = [];
    private bool $jsonParsed = false;

    public function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $_SERVER[$key] ?? null;
    }

    public function raw(string $key): mixed
    {
        if ($this->isPost()) {
            if (array_key_exists($key, $_POST)) {
                return $_POST[$key];
            }
            $json = $this->json();
            if (array_key_exists($key, $json)) {
                return $json[$key];
            }
        }
        return $_GET[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return $this->raw($key) !== null;
    }

    public function int(string $key, int $min = 1, int $max = PHP_INT_MAX): int
    {
        $value = $this->raw($key);
        if (is_string($value)) {
            $value = trim($value);
        }
        if ($value === null || $value === '' || filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw HttpException::badRequest("Feld „{$key}“ muss eine ganze Zahl sein.");
        }
        $int = (int)$value;
        if ($int < $min || $int > $max) {
            throw HttpException::badRequest("Feld „{$key}“ muss zwischen {$min} und {$max} liegen.");
        }
        return $int;
    }

    public function number(string $key, float $min = 0, float $max = PHP_FLOAT_MAX): float
    {
        $value = $this->raw($key);
        if (is_string($value)) {
            $value = str_replace(',', '.', trim($value));
        }
        if ($value === null || $value === '' || !is_numeric($value)) {
            throw HttpException::badRequest("Feld „{$key}“ muss eine Zahl sein.");
        }
        $float = (float)$value;
        if ($float < $min || $float > $max) {
            throw HttpException::badRequest("Feld „{$key}“ muss zwischen {$min} und {$max} liegen.");
        }
        return $float;
    }

    /**
     * Value of a string-backed enum.
     *
     * @template T of \BackedEnum
     * @param class-string<T> $enumClass
     * @return T
     */
    public function enum(string $key, string $enumClass): \BackedEnum
    {
        $value = $this->raw($key);
        $case = is_string($value) ? $enumClass::tryFrom(trim($value)) : null;
        if ($case === null) {
            throw HttpException::badRequest("Feld „{$key}“ hat einen ungültigen Wert.");
        }
        return $case;
    }

    public function string(string $key, int $maxLength = 100, bool $required = true): string
    {
        $value = $this->raw($key);
        if (!is_string($value)) {
            $value = $value === null ? '' : (string)$value;
        }
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        if ($required && $value === '') {
            throw HttpException::badRequest("Feld „{$key}“ darf nicht leer sein.");
        }
        if (mb_strlen($value) > $maxLength) {
            throw HttpException::badRequest("Feld „{$key}“ darf höchstens {$maxLength} Zeichen lang sein.");
        }
        return $value;
    }

    /**
     * Optional string that may legitimately be absent.
     */
    public function optionalString(string $key, int $maxLength = 100): ?string
    {
        return $this->has($key) ? $this->string($key, $maxLength, false) : null;
    }

    public function json(): array
    {
        if (!$this->jsonParsed) {
            $this->jsonParsed = true;
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            if (str_contains($contentType, 'application/json')) {
                $body = file_get_contents('php://input');
                $decoded = json_decode($body ?: '', true);
                if (!is_array($decoded)) {
                    throw HttpException::badRequest('Ungültiges JSON.');
                }
                $this->json = $decoded;
            }
        }
        return $this->json;
    }
}
