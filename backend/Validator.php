<?php
/**
 * DevCore Shared Library — Validator.php
 * Input validation used across all projects
 */
class Validator {
    private array $errors = [];
    private array $data   = [];

    public function __construct(array $data) {
        $this->data = $data;
    }

    public static function make(array $data, array $rules): self {
        $v = new self($data);
        foreach ($rules as $field => $ruleStr) {
            foreach (explode('|', $ruleStr) as $rule) {
                $v->applyRule($field, $rule);
            }
        }
        return $v;
    }

    private function applyRule(string $field, string $rule): void {
        $value = $this->data[$field] ?? null;
        [$ruleName, $param] = array_pad(explode(':', $rule, 2), 2, null);

        match ($ruleName) {
            'required' => (!isset($value) || $value === '')
                ? $this->errors[$field][] = "$field is required" : null,
            'email'    => ($value && !filter_var($value, FILTER_VALIDATE_EMAIL))
                ? $this->errors[$field][] = "$field must be a valid email" : null,
            'min'      => ($value !== null && strlen((string)$value) < (int)$param)
                ? $this->errors[$field][] = "$field must be at least $param characters" : null,
            'max'      => ($value !== null && strlen((string)$value) > (int)$param)
                ? $this->errors[$field][] = "$field must not exceed $param characters" : null,
            'numeric'  => ($value !== null && !is_numeric($value))
                ? $this->errors[$field][] = "$field must be a number" : null,
            'in'       => ($value !== null && !in_array($value, explode(',', $param)))
                ? $this->errors[$field][] = "$field must be one of: $param" : null,
            default    => null,
        };
    }

    public function fails(): bool  { return !empty($this->errors); }
    public function passes(): bool { return empty($this->errors); }
    public function errors(): array { return $this->errors; }

    public function validated(): array {
        return array_intersect_key($this->data, array_flip(array_keys($this->errors) ? [] : array_keys($this->data)));
    }
}
