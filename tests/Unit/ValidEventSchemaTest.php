<?php

namespace Tests\Unit;

use App\Rules\ValidEventSchema;
use Tests\TestCase;

class ValidEventSchemaTest extends TestCase
{
    private function fails(mixed $schema): bool
    {
        $failed = false;
        (new ValidEventSchema())->validate('schema', $schema, function () use (&$failed) {
            $failed = true;
        });

        return $failed;
    }

    public function test_schema_with_regex_rule_as_pipe_string_fails(): void
    {
        $this->assertTrue($this->fails(['field' => 'required|regex:/^(a+)+$/']));
    }

    public function test_schema_with_regex_rule_in_array_form_fails(): void
    {
        $this->assertTrue($this->fails(['field' => ['required', 'regex:/^(a+)+$/']]));
    }

    public function test_schema_with_not_regex_rule_fails(): void
    {
        $this->assertTrue($this->fails(['field' => ['not_regex:/^(a+)+$/']]));
    }

    public function test_schema_with_safe_rules_passes(): void
    {
        $this->assertFalse($this->fails([
            'field' => 'required|string|max:255',
            'other' => ['nullable', 'integer', 'min:0'],
        ]));
    }

    public function test_non_array_schema_passes(): void
    {
        $this->assertFalse($this->fails(null));
    }

    public function test_schema_with_unresolvable_rule_name_fails(): void
    {
        $this->assertTrue($this->fails(['field' => 'required|thisruledoesnotexist']));
    }

    public function test_schema_with_unresolvable_rule_in_array_form_fails(): void
    {
        $this->assertTrue($this->fails(['field' => ['required', 'anotherbogusrule']]));
    }
}
