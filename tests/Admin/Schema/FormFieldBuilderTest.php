<?php

declare(strict_types=1);

namespace Tests\Admin\Schema;

use Echo\Framework\Admin\Schema\FieldDefinition;
use Echo\Framework\Admin\Schema\FormFieldBuilder;
use PHPUnit\Framework\TestCase;

class FormFieldBuilderTest extends TestCase
{
    // --- Defaults ---

    public function testBuildWithDefaults(): void
    {
        $field = (new FormFieldBuilder('email'))->build();

        $this->assertInstanceOf(FieldDefinition::class, $field);
        $this->assertSame('email', $field->name);
        $this->assertSame('Email', $field->label); // auto-generated from name
        $this->assertNull($field->expression);
        $this->assertSame('input', $field->control); // default control
        $this->assertSame([], $field->rules);
        $this->assertSame([], $field->options);
        $this->assertNull($field->optionsQuery);
        $this->assertSame([], $field->datalist);
        $this->assertNull($field->accept);
        $this->assertNull($field->default);
        $this->assertFalse($field->readonly);
        $this->assertFalse($field->disabled);
        $this->assertFalse($field->requiredOnCreate);
        $this->assertNull($field->controlRenderer);
    }

    // --- Label auto-generation ---

    public function testAutoLabelFromUnderscoreName(): void
    {
        $field = (new FormFieldBuilder('first_name'))->build();
        $this->assertSame('First name', $field->label);
    }

    public function testAutoLabelFromSimpleName(): void
    {
        $field = (new FormFieldBuilder('role'))->build();
        $this->assertSame('Role', $field->label);
    }

    public function testExplicitLabelOverridesAuto(): void
    {
        $field = (new FormFieldBuilder('email', 'Email Address'))->build();
        $this->assertSame('Email Address', $field->label);
    }

    // --- Expression ---

    public function testExpressionPassedThrough(): void
    {
        $field = (new FormFieldBuilder('password', 'Password', "'' as password"))->build();
        $this->assertSame("'' as password", $field->expression);
    }

    // --- Control types ---

    public function testInputControl(): void
    {
        $field = (new FormFieldBuilder('name'))->input()->build();
        $this->assertSame('input', $field->control);
    }

    public function testNumberControl(): void
    {
        $field = (new FormFieldBuilder('order'))->number()->build();
        $this->assertSame('number', $field->control);
    }

    public function testCheckboxControl(): void
    {
        $field = (new FormFieldBuilder('enabled'))->checkbox()->build();
        $this->assertSame('checkbox', $field->control);
    }

    public function testEmailControl(): void
    {
        $field = (new FormFieldBuilder('email'))->email()->build();
        $this->assertSame('email', $field->control);
    }

    public function testPasswordControl(): void
    {
        $field = (new FormFieldBuilder('password'))->password()->build();
        $this->assertSame('password', $field->control);
    }

    public function testDropdownControl(): void
    {
        $field = (new FormFieldBuilder('role'))->dropdown()->build();
        $this->assertSame('dropdown', $field->control);
    }

    public function testImageControl(): void
    {
        $field = (new FormFieldBuilder('avatar'))->image()->build();
        $this->assertSame('image', $field->control);
    }

    public function testFileControl(): void
    {
        $field = (new FormFieldBuilder('document'))->file()->build();
        $this->assertSame('file', $field->control);
    }

    public function testLastControlTypeWins(): void
    {
        $field = (new FormFieldBuilder('field'))
            ->input()
            ->dropdown()
            ->password()
            ->build();
        $this->assertSame('password', $field->control);
    }

    // --- Rules ---

    public function testRulesSet(): void
    {
        $field = (new FormFieldBuilder('email'))
            ->rules(['required', 'email', 'unique:users'])
            ->build();
        $this->assertSame(['required', 'email', 'unique:users'], $field->rules);
    }

    public function testRulesOverwritePrevious(): void
    {
        $field = (new FormFieldBuilder('email'))
            ->rules(['required'])
            ->rules(['email'])
            ->build();
        $this->assertSame(['email'], $field->rules);
    }

    // --- Options ---

    public function testStaticOptions(): void
    {
        $options = [
            ['value' => 'admin', 'label' => 'Admin'],
            ['value' => 'standard', 'label' => 'Standard'],
        ];
        $field = (new FormFieldBuilder('role'))
            ->dropdown()
            ->options($options)
            ->build();

        $this->assertSame($options, $field->options);
        $this->assertNull($field->optionsQuery);
    }

