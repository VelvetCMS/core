<?php

declare(strict_types=1);

namespace VelvetCMS\Validation;

use Closure;
use VelvetCMS\Exceptions\ValidationException;

class Validator
{
    private array $data;
    private array $rules;
    private array $errors = [];
    
    /** @var array<string, Closure> */
    private static array $extensions = [];

    public function __construct(array $data, array $rules)
    {
        $this->data = $data;
        $this->rules = $rules;
    }

    public static function make(array $data, array $rules): self
    {
        return new self($data, $rules);
    }
    
    /**
     * Register a custom validation rule.
     * 
     * Callback signature: fn(mixed $value, ?string $parameter, array $data, string $field): bool|string
     * Return true if valid, false or error message string if invalid.
     */
    public static function extend(string $rule, Closure $callback): void
    {
        self::$extensions[$rule] = $callback;
    }
    
    public static function hasExtension(string $rule): bool
    {
        return isset(self::$extensions[$rule]);
    }

    public function validate(): array
    {
        foreach ($this->rules as $field => $rule) {
            $value = $this->getValue($field);
            $ruleList = is_string($rule) ? explode('|', $rule) : $rule;

            foreach ($ruleList as $singleRule) {
                if (str_contains($singleRule, ':')) {
                    [$name, $parameter] = explode(':', $singleRule, 2);
                } else {
                    $name = $singleRule;
                    $parameter = null;
                }

                if ($name === 'required') {
                    if ($this->isEmptyValue($value)) {
                        $this->addError($field, "The {$field} field is required.");
                        continue;
                    }
                }
                
                if ($this->isEmptyValue($value)) {
                    continue;
                }

                $this->checkRule($field, $value, $name, $parameter);
            }
        }

        if (!empty($this->errors)) {
            throw new ValidationException($this->errors);
        }

        return array_intersect_key($this->data, array_flip(array_keys($this->rules)));
    }

    private function checkRule(string $field, mixed $value, string $rule, ?string $parameter): void
    {
        // Check custom extensions first
        if (isset(self::$extensions[$rule])) {
            $result = (self::$extensions[$rule])($value, $parameter, $this->data, $field);
            
            if ($result === false) {
                $this->addError($field, "The {$field} field is invalid.");
            } elseif (is_string($result)) {
                $this->addError($field, $result);
            }
            return;
        }
        
        switch ($rule) {
            case 'max':
                $max = (int) $parameter;
                if ($this->valueLength($value) > $max) {
                    $this->addError($field, "The {$field} may not be greater than {$max} characters.");
                }
                break;
                
            case 'min':
                $min = (int) $parameter;
                if ($this->valueLength($value) < $min) {
                    $this->addError($field, "The {$field} must be at least {$min} characters.");
                }
                break;

            case 'email':
                if (is_string($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, "The {$field} must be a valid email address.");
                }
                break;

            case 'url':
                if (is_string($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $this->addError($field, "The {$field} must be a valid URL.");
                }
                break;

            case 'numeric':
                if (!is_numeric($value)) {
                    $this->addError($field, "The {$field} must be a number.");
                }
                break;

            case 'integer':
                if (!filter_var($value, FILTER_VALIDATE_INT) && $value !== 0 && $value !== '0') {
                    $this->addError($field, "The {$field} must be an integer.");
                }
                break;

            case 'boolean':
                if (!in_array($value, [true, false, 0, 1, '0', '1', 'true', 'false'], true)) {
                    $this->addError($field, "The {$field} must be true or false.");
                }
                break;

            case 'alpha':
                if (is_string($value) && !ctype_alpha($value)) {
                    $this->addError($field, "The {$field} may only contain letters.");
                }
                break;

            case 'alphanumeric':
                if (is_string($value) && !ctype_alnum($value)) {
                    $this->addError($field, "The {$field} may only contain letters and numbers.");
                }
                break;

            case 'in':
                $allowed = explode(',', $parameter ?? '');
                if (!in_array($value, $allowed, true)) {
                    $this->addError($field, "The selected {$field} is invalid.");
                }
                break;

            case 'regex':
                if (is_string($value) && !preg_match($parameter, $value)) {
                    $this->addError($field, "The {$field} format is invalid.");
                }
                break;
            
            case 'same':
                $otherValue = $this->getValue($parameter);
                if ($value !== $otherValue) {
                    $this->addError($field, "The {$field} must match {$parameter}.");
                }
                break;

            case 'different':
                $otherValue = $this->getValue($parameter);
                if ($value === $otherValue) {
                    $this->addError($field, "The {$field} must be different from {$parameter}.");
                }
                break;
                
            case 'date':
                if (!is_string($value) || !strtotime($value)) {
                    $this->addError($field, "The {$field} is not a valid date.");
                }
                break;
                
            case 'array':
                if (!is_array($value)) {
                    $this->addError($field, "The {$field} must be an array.");
                }
                break;
        }
    }

    private function getValue(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    private function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }

    private function isEmptyValue(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }
        if (is_string($value)) {
            return trim($value) === '';
        }
        if (is_array($value)) {
            return count($value) === 0;
        }
        return false;
    }

    private function valueLength(mixed $value): int
    {
        if (is_array($value)) {
            return count($value);
        }
        if (is_string($value)) {
            return strlen($value);
        }
        if (is_scalar($value)) {
            return strlen((string) $value);
        }
        return 0;
    }
}
