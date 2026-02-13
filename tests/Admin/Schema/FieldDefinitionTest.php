<?php

declare(strict_types=1);

namespace Tests\Admin\Schema;

use Echo\Framework\Admin\Schema\FieldDefinition;
use PHPUnit\Framework\TestCase;

class FieldDefinitionTest extends TestCase
{
    private function makeField(array $overrides = []): FieldDefinition
    {
        return new FieldDefinition(
            name: $overrides['name'] ?? 'email',
            label: $overrides['label'] ?? 'Email',
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

    public function testConstructorAssignsAllProperties(): void
    {
        $renderer = fn($col, $val) => "<custom>$val</custom>";
        $field = new FieldDefinition(
            name: 'role',
            label: 'Role',
            expression: null,
            control: 'dropdown',
            rules: ['required'],
            options: [['value' => 'admin', 'label' => 'Admin']],
            optionsQuery: null,
            datalist: [],
            accept: null,
            default: 'admin',
            readonly: true,
            disabled: false,
            requiredOnCreate: false,
            controlRenderer: $renderer,
        );

        $this->assertSame('role', $field->name);
        $this->assertSame('Role', $field->label);
        $this->assertNull($field->expression);
        $this->assertSame('dropdown', $field->control);
        $this->assertSame(['required'], $field->rules);
        $this->assertCount(1, $field->options);
        $this->assertNull($field->optionsQuery);
        $this->assertSame([], $field->datalist);
        $this->assertNull($field->accept);
        $this->assertSame('admin', $field->default);
        $this->assertTrue($field->readonly);
        $this->assertFalse($field->disabled);
        $this->assertFalse($field->requiredOnCreate);
        $this->assertSame($renderer, $field->controlRenderer);
    }

    // --- getSelectExpression ---

    public function testGetSelectExpressionReturnsNameWhenNoExpression(): void
    {
        $field = $this->makeField(['name' => 'email']);
        $this->assertSame('email', $field->getSelectExpression());
    }

    public function testGetSelectExpressionReturnsExpressionWhenSet(): void
    {
        $field = $this->makeField([
            'name' => 'password',
            'expression' => "'' as password",
        ]);
        $this->assertSame("'' as password", $field->getSelectExpression());
    }

    // --- isRequired ---

    public function testIsRequiredReturnsTrueWhenRequired(): void
    {
        $field = $this->makeField(['rules' => ['required', 'email']]);
        $this->assertTrue($field->isRequired());
    }

    public function testIsRequiredReturnsFalseWhenNotRequired(): void
    {
        $field = $this->makeField(['rules' => ['email']]);
        $this->assertFalse($field->isRequired());
    }

    public function testIsRequiredReturnsFalseWhenNoRules(): void
    {
        $field = $this->makeField(['rules' => []]);
        $this->assertFalse($field->isRequired());
    }

    // --- isRequired with requiredOnCreate ---

    public function testIsRequiredOnCreateReturnsRequiredForCreateFormType(): void
    {
        $field = $this->makeField([
            'rules' => ['required', 'min_length:4'],
            'requiredOnCreate' => true,
        ]);
        $this->assertTrue($field->isRequired('create'));
    }

    public function testIsRequiredOnCreateReturnsFalseForEditFormType(): void
    {
        $field = $this->makeField([
            'rules' => ['required', 'min_length:4'],
            'requiredOnCreate' => true,
        ]);
        $this->assertFalse($field->isRequired('edit'));
    }

    public function testIsRequiredOnCreateReturnsFalseForShowFormType(): void
    {
        $field = $this->makeField([
            'rules' => ['required', 'min_length:4'],
            'requiredOnCreate' => true,
        ]);
        $this->assertFalse($field->isRequired('show'));
    }

    public function testIsRequiredWithoutFlagRespectsFormTypeDefault(): void
    {
        $field = $this->makeField([
            'rules' => ['required'],
            'requiredOnCreate' => false,
        ]);
        $this->assertTrue($field->isRequired('edit'));
    }

    // --- hasRule ---

    public function testHasRuleMatchesExactRule(): void
    {
        $field = $this->makeField(['rules' => ['required', 'email', 'unique:users']]);
        $this->assertTrue($field->hasRule('required'));
        $this->assertTrue($field->hasRule('email'));
    }

    public function testHasRuleMatchesRulePrefix(): void
    {
        $field = $this->makeField(['rules' => ['unique:users']]);
        $this->assertTrue($field->hasRule('unique'));
        $this->assertTrue($field->hasRule('unique:other_table'));
    }

    public function testHasRuleReturnsFalseForMissingRule(): void
    {
        $field = $this->makeField(['rules' => ['required']]);
        $this->assertFalse($field->hasRule('email'));
    }

    public function testHasRuleReturnsFalseWhenNoRules(): void
    {
        $field = $this->makeField(['rules' => []]);
        $this->assertFalse($field->hasRule('required'));
    }

    // --- resolveOptions ---

    public function testResolveOptionsReturnsStaticOptions(): void
    {
        $options = [
            ['value' => 'admin', 'label' => 'Admin'],
            ['value' => 'standard', 'label' => 'Standard'],
        ];
        $field = $this->makeField(['options' => $options]);
        $this->assertSame($options, $field->resolveOptions());
    }

    public function testResolveOptionsReturnsEmptyArrayWhenNoOptions(): void
    {
        $field = $this->makeField();
        $this->assertSame([], $field->resolveOptions());
    }

    // --- immutability ---

    public function testFieldDefinitionIsReadonly(): void
    {
        $field = $this->makeField();

        $reflection = new \ReflectionClass($field);
        foreach ($reflection->getProperties() as $property) {
            $this->assertTrue(
                $property->isReadOnly(),
                "Property '{$property->getName()}' should be readonly"
            );
        }
    }

    public function testFieldDefinitionIsFinal(): void
    {
        $reflection = new \ReflectionClass(FieldDefinition::class);
        $this->assertTrue($reflection->isFinal());
    }
}
