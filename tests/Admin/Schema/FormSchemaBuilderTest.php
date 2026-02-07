<?php

declare(strict_types=1);

namespace Tests\Admin\Schema;

use Echo\Framework\Admin\Schema\FieldDefinition;
use Echo\Framework\Admin\Schema\FormFieldBuilder;
use Echo\Framework\Admin\Schema\FormSchema;
use Echo\Framework\Admin\Schema\FormSchemaBuilder;
use PHPUnit\Framework\TestCase;

class FormSchemaBuilderTest extends TestCase
{
    // --- Empty builder ---

    public function testBuildEmptySchema(): void
    {
        $builder = new FormSchemaBuilder();
        $schema = $builder->build();

        $this->assertInstanceOf(FormSchema::class, $schema);
        $this->assertSame([], $schema->fields);
    }

    // --- field() returns FormFieldBuilder ---

    public function testFieldReturnsFormFieldBuilder(): void
    {
        $builder = new FormSchemaBuilder();
        $fieldBuilder = $builder->field('email', 'Email');

        $this->assertInstanceOf(FormFieldBuilder::class, $fieldBuilder);
    }

    // --- Single field ---

    public function testBuildWithSingleField(): void
    {
        $builder = new FormSchemaBuilder();
        $builder->field('email', 'Email')->email()->rules(['required', 'email']);

        $schema = $builder->build();

        $this->assertCount(1, $schema->fields);
        $this->assertSame('email', $schema->fields[0]->name);
        $this->assertSame('Email', $schema->fields[0]->label);
        $this->assertSame('email', $schema->fields[0]->control);
        $this->assertSame(['required', 'email'], $schema->fields[0]->rules);
    }

    // --- Multiple fields preserve order ---

    public function testBuildPreservesFieldOrder(): void
    {
        $builder = new FormSchemaBuilder();
        $builder->field('avatar', 'Avatar')->image();
        $builder->field('role', 'Role')->dropdown();
        $builder->field('email', 'Email')->email();

        $schema = $builder->build();

        $this->assertCount(3, $schema->fields);
        $this->assertSame('avatar', $schema->fields[0]->name);
        $this->assertSame('role', $schema->fields[1]->name);
        $this->assertSame('email', $schema->fields[2]->name);
    }

    // --- Expression passed through ---

    public function testFieldWithExpression(): void
    {
        $builder = new FormSchemaBuilder();
        $builder->field('password', 'Password', "'' as password")->password();

        $schema = $builder->build();

        $this->assertSame("'' as password", $schema->fields[0]->expression);
        $this->assertSame("'' as password", $schema->fields[0]->getSelectExpression());
    }

    // --- All FieldDefinitions are immutable ---

    public function testBuildProducesFieldDefinitions(): void
    {
        $builder = new FormSchemaBuilder();
        $builder->field('name')->input();

        $schema = $builder->build();

        $this->assertInstanceOf(FieldDefinition::class, $schema->fields[0]);
    }

    // --- Integration: full UsersController-like schema ---

    public function testFullUsersControllerSchema(): void
    {
        $builder = new FormSchemaBuilder();

        $builder->field('avatar', 'Avatar')
                ->image()
                ->accept('image/*');

        $builder->field('role', 'Role')
                ->dropdown()
                ->options([
                    ['value' => 'standard', 'label' => 'Standard'],
                    ['value' => 'admin', 'label' => 'Admin'],
                ])
                ->rules(['required']);

        $builder->field('first_name', 'First Name')
                ->input()
                ->rules(['required']);

        $builder->field('surname', 'Surname')
                ->input();

        $builder->field('email', 'Email')
                ->email()
                ->rules(['required', 'email', 'unique:users']);

        $builder->field('password', 'Password', "'' as password")
                ->password()
                ->rules(['required', 'min_length:10']);

        $builder->field('password_match', 'Password (again)', "'' as password_match")
                ->password()
                ->rules(['required', 'match:password']);

        $schema = $builder->build();

        // Field count
        $this->assertCount(7, $schema->fields);

        // Labels (positional, for form-modal.html.twig)
        $this->assertSame(
            ['Avatar', 'Role', 'First Name', 'Surname', 'Email', 'Password', 'Password (again)'],
            $schema->getLabels()
        );

        // SELECT expressions
        $selects = $schema->getSelectExpressions();
        $this->assertSame('avatar', $selects[0]);
        $this->assertSame("'' as password", $selects[5]);
        $this->assertSame("'' as password_match", $selects[6]);

        // Validation rules
        $rules = $schema->getValidationRules();
        $this->assertSame([], $rules['avatar']);
        $this->assertSame(['required'], $rules['role']);
        $this->assertSame(['required', 'email', 'unique:users'], $rules['email']);
        $this->assertSame(['required', 'match:password'], $rules['password_match']);

        // Defaults (all null since none set)
        $defaults = $schema->getDefaults();
        $this->assertNull($defaults['email']);
        $this->assertNull($defaults['avatar']);

        // Field lookup
        $avatar = $schema->getField('avatar');
        $this->assertSame('image', $avatar->control);
        $this->assertSame('image/*', $avatar->accept);

        $role = $schema->getField('role');
        $this->assertSame('dropdown', $role->control);
        $this->assertCount(2, $role->options);
    }

    // --- Integration: modules-like schema with checkboxes and datalist ---

    public function testModulesControllerSchema(): void
    {
        $builder = new FormSchemaBuilder();

        $builder->field('enabled', 'Enabled')->checkbox();
        $builder->field('parent_id', 'Parent')
                ->dropdown()
                ->optionsFrom("SELECT id as value, title as label FROM modules");
        $builder->field('title', 'Title')->input()->rules(['required']);
        $builder->field('icon', 'Icon')->input()->datalist(['icon-a', 'icon-b']);
        $builder->field('item_order', 'Order')->number();

        $schema = $builder->build();

        $this->assertCount(5, $schema->fields);

        // Checkbox
        $enabled = $schema->getField('enabled');
        $this->assertSame('checkbox', $enabled->control);

        // Dropdown with query
        $parent = $schema->getField('parent_id');
        $this->assertSame('dropdown', $parent->control);
        $this->assertSame("SELECT id as value, title as label FROM modules", $parent->optionsQuery);

        // Datalist
        $icon = $schema->getField('icon');
        $this->assertSame(['icon-a', 'icon-b'], $icon->datalist);

        // Number
        $order = $schema->getField('item_order');
        $this->assertSame('number', $order->control);
    }

    // --- Build is idempotent ---

    public function testBuildCanBeCalledMultipleTimes(): void
    {
        $builder = new FormSchemaBuilder();
        $builder->field('name')->input();

        $schema1 = $builder->build();
        $schema2 = $builder->build();

        $this->assertCount(1, $schema1->fields);
        $this->assertCount(1, $schema2->fields);
        $this->assertNotSame($schema1, $schema2);
        // Each build creates new FieldDefinition instances
        $this->assertNotSame($schema1->fields[0], $schema2->fields[0]);
    }
}
