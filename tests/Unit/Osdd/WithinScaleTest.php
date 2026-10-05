<?php

namespace Tests\Unit\Osdd;

use Illuminate\Support\Facades\Validator;
use Illuminate\Translation\PotentiallyTranslatedString;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Technical\Osdd\Rules\WithinScale;
use Tests\TestCase;

class WithinScaleTest extends TestCase
{
    private function passes(mixed $input, int $scale): bool
    {
        return Validator::make(['amount' => $input], ['amount' => [new WithinScale($scale)]])->passes();
    }

    public static function acceptedInputs(): array
    {
        return [
            'string at the scale' => ['0.00000001', 8],
            'string below the scale' => ['1.5', 8],
            'string without decimals' => ['100', 2],
            'string with a leading dot' => ['.5', 2],
            'string with a trailing dot' => ['5.', 2],
            'string with a sign' => ['+1.25', 2],
            'string with trailing zeros past the scale' => ['1.500000000', 8],
            'exponent string within the scale' => ['5.0E-5', 8],
            'exponent string at the scale' => ['1E-8', 8],
            'exponent string with a positive exponent' => ['1E+3', 2],
            'exponent string with trailing zeros in the mantissa' => ['100E-2', 0],
            'exponent string with a zero mantissa' => ['0e-999', 8],
            'exponent string with an enormous positive exponent' => ['1e999999999999999999999', 8],
            'string surrounded by whitespace' => [' 1.5 ', 8],
            'float with sixteen significant digits' => [41148567.69631931, 8],
            'whole float' => [120.0, 0],
            'float at the scale' => [0.00000001, 8],
            'float below the scale' => [0.00005, 8],
            'float with cents' => [80.55, 2],
            'float at the maximum with cents' => [10000000.01, 2],
            'integer' => [1000000000, 2],
            'zero integer' => [0, 8],
            'integer at a zero scale' => [7, 0],
            'string at a zero scale' => ['7', 0],
        ];
    }

    public static function rejectedInputs(): array
    {
        return [
            'string past the scale' => ['0.000000001', 8],
            'string one digit past the cents' => ['0.004', 2],
            'string rounding up past the cents' => ['0.005', 2],
            'exponent string past the scale' => ['1.0E-9', 8],
            'exponent string past a shorter scale' => ['5.0E-5', 4],
            'exponent string far below the scale' => ['1e-400', 8],
            'exponent string below the float range' => ['1e-324', 8],
            'exponent string rounding to zero as a float' => ['4e-324', 8],
            'exponent string with an enormous negative exponent' => ['1e-999999999999999999999', 8],
            'exponent string surrounded by whitespace' => [' 1e-400 ', 8],
            'exponent string preceded by a form feed' => ["\f1e-400", 8],
            'exponent string with a mantissa fraction' => ['1.5e-9', 8],
            'float past the scale' => [0.000000001, 8],
            'float one digit past the cents' => [0.004, 2],
            'float rounding up past the cents' => [0.005, 2],
            'float whose shortest representation is long' => [0.1 + 0.2, 8],
            'infinite float' => [INF, 8],
            'float past a zero scale' => [1.5, 0],
            'string past a zero scale' => ['1.5', 0],
        ];
    }

    public static function inputsLeftToTheNumericRule(): array
    {
        return [
            'letters' => ['abc'],
            'decimal comma' => ['12,5'],
            'null' => [null],
            'empty string' => [''],
            'boolean' => [true],
            'array' => [[1.5]],
        ];
    }

    #[Test]
    #[DataProvider('acceptedInputs')]
    public function it_accepts_a_number_within_the_scale(float|int|string $input, int $scale): void
    {
        $this->assertTrue($this->passes($input, $scale));
    }

    #[Test]
    #[DataProvider('rejectedInputs')]
    public function it_rejects_a_number_past_the_scale(float|int|string $input, int $scale): void
    {
        $this->assertFalse($this->passes($input, $scale));
    }

    #[Test]
    #[DataProvider('inputsLeftToTheNumericRule')]
    public function it_leaves_a_non_numeric_input_to_the_numeric_rule(mixed $input): void
    {
        $reported = [];
        $fail = function (string $message) use (&$reported): PotentiallyTranslatedString {
            $reported[] = $message;

            return new PotentiallyTranslatedString($message, $this->app->make('translator'));
        };

        (new WithinScale(8))->validate('amount', $input, $fail);

        $this->assertSame([], $reported);
    }

    #[Test]
    public function it_adds_no_second_error_to_a_non_numeric_input(): void
    {
        $validator = Validator::make(['amount' => 'abc'], ['amount' => ['numeric', new WithinScale(8)]]);

        $this->assertCount(1, $validator->errors()->get('amount'));
    }

    #[Test]
    public function it_names_the_scale_in_the_english_message(): void
    {
        $this->app->setLocale('en');

        $validator = Validator::make(['amount' => '0.004'], ['amount' => [new WithinScale(2)]]);

        $this->assertSame('The amount field must have at most 2 decimal places.', $validator->errors()->first('amount'));
    }

    #[Test]
    public function it_names_the_scale_in_the_french_message(): void
    {
        $this->app->setLocale('fr');

        $validator = Validator::make(['amount' => '0.004'], ['amount' => [new WithinScale(2)]]);

        $this->assertSame('Le champ amount ne peut pas avoir plus de 2 décimales.', $validator->errors()->first('amount'));
    }
}
