<?php

declare(strict_types=1);

namespace Tests\Admin\Schema;

use Echo\Framework\Admin\Schema\FieldDefinition;
use Echo\Framework\Admin\Schema\FormSchema;
use PHPUnit\Framework\TestCase;

class FormSchemaTest extends TestCase
{
    private function makeField(array $overrides = []): FieldDefinition
    {
        return new FieldDefinition(
            name: $overrides['name'] ?? 'field',
            label: $overrides['label'] ?? 'Field',
            expression: $overrides['expression'] ?? null,
            control: $overrides['control'] ?? 'input',
            rules: $overrides['rules'] ?? [],
            options: $overrides['options'] ?? [],
            optionsQuery: $overrides['optionsQuery'] ?? null,
            datalist: $overrides['datalist'] ?? [],
            accept: $overrides['accept'] ?? null,
            default: $overrides['default'] ?? null,
            readonly: $overrides['readonly'] ?? false,
            disabled: $overrides['disabled'] ?? false,
            requiredOnCreate: $overrides['requiredOnCreate'] ?? false,
            controlRenderer: $overrides['controlRenderer'] ?? null,
        );
    }

    private function makeSchema(array $fields = []): FormSchema
    {
        return new FormSchema(fields: $fields);
    }

    // --- Empty schema ---

    public function testEmptySchemaHasNoFields(): void
    {
        $schema = $this->makeSchema();
        $this->assertSame([], $schema->fields);
    }

    public function testEmptySchemaReturnsEmptyRules(): void
    {
        $schema = $this->makeSchema();
        $this->assertSame([], $schema->getValidationRules());
    }

    public function testEmptySchemaReturnsEmptyLabels(): void
    {
        $schema = $this->makeSchema();
        $this->assertSame([], $schema->getLabels());
    }

    public function testEmptySchemaReturnsEmptyDefaults(): void
    {
        $schema = $this->makeSchema();
        $this->assertSame([], $schema->getDefaults());
    }

    public function testEmptySchemaReturnsEmptySelectExpressions(): void
    {
        $schema = $this->makeSchema();
        $this->assertSame([], $schema->getSelectExpressions());
    }

    // --- getField ---

    public function testGetFieldReturnsFieldByName(): void
    {
        $email = $this->makeField(['name' => 'email', 'label' => 'Email']);
        $name = $this->makeField(['name' => 'first_name', 'label' => 'First Name']);
        $schema = $this->makeSchema([$email, $name]);

        $result = $schema->getField('email');
        $this->assertSame($email, $result);
    }

    public function testGetFieldReturnsNullForMissingField(): void
    {
        $schema = $this->makeSchema([
            $this->makeField(['name' => 'email']),
        ]);

        $this->assertNull($schema->getField('nonexistent'));
    }

    public function testGetFieldReturnsFirstMatchOnDuplicate(): void
    {
        $first = $this->makeField(['name' => 'email', 'label' => 'First']);
        $second = $this->makeField(['name' => 'email', 'label' => 'Second']);
        $schema = $this->makeSchema([$first, $second]);

        $this->assertSame($first, $schema->getField('email'));
    }

    // --- getValidationRules ---

    public function testGetValidationRulesKeyedByFieldName(): void
    {
        $schema = $this->makeSchema([
            $this->makeField(['name' => 'email', 'rules' => ['required', 'email']]),
            $this->makeField(['name' => 'name', 'rules' => ['required']]),
            $this->makeField(['name' => 'avatar', 'rules' => []]),
        ]);

        $expected = [
            'email' => ['required', 'email'],
            'name' => ['required'],
            'avatar' => [],
        ];
        $this->assertSame($expected, $schema->getValidationRules());
    }

    public function testGetValidationRulesStripsRequiredOnEditForRequiredOnCreateFields(): void
    {
        $schema = $this->makeSchema([
            $this->makeField(['name' => 'email', 'rules' => ['required', 'email']]),
            $this->makeField([
                'name' => 'password',
                'rules' => ['required', 'min_length:4'],
                'requiredOnCreate' => true,
            ]),
        ]);

        $editRules = $schema->getValidationRules('edit');
        $this->assertSame(['required', 'email'], $editRules['email']);
        $this->assertSame(['min_length:4'], $editRules['password']);
    }

    public function testGetValidationRulesKeepsRequiredOnCreateForCreateFormType(): void
    {
        $schema = $this->makeSchema([
            $this->makeField([
                'name' => 'password',
                'rules' => ['required', 'min_length:4'],
                'requiredOnCreate' => true,
            ]),
        ]);

        $createRules = $schema->getValidationRules('create');
        $this->assertSame(['required', 'min_length:4'], $createRules['password']);
    }

    public function testGetValidationRulesDefaultsToCreate(): void
    {
        $schema = $this->makeSchema([
            $this->makeField([
                'name' => 'password',
                'rules' => ['required', 'min_length:4'],
                'requiredOnCreate' => true,
            ]),
        ]);

        $rules = $schema->getValidationRules();
        $this->assertSame(['required', 'min_length:4'], $rules['password']);
    }

    // --- getSelectExpressions ---

    public function testGetSelectExpressionsUsesFieldNames(): void
    {
        $schema = $this->makeSchema([
            $this->makeField(['name' => 'email']),
            $this->makeField(['name' => 'first_name']),
        ]);

        $this->assertSame(['email', 'first_name'], $schema->getSelectExpressions());
    }

    public function testGetSelectExpressionsUsesExpressionWhenSet(): void
    {
        $schema = $this->makeSchema([
            $this->makeField(['name' => 'email']),
            $this->makeField(['name' => 'password', 'expression' => "'' as password"]),
        ]);

        $this->assertSame(['email', "'' as password"], $schema->getSelectExpressions());
    }

    // --- getLabels ---

    public function testGetLabelsReturnsIndexedArray(): void
    {
        $schema = $this->makeSchema([
            $this->makeField(['name' => 'email', 'label' => 'Email Address']),
            $this->makeField(['name' => 'role', 'label' => 'Role']),
        ]);

        $labels = $schema->getLabels();
        $this->assertSame(['Email Address', 'Role'], $labels);
        // Verify indexed (not associative)
        $this->assertSame(0, array_key_first($labels));
    }

    // --- getDefaults ---

    public function testGetDefaultsKeyedByFieldName(): void
    {
        $schema = $this->makeSchema([
            $this->makeField(['name' => 'role', 'default' => 'standard']),
            $this->makeField(['name' => 'email', 'default' => null]),
            $this->makeField(['name' => 'enabled', 'default' => 1]),
        ]);

        $expected = [
            'role' => 'standard',
            'email' => null,
            'enabled' => 1,
        ];
        $this->assertSame($expected, $schema->getDefaults());
    }

    // --- Field ordering preserved ---

    public function testFieldOrderIsPreserved(): void
    {
        $fields = [
            $this->makeField(['name' => 'c']),
            $this->makeField(['name' => 'a']),
            $this->makeField(['name' => 'b']),
        ];
        $schema = $this->makeSchema($fields);

        $names = array_map(fn(FieldDefinition $f) => $f->name, $schema->fields);
        $this->assertSame(['c', 'a', 'b'], $names);
    }

    // --- Immutability ---

    public function testFormSchemaIsFinal(): void
    {
        $reflection = new \ReflectionClass(FormSchema::class);
        $this->assertTrue($reflection->isFinal());
    }
}
