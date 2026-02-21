<?php

namespace App\Http\Controllers\Admin;

use Echo\Framework\Admin\Schema\{FormSchemaBuilder, ModalSize, TableSchemaBuilder};
use Echo\Framework\Http\ModuleController;
use Echo\Framework\Routing\Group;

#[Group(pathPrefix: "/modules", namePrefix: "modules")]
class ModulesController extends ModuleController
{
    protected string $tableName = "modules";

    protected function defineTable(TableSchemaBuilder $builder): void
    {
        $builder->defaultSort('id', 'ASC');

        $builder->column('id', 'ID');
        $builder->column('enabled', 'Enabled')->format('check');
        $builder->column('link', 'Link');
        $builder->column('title', 'Title')->searchable();
        $builder->column('icon', 'Icon')
                ->formatUsing(fn($col, $val) => "<i class='bi bi-$val' />");
        $builder->column('created_at', 'Created');

        $builder->filterLink('Parents', 'parent_id IS NULL');
        $builder->filterLink('Children', 'parent_id IS NOT NULL');

        $builder->rowAction('show');
        $builder->rowAction('edit');
        $builder->rowAction('delete');

        $builder->toolbarAction('create');
        $builder->toolbarAction('export');

        $builder->bulkAction('delete', 'Delete');
    }

    protected function defineForm(FormSchemaBuilder $builder): void
    {
        $builder->modalSize(ModalSize::Small);

        $builder->field('enabled', 'Enabled')->checkbox();

        $builder->field('parent_id', 'Parent')
                ->dropdown()
                ->optionsFrom("SELECT id as value, if(parent_id IS NULL, concat(title, ' (root)'), title) as label
                FROM modules
                ORDER BY parent_id IS NULL DESC, title");

        $builder->field('link', 'Link')
            ->input()
            ->rules(['required']);

        $builder->field('title', 'Title')
                ->input()
                ->rules(['required']);

        $builder->field('icon', 'Icon')
                ->input()
                ->datalist($this->getIconList())
                ->rules(['required']);

        $builder->field('item_order', 'Order')
            ->number()
            ->rules(['required']);
    }

    private function getIconList(): array
    {
        $url = config("paths.js") . '/bootstrap-icons.json';
        $json = file_get_contents($url);
        $data = json_decode($json, true);
        return array_keys($data);
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
