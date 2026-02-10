<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Models\UserPermission;
use Echo\Framework\Admin\Schema\{FormSchemaBuilder, TableSchemaBuilder};
use Echo\Framework\Http\ModuleController;
use Echo\Framework\Routing\Group;

#[Group(path_prefix: "/user-permissions", name_prefix: "user-permissions")]
class UserPermissionsController extends ModuleController
{
    protected string $table_name = "user_permissions";

    protected function defineTable(TableSchemaBuilder $builder): void
    {
        $builder->primaryKey('user_permissions.id')
                ->join('INNER JOIN modules ON modules.id = user_permissions.module_id')
                ->join('INNER JOIN users ON users.id = user_permissions.user_id')
                ->defaultSort('user_permissions.id', 'DESC');

        $builder->column('id', 'ID', 'user_permissions.id')->sortable();
        $builder->column('title', 'Module', 'modules.title');
        $builder->column('user_id', 'User', "CONCAT(users.first_name, ' ', users.surname)");
        $builder->column('has_create', 'Create', 'user_permissions.has_create')->format('check');
        $builder->column('has_edit', 'Edit', 'user_permissions.has_edit')->format('check');
        $builder->column('has_delete', 'Delete', 'user_permissions.has_delete')->format('check');
        $builder->column('has_export', 'Export CSV', 'user_permissions.has_export')->format('check');
        $builder->column('created_at', 'Created', 'user_permissions.created_at')->sortable();

        $builder->filter('module', 'modules.title')
                ->label('Module')
                ->optionsFrom("SELECT title as value, title as label FROM modules WHERE parent_id IS NOT NULL AND enabled = 1 ORDER BY label");

        $builder->filter('user', 'user_permissions.user_id')
                ->label('User')
                ->optionsFrom("SELECT id as value, CONCAT(first_name, ' ', surname) as label FROM users WHERE role != 'admin' ORDER BY label");
    }

    protected function defineForm(FormSchemaBuilder $builder): void
    {
        $builder->field('module_id', 'Module')
                ->dropdown()
                ->optionsFrom("SELECT id as value, title as label FROM modules WHERE parent_id IS NOT NULL AND enabled = 1 ORDER BY title")
                ->rules(['required']);

        $builder->field('user_id', 'User')
                ->dropdown()
                ->optionsFrom("SELECT id as value, CONCAT(first_name, ' ', surname) as label FROM users WHERE role != 'admin' ORDER BY label")
                ->rules(['required']);

        $builder->field('has_create', 'Create')->checkbox();
        $builder->field('has_edit', 'Edit')->checkbox();
        $builder->field('has_delete', 'Delete')->checkbox();
        $builder->field('has_export', 'Export CSV')->checkbox();
    }

    public function validate(array $ruleset = [], mixed $id = null): mixed
    {
        $request = parent::validate($ruleset, $id);
        if ($request && isset($request->module_id) && isset($request->user_id)) {
            $module_id = $request->module_id;
            $user_id = $request->user_id;
            $user = $user_id ? User::find($user_id) : null;
            if ($user) {
                $exists = $user->hasPermission($module_id);
                if ($id) {
                    $user_permission = UserPermission::find($id);
                    if ($exists && $user_permission && $user_permission->module_id != $request->module_id) {
                        $this->addValidationError("module_id", "This user already has permission to this module");
                        return null;
                    }
                } elseif ($exists) {
                    $this->addValidationError("module_id", "This user already has permission to this module");
                    return null;
                }
            }
        }
        return $request;
    }
}
