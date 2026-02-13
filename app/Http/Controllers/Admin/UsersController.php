<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Services\Auth\AuthService;
use Echo\Framework\Admin\Schema\{FormSchemaBuilder, ModalSize, TableSchemaBuilder};
use Echo\Framework\Http\ModuleController;
use Echo\Framework\Routing\Group;

#[Group(pathPrefix: "/users", namePrefix: "users")]
class UsersController extends ModuleController
{
    protected string $tableName = "users";

    protected function defineTable(TableSchemaBuilder $builder): void
    {
        $builder->defaultSort('id', 'DESC');

        $builder->column('id', 'ID')->sortable();
        $builder->column('uuid', 'UUID')->sortable();
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

        $builder->rowAction('show');
        $builder->rowAction('edit');
        $builder->rowAction('delete');

        $builder->toolbarAction('create');
        $builder->toolbarAction('export');

        $builder->bulkAction('delete', 'Delete');
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
                ->rules(['required', 'min_length:4']);

        $builder->field('password_match', 'Password (again)', "'' as password_match")
                ->password()
                ->rules(['required', 'match:password']);
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
        $service = container()->get(AuthService::class);
        $role = $request['role'] ?? 'standard';
        unset($request["password_match"]);
        $request["password"] = $service->hashPassword($request['password']);
        $id = parent::handleStore($request);

        if ($id !== false && $role !== 'admin') {
            $user = User::find((string) $id);
            $user?->grantDefaultPermissions();
        }

        return $id;
    }

    protected function handleUpdate(int $id, array $request): bool
    {
        $service = container()->get(AuthService::class);
        unset($request["password_match"]);
        $request["password"] = $service->hashPassword($request['password']);
        return parent::handleUpdate($id, $request);
    }
}
