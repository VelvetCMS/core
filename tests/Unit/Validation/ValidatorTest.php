<?php

declare(strict_types=1);

namespace VelvetCMS\Tests\Unit\Validation;

use VelvetCMS\Exceptions\ValidationException;
use VelvetCMS\Tests\Support\TestCase;
use VelvetCMS\Validation\Validator;

final class ValidatorTest extends TestCase
{
    // === Required Rule ===
    
    public function test_required_fails_for_null(): void
    {
        $this->expectException(ValidationException::class);
        Validator::make(['field' => null], ['field' => 'required'])->validate();
    }

    public function test_required_fails_for_empty_string(): void
    {
        $this->expectException(ValidationException::class);
        Validator::make(['field' => ''], ['field' => 'required'])->validate();
    }

    public function test_required_fails_for_whitespace_only(): void
    {
        $this->expectException(ValidationException::class);
        Validator::make(['field' => '   '], ['field' => 'required'])->validate();
    }

    public function test_required_fails_for_empty_array(): void
    {
        $this->expectException(ValidationException::class);
        Validator::make(['field' => []], ['field' => 'required'])->validate();
    }

    public function test_required_passes_for_zero(): void
    {
        $validated = Validator::make(['field' => 0], ['field' => 'required'])->validate();
        $this->assertSame(0, $validated['field']);
    }

    public function test_required_passes_for_false(): void
    {
        $validated = Validator::make(['field' => false], ['field' => 'required'])->validate();
        $this->assertFalse($validated['field']);
    }

    // === Email Rule ===
    
    public function test_email_passes_for_valid_email(): void
    {
        $validated = Validator::make(
            ['email' => 'test@example.com'],
            ['email' => 'email']
        )->validate();

        $this->assertSame('test@example.com', $validated['email']);
    }

    public function test_email_fails_for_invalid_email(): void
    {
        $this->expectException(ValidationException::class);
        Validator::make(['email' => 'not-an-email'], ['email' => 'email'])->validate();
    }

    // === URL Rule ===
    
    public function test_url_passes_for_valid_url(): void
    {
        $validated = Validator::make(
            ['website' => 'https://example.com/path?query=1'],
            ['website' => 'url']
        )->validate();

        $this->assertSame('https://example.com/path?query=1', $validated['website']);
    }

    public function test_url_fails_for_invalid_url(): void
    {
        $this->expectException(ValidationException::class);
        Validator::make(['website' => 'not a url'], ['website' => 'url'])->validate();
    }

    // === Numeric / Integer Rules ===
    
    public function test_numeric_passes_for_integer(): void
    {
        $validated = Validator::make(['num' => 42], ['num' => 'numeric'])->validate();
        $this->assertSame(42, $validated['num']);
    }

    public function test_numeric_passes_for_float(): void
    {
        $validated = Validator::make(['num' => 3.14], ['num' => 'numeric'])->validate();
        $this->assertSame(3.14, $validated['num']);
    }

    public function test_numeric_passes_for_numeric_string(): void
    {
        $validated = Validator::make(['num' => '123'], ['num' => 'numeric'])->validate();
        $this->assertSame('123', $validated['num']);
    }

    public function test_numeric_fails_for_non_numeric(): void
    {
        $this->expectException(ValidationException::class);
        Validator::make(['num' => 'abc'], ['num' => 'numeric'])->validate();
    }

    public function test_integer_passes_for_integer(): void
    {
        $validated = Validator::make(['num' => 42], ['num' => 'integer'])->validate();
        $this->assertSame(42, $validated['num']);
    }

    public function test_integer_passes_for_zero(): void
    {
        $validated = Validator::make(['num' => 0], ['num' => 'integer'])->validate();
        $this->assertSame(0, $validated['num']);
    }

    public function test_integer_passes_for_string_integer(): void
    {
        $validated = Validator::make(['num' => '42'], ['num' => 'integer'])->validate();
        $this->assertSame('42', $validated['num']);
    }

    public function test_integer_fails_for_float(): void
    {
        $this->expectException(ValidationException::class);
        Validator::make(['num' => 3.14], ['num' => 'integer'])->validate();
    }

    // === Boolean Rule ===
    
    public function test_boolean_passes_for_true(): void
    {
        $validated = Validator::make(['flag' => true], ['flag' => 'boolean'])->validate();
        $this->assertTrue($validated['flag']);
    }

