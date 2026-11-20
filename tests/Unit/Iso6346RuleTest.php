<?php

namespace Tests\Unit;

use App\Rules\Iso6346Rule;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class Iso6346RuleTest extends TestCase
{
    /**
     * Verify ISO 6346 check digit calculation logic.
     */
    public function test_calculate_check_digit_for_known_containers(): void
    {
        // CSQU305438 -> check digit 3
        $this->assertEquals(3, Iso6346Rule::calculateCheckDigit('CSQU305438'));

        // MSKU901234 -> check digit 8 (sum 3704 % 11 = 8)
        $this->assertEquals(8, Iso6346Rule::calculateCheckDigit('MSKU901234'));

        // MAEU123456 -> check digit 7 (sum 5496 % 11 = 7)
        $this->assertEquals(7, Iso6346Rule::calculateCheckDigit('MAEU123456'));
    }

    /**
     * Test valid container numbers pass validation.
     */
    public function test_valid_container_numbers_pass_validation(): void
    {
        $validContainers = [
            'CSQU3054383',
            'MSKU9012348',
            'MAEU1234567',
            'csqu3054383', // lowercase normalized
            'CSQU 305438-3', // spaces / dashes normalized
        ];

        foreach ($validContainers as $code) {
            $validator = Validator::make(
                ['container_number' => $code],
                ['container_number' => [new Iso6346Rule()]]
            );

            $this->assertTrue(
                $validator->passes(),
                "Expected {$code} to pass validation, but failed: " . json_encode($validator->errors()->all())
            );
        }
    }

    /**
     * Test container numbers with wrong check digit fail validation.
     */
    public function test_invalid_check_digits_fail_validation(): void
    {
        $invalidContainers = [
            'CSQU3054384', // check digit should be 3, not 4
            'MSKU9012345', // check digit should be 8, not 5
            'MAEU1234569', // check digit should be 7, not 9
        ];

        foreach ($invalidContainers as $code) {
            $validator = Validator::make(
                ['container_number' => $code],
                ['container_number' => [new Iso6346Rule()]]
            );

            $this->assertFalse($validator->passes(), "Expected {$code} to fail check digit validation.");
            $this->assertStringContainsString('invalid ISO 6346 check digit', $validator->errors()->first('container_number'));
        }
    }

    /**
     * Test malformed container numbers fail format validation.
     */
    public function test_malformed_container_numbers_fail_validation(): void
    {
        $malformed = [
            'CSQU123',        // too short
            '12345678901',    // no letters
            'CSQU3054383999', // too long
            'CSQ@1234567',    // special character
        ];

        foreach ($malformed as $code) {
            $validator = Validator::make(
                ['container_number' => $code],
                ['container_number' => [new Iso6346Rule()]]
            );

            $this->assertFalse($validator->passes(), "Expected {$code} to fail format validation.");
            $this->assertStringContainsString('ISO 6346 format', $validator->errors()->first('container_number'));
        }
    }
}
