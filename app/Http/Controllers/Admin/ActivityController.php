<?php

namespace App\Http\Controllers\Admin;

use Echo\Framework\Admin\Schema\TableSchemaBuilder;
use Echo\Framework\Http\ModuleController;
use Echo\Framework\Routing\Group;

#[Group(path_prefix: "/activity", name_prefix: "activity")]
class ActivityController extends ModuleController
{
    protected function defineTable(TableSchemaBuilder $builder): void
    {
        $builder->primaryKey('activity.id')
                ->join('LEFT JOIN users ON users.id = activity.user_id')
                ->defaultSort('activity.id', 'DESC');

        $builder->column('id', 'ID', 'activity.id')->sortable();
        $builder->column('email', 'User', 'users.email')->searchable();
        $builder->column('ip', 'IP', 'INET_NTOA(activity.ip)');
        $builder->column('uri', 'URI', 'activity.uri')->searchable();
        $builder->column('created_at', 'Created', 'activity.created_at')->sortable();

        $builder->filter('email', 'users.email')
                ->label('User')
                ->optionsFrom("SELECT email as value, CONCAT(first_name, ' ', surname) as label FROM users ORDER BY label");

        $builder->filterLink('Frontend', "LEFT(activity.uri, 6) != '/admin'");
        $builder->filterLink('Backend', "LEFT(activity.uri, 6) = '/admin'");
        $builder->filterLink('Me', 'user_id = ' . user()->id);
    }

    public function __construct()
    {
        $this->has_create = $this->has_edit = $this->has_delete = false;
        parent::__construct('activity');
    }
}