    public function test_boolean_passes_for_false(): void
    {
        $validated = Validator::make(['flag' => false], ['flag' => 'boolean'])->validate();
        $this->assertFalse($validated['flag']);
    }

    public function test_boolean_passes_for_string_true(): void
    {
        $validated = Validator::make(['flag' => 'true'], ['flag' => 'boolean'])->validate();
        $this->assertSame('true', $validated['flag']);
    }

    public function test_boolean_passes_for_numeric_one_zero(): void
    {
        Validator::make(['flag' => 1], ['flag' => 'boolean'])->validate();
        Validator::make(['flag' => 0], ['flag' => 'boolean'])->validate();
        Validator::make(['flag' => '1'], ['flag' => 'boolean'])->validate();
        Validator::make(['flag' => '0'], ['flag' => 'boolean'])->validate();
        $this->assertTrue(true);
    }

    public function test_boolean_fails_for_invalid(): void
    {
        $this->expectException(ValidationException::class);
        Validator::make(['flag' => 'yes'], ['flag' => 'boolean'])->validate();
    }

    // === Min / Max Rules ===
    
    public function test_min_passes_for_sufficient_length(): void
    {
        $validated = Validator::make(['name' => 'John'], ['name' => 'min:3'])->validate();
        $this->assertSame('John', $validated['name']);
    }

    public function test_min_fails_for_insufficient_length(): void
    {
        $this->expectException(ValidationException::class);
        Validator::make(['name' => 'Jo'], ['name' => 'min:3'])->validate();
    }

    public function test_max_passes_for_within_limit(): void
    {
        $validated = Validator::make(['name' => 'John'], ['name' => 'max:10'])->validate();
        $this->assertSame('John', $validated['name']);
    }

    public function test_max_fails_for_exceeding_limit(): void
    {
        $this->expectException(ValidationException::class);
        Validator::make(['name' => 'John Doe Smith'], ['name' => 'max:5'])->validate();
    }

    public function test_min_max_work_on_arrays(): void
    {
        $validated = Validator::make(
            ['items' => [1, 2, 3]],
            ['items' => 'array|min:2|max:5']
        )->validate();

        $this->assertCount(3, $validated['items']);
    }

    // === Alpha / Alphanumeric Rules ===
    
    public function test_alpha_passes_for_letters_only(): void
    {
        $validated = Validator::make(['name' => 'John'], ['name' => 'alpha'])->validate();
        $this->assertSame('John', $validated['name']);
    }

    public function test_alpha_fails_for_numbers(): void
    {
        $this->expectException(ValidationException::class);
        Validator::make(['name' => 'John123'], ['name' => 'alpha'])->validate();
    }

    public function test_alphanumeric_passes_for_letters_and_numbers(): void
    {
        $validated = Validator::make(['code' => 'ABC123'], ['code' => 'alphanumeric'])->validate();
        $this->assertSame('ABC123', $validated['code']);
    }

    public function test_alphanumeric_fails_for_special_chars(): void
    {
        $this->expectException(ValidationException::class);
        Validator::make(['code' => 'ABC-123'], ['code' => 'alphanumeric'])->validate();
    }

    // === In Rule ===
    
    public function test_in_passes_for_allowed_value(): void
    {
        $validated = Validator::make(
            ['status' => 'active'],
            ['status' => 'in:active,pending,closed']
        )->validate();

        $this->assertSame('active', $validated['status']);
    }

    public function test_in_fails_for_disallowed_value(): void
    {
        $this->expectException(ValidationException::class);
        Validator::make(
            ['status' => 'unknown'],
            ['status' => 'in:active,pending,closed']
        )->validate();
    }

    // === Regex Rule ===
    
    public function test_regex_passes_for_matching_pattern(): void
    {
        $validated = Validator::make(
            ['phone' => '123-456-7890'],
            ['phone' => 'regex:/^\d{3}-\d{3}-\d{4}$/']
        )->validate();

        $this->assertSame('123-456-7890', $validated['phone']);
    }

    public function test_regex_fails_for_non_matching_pattern(): void
    {
        $this->expectException(ValidationException::class);
        Validator::make(
            ['phone' => '1234567890'],
            ['phone' => 'regex:/^\d{3}-\d{3}-\d{4}$/']
        )->validate();
    }

