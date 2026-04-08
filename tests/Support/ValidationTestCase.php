<?php

declare(strict_types=1);

namespace VelvetCMS\Tests\Support;

use VelvetCMS\Exceptions\ValidationException;
use VelvetCMS\Validation\Validator;

abstract class ValidationTestCase extends TestCase
{
    protected function validateData(array $data, array $rules): array
    {
        return Validator::make($data, $rules)->validate();
    }

    protected function assertValidationFails(array $data, array $rules, ?callable $assertion = null): ValidationException
    {
        try {
            $this->validateData($data, $rules);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $exception) {
            $this->addToAssertionCount(1);

            if ($assertion !== null) {
                $assertion($exception);
            }

            return $exception;
        }
    }
}
