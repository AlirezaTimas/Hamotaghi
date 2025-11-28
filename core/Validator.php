<?php
/**
 * Input Validation and Sanitization
 */

declare(strict_types=1);

class Validator
{
    private array $errors = [];
    private array $data = [];

    /**
     * Validate input data
     */
    public function validate(array $data, array $rules): bool
    {
        $this->errors = [];
        $this->data = $data;

        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;
            $ruleArray = is_string($fieldRules) ? explode('|', $fieldRules) : $fieldRules;

            foreach ($ruleArray as $rule) {
                $this->applyRule($field, $value, $rule);
            }
        }

        return empty($this->errors);
    }

    /**
     * Apply validation rule
     */
    private function applyRule(string $field, $value, string $rule): void
    {
        $parts = explode(':', $rule);
        $ruleName = $parts[0];
        $ruleValue = $parts[1] ?? null;

        switch ($ruleName) {
            case 'required':
                if (empty($value) && $value !== '0') {
                    $this->errors[$field][] = "فیلد {$field} الزامی است";
                }
                break;

            case 'email':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->errors[$field][] = "ایمیل معتبر نیست";
                }
                break;

            case 'min':
                if (!empty($value) && strlen($value) < (int)$ruleValue) {
                    $this->errors[$field][] = "حداقل {$ruleValue} کاراکتر لازم است";
                }
                break;

            case 'max':
                if (!empty($value) && strlen($value) > (int)$ruleValue) {
                    $this->errors[$field][] = "حداکثر {$ruleValue} کاراکتر مجاز است";
                }
                break;

            case 'numeric':
                if (!empty($value) && !is_numeric($value)) {
                    $this->errors[$field][] = "باید عدد باشد";
                }
                break;

            case 'in':
                $allowed = explode(',', $ruleValue);
                if (!empty($value) && !in_array($value, $allowed)) {
                    $this->errors[$field][] = "مقدار نامعتبر";
                }
                break;

            case 'date':
                if (!empty($value)) {
                    $date = strtotime($value);
                    if ($date === false) {
                        $this->errors[$field][] = "تاریخ معتبر نیست";
                    }
                }
                break;

            case 'date_future':
                if (!empty($value)) {
                    $date = strtotime($value);
                    if ($date === false || $date < strtotime('today')) {
                        $this->errors[$field][] = "تاریخ باید در آینده باشد";
                    }
                }
                break;
        }
    }

    /**
     * Get validation errors
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Get first error for field
     */
    public function firstError(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }

    /**
     * Sanitize string input
     */
    public static function sanitize(string $input): string
    {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Sanitize array of inputs
     */
    public static function sanitizeArray(array $data): array
    {
        return array_map([self::class, 'sanitize'], $data);
    }

    /**
     * Validate email
     */
    public static function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate phone (Iranian format)
     */
    public static function isValidPhone(string $phone): bool
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        return preg_match('/^09\d{9}$/', $phone) || preg_match('/^0\d{10}$/', $phone);
    }
}

