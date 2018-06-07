<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\Resource;

class Role extends Resource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => (int) $this->id,
            'name' => (string) $this->role_name,
            'permissions' => (array) $this->role_permissions,
            'date_added' => (string) Carbon::parse($this->created_at)->format('d M Y h:i A')
        ];
    }
}
