<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class RutValid implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$this->isValidRut($value)) {
            $fail('The :attribute is not a valid Chilean RUT.');
        }
    }

    private function isValidRut($rut): bool
    {
        $rut = preg_replace('/[^kK0-9]/i', '', $rut);
        if (strlen($rut) < 8) return false;

        $dv = strtolower(substr($rut, -1));
        $number = substr($rut, 0, -1);

        $s = 1;
        $m = 0;
        for (; $number != 0; $number = floor($number / 10)) {
            $s = ($s + $number % 10 * (9 - $m++ % 6)) % 11;
        }

        $expectedDv = $s ? $s - 1 : 'k';
        return (string)$expectedDv === (string)$dv;
    }
}
