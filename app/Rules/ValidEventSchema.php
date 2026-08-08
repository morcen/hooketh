<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Rejects event schemas that can't safely be used as Laravel validation
 * rules against a future trigger payload.
 *
 * Two things are checked:
 * - Rules whose cost is driven by an attacker-controlled pattern (e.g.
 *   `regex:`/`not_regex:`). Event schemas run synchronously against the
 *   trigger payload on the request thread (see WebhookController::trigger),
 *   so a schema author could craft a catastrophically backtracking pattern
 *   to tie up a PHP-FPM worker and degrade the shared pool for every tenant.
 * - Rules that are otherwise malformed or unresolvable (a typo'd rule name,
 *   a reference to a non-existent custom rule class, etc.). Laravel only
 *   discovers this when the rule set is actually run, which previously
 *   happened for the first time at trigger time, throwing an uncaught
 *   exception on every subsequent trigger call for that event. Running a
 *   dry-run validation here surfaces the same failure as a clean 422 at
 *   creation/update time instead.
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

        try {
            // Laravel skips a non-implicit rule entirely when its field is
            // absent from the data being validated, so validating against
            // an empty payload would never actually resolve/invoke most
            // rules. Fill in a dummy value for every schema field so each
            // rule genuinely runs; we only care whether that run throws
            // (an unresolvable rule name/class), not whether it passes.
            Validator::make($this->dryRunPayload($value), $value)->fails();
        } catch (Throwable $e) {
            $fail("The :attribute contains an invalid or unusable validation rule: {$e->getMessage()}");
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

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function dryRunPayload(array $schema): array
    {
        $payload = [];

        foreach (array_keys($schema) as $field) {
            // Wildcards (e.g. "items.*.name") need a concrete index to
            // resolve against; 0 is as good as any for a dry run.
            Arr::set($payload, str_replace('*', '0', $field), 'dry-run-value');
        }

        return $payload;
    }
}