    // === Same / Different Rules ===
    
    public function test_same_passes_when_fields_match(): void
    {
        $validated = Validator::make(
            ['password' => 'secret', 'password_confirmation' => 'secret'],
            ['password_confirmation' => 'same:password']
        )->validate();

        $this->assertSame('secret', $validated['password_confirmation']);
    }

    public function test_same_fails_when_fields_differ(): void
    {
        $this->expectException(ValidationException::class);
        Validator::make(
            ['password' => 'secret', 'password_confirmation' => 'different'],
            ['password_confirmation' => 'same:password']
        )->validate();
    }

    public function test_different_passes_when_fields_differ(): void
    {
        $validated = Validator::make(
            ['old_password' => 'old', 'new_password' => 'new'],
            ['new_password' => 'different:old_password']
        )->validate();

        $this->assertSame('new', $validated['new_password']);
    }

    public function test_different_fails_when_fields_match(): void
    {
        $this->expectException(ValidationException::class);
        Validator::make(
            ['old_password' => 'same', 'new_password' => 'same'],
            ['new_password' => 'different:old_password']
        )->validate();
    }

    // === Date Rule ===
    
    public function test_date_passes_for_valid_date(): void
    {
        $validated = Validator::make(['date' => '2025-01-15'], ['date' => 'date'])->validate();
        $this->assertSame('2025-01-15', $validated['date']);
    }

    public function test_date_passes_for_various_formats(): void
    {
        Validator::make(['date' => '15-01-2025'], ['date' => 'date'])->validate();
        Validator::make(['date' => 'January 15, 2025'], ['date' => 'date'])->validate();
        Validator::make(['date' => '2025-01-15 10:30:00'], ['date' => 'date'])->validate();
        $this->assertTrue(true);
    }

    public function test_date_fails_for_invalid_date(): void
    {
        $this->expectException(ValidationException::class);
        Validator::make(['date' => 'not-a-date'], ['date' => 'date'])->validate();
    }

    // === Array Rule ===
    
    public function test_array_passes_for_array(): void
    {
        $validated = Validator::make(['items' => [1, 2, 3]], ['items' => 'array'])->validate();
        $this->assertSame([1, 2, 3], $validated['items']);
    }

    public function test_array_fails_for_non_array(): void
    {
        $this->expectException(ValidationException::class);
        Validator::make(['items' => 'not an array'], ['items' => 'array'])->validate();
    }

    // === Optional Fields ===
    
    public function test_optional_field_skipped_when_missing(): void
    {
        $validated = Validator::make(
            ['name' => 'John'],
            ['name' => 'required', 'email' => 'email']
        )->validate();

        $this->assertArrayNotHasKey('email', $validated);
    }

    public function test_optional_field_validated_when_present(): void
    {
        $this->expectException(ValidationException::class);
        Validator::make(
            ['name' => 'John', 'email' => 'invalid'],
            ['name' => 'required', 'email' => 'email']
        )->validate();
    }

    // === Combined Rules ===
    
    public function test_multiple_rules_all_pass(): void
    {
        $validated = Validator::make(
            ['username' => 'john123'],
            ['username' => 'required|min:3|max:20|alphanumeric']
        )->validate();

        $this->assertSame('john123', $validated['username']);
    }

    public function test_multiple_rules_first_failure_reports(): void
    {
        try {
            Validator::make(
                ['username' => 'a'],
                ['username' => 'required|min:3|max:20']
            )->validate();
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertArrayHasKey('username', $errors);
            $this->assertStringContainsString('at least 3', $errors['username'][0]);
        }
    }

    public function test_array_rule_syntax(): void
    {
        $validated = Validator::make(
            ['email' => 'test@example.com'],
            ['email' => ['required', 'email']]
        )->validate();

        $this->assertSame('test@example.com', $validated['email']);
    }

    // === Error Messages ===
    
    public function test_validation_exception_contains_all_errors(): void
    {
        try {
            Validator::make(
                ['email' => '', 'age' => 'not-a-number'],
                ['email' => 'required|email', 'age' => 'required|integer']
            )->validate();
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertArrayHasKey('email', $errors);
            $this->assertArrayHasKey('age', $errors);
        }
    }
}
