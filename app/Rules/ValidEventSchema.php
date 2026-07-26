<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects event schemas containing validation rules whose cost is driven
 * by an attacker-controlled pattern (e.g. `regex:`/`not_regex:`). Event
 * schemas run synchronously against the trigger payload on the request
 * thread (see WebhookController::trigger), so a schema author could craft
 * a catastrophically backtracking pattern to tie up a PHP-FPM worker and
 * degrade the shared pool for every tenant.
 */
class ValidEventSchema implements ValidationRule
{
    /**
     * Rule names that accept an arbitrary regular expression, making them
     * a ReDoS vector when run against payload data at request time.
     *
     * @var array<int, string>
     */
    private const BLOCKED_RULES = ['regex', 'not_regex'];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            return;
        }

        foreach ($value as $field => $rules) {
            foreach ($this->normalizeRules($rules) as $rule) {
                $ruleName = strtolower(is_string($rule) ? explode(':', $rule, 2)[0] : '');

                if (in_array($ruleName, self::BLOCKED_RULES, true)) {
                    $fail("The :attribute must not use the \"{$ruleName}\" rule (field \"{$field}\").");

                    return;
                }
            }
        }
    }

    /**
     * @return array<int, mixed>
     */
    private function normalizeRules(mixed $rules): array
    {
        if (is_string($rules)) {
            return explode('|', $rules);
        }

        return is_array($rules) ? $rules : [$rules];
    }
}
