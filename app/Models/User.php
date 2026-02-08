<?php

namespace App\Models;

use Echo\Framework\Audit\Auditable;
use Echo\Framework\Database\Model;

class User extends Model
{
    use Auditable;

    public function __construct(?string $id = null)
    {
        parent::__construct('users', $id);
    }

    public function fullName()
    {
        return trim($this->first_name . ' ' . $this->surname);
    }

    public function avatar()
    {
        return $this->belongsTo(FileInfo::class, "avatar");
    }

    public function gravatar(int $size = 80, string $default = "mp", string $rating = "g")
    {
        $hash = hash( "sha256", strtolower(trim($this->email)));
        return "https://www.gravatar.com/avatar/{$hash}?s={$size}&d={$default}&r={$rating}";
    }

    public function hasPermission(int $module_id): bool
    {
        $permission = UserPermission::where("user_id", $this->id)
            ->andWhere("module_id", $module_id)
            ->get();
        return $permission ? true : false;
    }

    public function hasModePermission(int $module_id, string $mode): bool
    {
        $permission = UserPermission::where("user_id", $this->id)
            ->andWhere("module_id", $module_id)
            ->andWhere($mode, 1)
            ->get();
        return $permission ? true : false;
    }

    /**
     * Grant default permissions for a newly created standard user.
     * Currently grants access to the dashboard module.
     */
    public function grantDefaultPermissions(): void
    {
        $dashboard = Module::where('link', 'dashboard')->first();

        if ($dashboard && $this->id) {
            UserPermission::create([
                'module_id' => (int) $dashboard->id,
                'user_id' => (int) $this->id,
                'has_create' => 0,
                'has_edit' => 0,
                'has_delete' => 0,
                'has_export' => 0,
            ]);
        }
    }
}