    public function testOptionsFromQuery(): void
    {
        $query = "SELECT id as value, name as label FROM roles";
        $field = (new FormFieldBuilder('role'))
            ->dropdown()
            ->optionsFrom($query)
            ->build();

        $this->assertSame($query, $field->optionsQuery);
        $this->assertSame([], $field->options);
    }

    // --- Datalist ---

    public function testDatalist(): void
    {
        $values = ['icon-1', 'icon-2', 'icon-3'];
        $field = (new FormFieldBuilder('icon'))
            ->input()
            ->datalist($values)
            ->build();
        $this->assertSame($values, $field->datalist);
    }

    // --- Accept ---

    public function testAccept(): void
    {
        $field = (new FormFieldBuilder('avatar'))
            ->image()
            ->accept('image/*')
            ->build();
        $this->assertSame('image/*', $field->accept);
    }

    // --- Default ---

    public function testDefaultValue(): void
    {
        $field = (new FormFieldBuilder('role'))
            ->default('standard')
            ->build();
        $this->assertSame('standard', $field->default);
    }

    public function testDefaultValueCanBeZero(): void
    {
        $field = (new FormFieldBuilder('count'))
            ->default(0)
            ->build();
        $this->assertSame(0, $field->default);
    }

    // --- Readonly / Disabled ---

    public function testReadonly(): void
    {
        $field = (new FormFieldBuilder('id'))
            ->readonly()
            ->build();
        $this->assertTrue($field->readonly);
    }

    public function testDisabled(): void
    {
        $field = (new FormFieldBuilder('id'))
            ->disabled()
            ->build();
        $this->assertTrue($field->disabled);
    }

    // --- RequiredOnCreate ---

    public function testRequiredOnCreate(): void
    {
        $field = (new FormFieldBuilder('password'))
            ->password()
            ->requiredOnCreate()
            ->rules(['required', 'min_length:4'])
            ->build();

        $this->assertTrue($field->requiredOnCreate);
    }

    public function testRequiredOnCreateDefaultsFalse(): void
    {
        $field = (new FormFieldBuilder('email'))
            ->email()
            ->rules(['required', 'email'])
            ->build();

        $this->assertFalse($field->requiredOnCreate);
    }

    // --- Custom renderer ---

    public function testRenderUsing(): void
    {
        $renderer = fn(string $col, mixed $val) => "<custom>$val</custom>";
        $field = (new FormFieldBuilder('special'))
            ->renderUsing($renderer)
            ->build();

        $this->assertSame($renderer, $field->controlRenderer);
    }

    // --- Chaining ---

    public function testFullChaining(): void
    {
        $field = (new FormFieldBuilder('role', 'Role'))
            ->dropdown()
            ->options([['value' => 'admin', 'label' => 'Admin']])
            ->rules(['required'])
            ->default('admin')
            ->build();

        $this->assertSame('role', $field->name);
        $this->assertSame('Role', $field->label);
        $this->assertSame('dropdown', $field->control);
        $this->assertCount(1, $field->options);
        $this->assertSame(['required'], $field->rules);
        $this->assertSame('admin', $field->default);
    }

    // --- Returns self for chaining ---

    public function testAllSettersReturnSelf(): void
    {
        $builder = new FormFieldBuilder('test');

        $this->assertSame($builder, $builder->input());
        $this->assertSame($builder, $builder->number());
        $this->assertSame($builder, $builder->checkbox());
        $this->assertSame($builder, $builder->email());
        $this->assertSame($builder, $builder->password());
        $this->assertSame($builder, $builder->dropdown());
        $this->assertSame($builder, $builder->image());
        $this->assertSame($builder, $builder->file());
        $this->assertSame($builder, $builder->rules([]));
        $this->assertSame($builder, $builder->options([]));
        $this->assertSame($builder, $builder->optionsFrom('SELECT 1'));
        $this->assertSame($builder, $builder->datalist([]));
        $this->assertSame($builder, $builder->accept('image/*'));
        $this->assertSame($builder, $builder->default(null));
        $this->assertSame($builder, $builder->readonly());
        $this->assertSame($builder, $builder->disabled());
        $this->assertSame($builder, $builder->requiredOnCreate());
        $this->assertSame($builder, $builder->renderUsing(fn() => ''));
    }
}
