<?php

namespace App\Http\Controllers\Admin;

use Echo\Framework\Admin\Schema\TableSchemaBuilder;
use Echo\Framework\Http\ModuleController;
use Echo\Framework\Routing\Group;

#[Group(path_prefix: "/modules", name_prefix: "modules")]
class ModulesController extends ModuleController
{
    protected function defineTable(TableSchemaBuilder $builder): void
    {
        $builder->defaultSort('item_order', 'ASC');

        $builder->column('id', 'ID')->sortable();
        $builder->column('enabled', 'Enabled')->format('check');
        $builder->column('link', 'Link');
        $builder->column('title', 'Title')->sortable()->searchable();
        $builder->column('icon', 'Icon')
                ->formatUsing(fn($col, $val) => "<i class='bi bi-$val' />");
        $builder->column('created_at', 'Created')->sortable();

        $builder->filterLink('Parents', 'parent_id IS NULL');
        $builder->filterLink('Children', 'parent_id IS NOT NULL');
    }

    public function __construct()
    {
        $url = config("paths.js") . '/bootstrap-icons.json';
        $json = file_get_contents($url);
        $data = json_decode($json, true);

        $this->form_datalist = [
            "icon" => array_keys($data),
        ];

        $this->form_columns = [
            "Enabled" => "enabled",
            "Parent" => "parent_id",
            "Link" => "link",
            "Title" => "title",
            "Icon" => "icon",
            "Order" => "item_order",
        ];

        $this->form_controls = [
            "enabled" => "checkbox",
            "parent_id" => "dropdown",
            "link" => "input",
            "title" => "input",
            "icon" => "input",
            "item_order" => "number",
        ];

        $this->form_dropdowns = [
            "parent_id" => "SELECT id as value, if(parent_id IS NULL, concat(title, ' (root)'), title) as label
                FROM modules
                ORDER BY parent_id IS NULL DESC, title",
        ];

        $this->validation_rules = [
            "enabled" => [],
            "parent_id" => [],
            "link" => [],
            "title" => ["required"],
            "icon" => [],
        ];

        parent::__construct("modules");
    }

    protected function handleUpdate(int $id, array $request): bool
    {
        $result = parent::handleUpdate($id, $request);
        if ($result) $this->hxTrigger("loadSidebar");
        return $result;
    }

    protected function handleStore(array $request): mixed
    {
        $result = parent::handleStore($request);
        if ($result) $this->hxTrigger("loadSidebar");
        return $result;
    }

    protected function handleDestroy(int $id): bool
    {
        $result = parent::handleDestroy($id);
        if ($result) $this->hxTrigger("loadSidebar");
        return $result;
    }
}
