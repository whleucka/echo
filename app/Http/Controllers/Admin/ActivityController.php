<?php

namespace App\Http\Controllers\Admin;

use Echo\Framework\Admin\Schema\TableSchemaBuilder;
use Echo\Framework\Http\ModuleController;
use Echo\Framework\Routing\Group;

#[Group(pathPrefix: "/activity", namePrefix: "activity")]
class ActivityController extends ModuleController
{
    protected string $tableName = "activity";

    protected function defineTable(TableSchemaBuilder $builder): void
    {
        $builder->primaryKey('activity.id')
                ->join('LEFT JOIN users ON users.id = activity.user_id')
                ->defaultSort('activity.id', 'DESC');

        $builder->column('id', 'ID', 'activity.id');
        $builder->column('email', 'User', 'users.email')->searchable();
        $builder->column('ip', 'IP', 'INET_NTOA(activity.ip)');
        $builder->column('country_code', 'Country', 'activity.country_code')
            ->searchable()
            ->formatUsing(fn($col, $val) => $this->countryFlag($val));
        $builder->column('uri', 'URI', 'activity.uri')->searchable();
        $builder->column('created_at', 'Created', 'activity.created_at');

        $builder->filter('email', 'users.email')
                ->label('User')
                ->optionsFrom("SELECT email as value, 
                    CONCAT(first_name, ' ', surname) as label 
                    FROM users 
                    ORDER BY label");
        $builder->filter('country_code', 'activity.country_code')
            ->label('Country')
            ->optionsFrom("SELECT DISTINCT country_code as value, country_code as label
                FROM activity 
                ORDER BY country_code");

        $builder->filterLink('All', "1=1");
        $builder->filterLink('Me', sprintf("user_id = %s", user()->id));

        $builder->toolbarAction('export');
    }

    /**
     * Convert a 2-letter country code to a flag icon, or a fallback icon
     */
    private function countryFlag(?string $code): string
    {
        if (!$code || strlen($code) !== 2) {
            return '<i class="bi bi-globe text-muted" title="Unknown"></i>';
        }

        $code = strtolower($code);
        return sprintf('<span class="fi fi-%s" title="%s"></span>', $code, strtoupper($code));
    }
}
