<?php
/**
 * HSMS - Server-Side Data Validation Engine
 */

declare(strict_types=1);

class Validator {
    private array $data;
    private array $errors = [];

    public function __construct(array $data) {
        $this->data = $data;
    }

    public function required(string $field, string $label): self {
        $val = trim((string)($this->data[$field] ?? ''));
        if ($val === '') {
            $this->errors[$field] = "{$label} is required.";
        }
        return $this;
    }

    public function email(string $field, string $label): self {
        $val = trim((string)($this->data[$field] ?? ''));
        if ($val !== '' && !filter_var($val, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = "{$label} must be a valid email address.";
        }
        return $this;
    }

    public function numeric(string $field, string $label): self {
        $val = $this->data[$field] ?? null;
        if ($val !== null && $val !== '' && !is_numeric($val)) {
            $this->errors[$field] = "{$label} must be a numeric value.";
        }
        return $this;
    }

    public function minNumber(string $field, float $min, string $label): self {
        $val = $this->data[$field] ?? null;
        if ($val !== null && $val !== '' && is_numeric($val) && (float)$val < $min) {
            $this->errors[$field] = "{$label} must be at least " . number_format($min, 2) . ".";
        }
        return $this;
    }

    public function cnic(string $field, string $label): self {
        $val = trim((string)($this->data[$field] ?? ''));
        if ($val !== '') {
            // Pakistan CNIC standard format 13 digits with or without hyphens: 12345-1234567-1
            $clean = str_replace('-', '', $val);
            if (!preg_match('/^[0-9]{13}$/', $clean)) {
                $this->errors[$field] = "{$label} must be a valid 13-digit CNIC (e.g., 37405-1234567-1).";
            }
        }
        return $this;
    }

    public function mobile(string $field, string $label): self {
        $val = trim((string)($this->data[$field] ?? ''));
        if ($val !== '') {
            // Standard Pakistani mobile format: 03001234567 or +923001234567
            $clean = preg_replace('/[^0-9+]/', '', $val);
            if (strlen($clean) < 10 || strlen($clean) > 15) {
                $this->errors[$field] = "{$label} must be a valid mobile phone number.";
            }
        }
        return $this;
    }

    public function inList(string $field, array $allowed, string $label): self {
        $val = $this->data[$field] ?? null;
        if ($val !== null && !in_array($val, $allowed, true)) {
            $this->errors[$field] = "{$label} contains an invalid selection.";
        }
        return $this;
    }

    public function date(string $field, string $label): self {
        $val = trim((string)($this->data[$field] ?? ''));
        if ($val !== '') {
            $d = DateTime::createFromFormat('Y-m-d', $val);
            if (!$d || $d->format('Y-m-d') !== $val) {
                $this->errors[$field] = "{$label} must be a valid date format (YYYY-MM-DD).";
            }
        }
        return $this;
    }

    public function passes(): bool {
        return empty($this->errors);
    }

    public function fails(): bool {
        return !empty($this->errors);
    }

    public function getErrors(): array {
        return $this->errors;
    }

    public function getFirstError(): string {
        return reset($this->errors) ?: '';
    }
}
