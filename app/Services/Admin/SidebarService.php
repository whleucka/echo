<?php

namespace App\Services\Admin;

use App\Models\Module;
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

    /**
     * Get sidebar links for a user
     *
     * @param Module[]|null $nodes Root nodes to process (null = fetch root modules)
     * @param User|null $user Current user
     * @return array Sidebar link structure
     */
    public function getLinks(?array $nodes = null, ?User $user = null): array
    {
        if (is_null($user)) {
            return [];
        }

        // Fetch root modules if not provided
        if (is_null($nodes)) {
            $nodes = Module::where('enabled', '1')
                ->whereNull('parent_id')
                ->orderBy('item_order')
                ->get() ?? [];
        }

        $modules = [];
        foreach ($nodes as $node) {
            // Get children for this node
            $childModules = $node->children();

            // Filter by permissions for non-admin users
            if ($user->role !== 'admin') {
                $childModules = array_filter($childModules, fn(Module $child) =>
                    $user->hasPermission((int)$child->id)
                );
            }

            // Convert to array format for template compatibility
            $nodeData = $node->getAttributes();
            $nodeData['url'] = $node->url();

            if (!empty($childModules)) {
                $nodeData['children'] = $this->getLinks(array_values($childModules), $user);
            }

            $modules[] = $nodeData;
        }

        return $modules;
    }
}
