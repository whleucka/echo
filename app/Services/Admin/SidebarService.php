<?php

namespace App\Services\Admin;

use App\Models\User;

class SidebarService
{
    public function getState(): bool
    {
        $state = session()->get("sidebar_state");
        if (is_null($state)) {
            session()->set("sidebar_state", true);
            $state = true;
        }
        return $state;
    }

    public function setState(bool $state): void
    {
        session()->set("sidebar_state", $state);
    }

    public function toggleState(): void
    {
        $state = $this->getState();
        $this->setState(!$state);
    }

    public function getLinks(array $nodes = [], array $modules = [], ?User $user = null): array
    {
        if (is_null($user)) {
            return [];
        }

        if (empty($nodes)) {
            $nodes = db()->fetchAll(
                "SELECT * FROM modules
                WHERE parent_id IS NULL AND enabled = 1
                ORDER BY item_order"
            );
        }

        foreach ($nodes as $node) {
            if ($user->role === 'admin') {
                $children = db()->fetchAll(
                    "SELECT *, CONCAT('/admin/', link) as url
                    FROM modules
                    WHERE parent_id = ? AND enabled = 1
                    ORDER BY item_order",
                    [$node['id']]
                );
            } else {
                $children = db()->fetchAll(
                    "SELECT *, CONCAT('/admin/', link) as url
                    FROM modules
                    WHERE parent_id = ? AND
                    enabled = 1 AND
                    EXISTS (SELECT *
                        FROM user_permissions
                        WHERE user_id = ? AND module_id = modules.id)
                    ORDER BY item_order",
                    [$node['id'], $user->id]
                );
            }

            if ($children) {
                $node['children'] = $this->getLinks($children, [], $user);
            }
            $modules[] = $node;
        }

        return $modules;
    }
}
