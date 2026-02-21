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

        $builder->filterLink('All', "1=1");
        $builder->filterLink('Me', sprintf("user_id = %s", user()->id));

        $builder->toolbarAction('export');
    }

    /**
     * Convert a 2-letter country code to a flag emoji, or a fallback icon
     */
    private function countryFlag(?string $code): string
    {
        if (!$code || strlen($code) !== 2) {
            return '<i class="bi bi-globe text-muted" title="Unknown"></i>';
        }

        $code = strtoupper($code);
        $flag = mb_chr(0x1F1E6 + ord($code[0]) - ord('A'))
              . mb_chr(0x1F1E6 + ord($code[1]) - ord('A'));
        return sprintf('<span title="%s" style="font-size:1.25em">%s</span>', $code, $flag);
    }
}
