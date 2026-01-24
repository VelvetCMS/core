<?php

declare(strict_types=1);

namespace VelvetCMS\Tests\Unit\Validation;

use VelvetCMS\Exceptions\ValidationException;
use VelvetCMS\Tests\Support\TestCase;
use VelvetCMS\Validation\Validator;

final class ValidatorTest extends TestCase
{
    public function test_required_rule_throws_validation_exception(): void
    {
        $this->expectException(ValidationException::class);

        Validator::make([], ['name' => 'required'])->validate();
    }

    public function test_returns_only_validated_fields_on_success(): void
    {
        $data = ['name' => 'Ada', 'email' => 'ada@example.com', 'extra' => 'ignore'];
        $validated = Validator::make($data, [
            'name' => 'required|min:2',
            'email' => 'required|email',
        ])->validate();

        $this->assertSame(['name' => 'Ada', 'email' => 'ada@example.com'], $validated);
    }

    public function test_string_rules_min_max_alpha_and_regex(): void
    {
        $data = ['code' => 'AB12'];
        $validated = Validator::make($data, [
            'code' => ['min:2', 'max:6', 'alphanumeric', 'regex:/^[A-Z0-9]+$/'],
        ])->validate();

        $this->assertSame(['code' => 'AB12'], $validated);
    }

    public function test_numeric_integer_boolean_date_and_array_rules(): void
    {
        $data = [
            'count' => 10,
            'age' => '20',
            'flag' => 'true',
            'when' => '2025-01-10',
            'tags' => ['a', 'b'],
        ];

        $validated = Validator::make($data, [
            'count' => 'numeric',
            'age' => 'integer',
            'flag' => 'boolean',
            'when' => 'date',
            'tags' => 'array',
        ])->validate();

        $this->assertSame($data, $validated);
    }

    public function test_same_and_different_rules(): void
    {
        $data = ['password' => 'secret', 'confirm' => 'secret', 'username' => 'alice'];

        $validated = Validator::make($data, [
            'confirm' => 'same:password',
            'username' => 'different:password',
        ])->validate();

        $this->assertSame(['confirm' => 'secret', 'username' => 'alice'], $validated);
    }

    public function test_in_rule_accepts_allowed_values(): void
    {
        $validated = Validator::make(['role' => 'editor'], [
            'role' => 'in:admin,editor,viewer',
        ])->validate();

        $this->assertSame(['role' => 'editor'], $validated);
    }
}
