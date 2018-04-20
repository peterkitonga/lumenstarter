<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Role extends Model
{
    use SoftDeletes;

    protected $dates = ['deleted_at'];

    protected $fillable = ['role_name', 'role_slug', 'role_permissions'];

    protected $casts = [
        'role_permissions' => 'array',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'role_user');
    }

    public function setRoleSlugAttribute($value)
    {
        $this->attributes['role_slug'] = str_replace(" ", "_", strtolower($value));
    }

    public function hasAccess(array $permissions) : bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission))
                return true;
        }
        return false;
    }

    private function hasPermission(string $permission) : bool
    {
        return $this->role_permissions[$permission] ?? false;
    }
}
