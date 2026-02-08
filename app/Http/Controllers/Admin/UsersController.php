<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use Echo\Framework\Admin\Schema\{FormSchemaBuilder, TableSchemaBuilder};
use Echo\Framework\Http\ModuleController;
use Echo\Framework\Routing\Group;

#[Group(path_prefix: "/users", name_prefix: "users")]
class UsersController extends ModuleController
{
    protected function defineTable(TableSchemaBuilder $builder): void
    {
        $builder->defaultSort('id', 'DESC')
                ->perPage(10);

        $builder->column('id', 'ID')->sortable();
        $builder->column('uuid', 'UUID');
        $builder->column('role', 'Role')->sortable();
        $builder->column('name', 'Name', "CONCAT(first_name, ' ', surname)")
                ->sortable()
                ->searchable();
        $builder->column('email', 'Email')
                ->sortable()
                ->searchable();
        $builder->column('created_at', 'Created')->sortable();

        $builder->filter('role', 'role')
                ->label('Role')
                ->options([
                    ['value' => 'standard', 'label' => 'Standard'],
                    ['value' => 'admin', 'label' => 'Admin'],
                ]);
    }

    protected function defineForm(FormSchemaBuilder $builder): void
    {
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
                ->rules(['required', 'min_length:10', 'regex:^(?=.*[A-Z])(?=.*\W)(?=.*\d).+$']);

        $builder->field('password_match', 'Password (again)', "'' as password_match")
                ->password()
                ->rules(['required', 'match:password']);
    }

    public function __construct()
    {
        parent::__construct("users");
    }

    public function validate(array $ruleset = [], mixed $id = null): mixed
    {
        if ($id) {
            $ruleset = $this->removeValidationRule($ruleset, "email", "unique:users");
        }
        return parent::validate($ruleset);
    }

    protected function hasDelete(int $id): bool
    {
        if ($id === $this->user->id) return false;
        return parent::hasDelete($id);
    }

    protected function handleStore(array $request): mixed
    {
        $role = $request['role'] ?? 'standard';
        unset($request["password_match"]);
        $request["password"] = password_hash($request['password'], PASSWORD_ARGON2I);
        $id = parent::handleStore($request);

        if ($id !== false && $role !== 'admin') {
            $user = User::find((string) $id);
            $user?->grantDefaultPermissions();
        }

        return $id;
    }

    protected function handleUpdate(int $id, array $request): bool
    {
        unset($request["password_match"]);
        $request["password"] = password_hash($request['password'], PASSWORD_ARGON2I);
        return parent::handleUpdate($id, $request);
    }
}
