<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\Resource;
use App\Http\Resources\Role as RoleResource;

class User extends Resource
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
            'name' => (string) $this->name,
            'email' => (string) $this->email,
            'is_logged_in' => (bool) ($this->is_logged_in == 0 ? false : true),
            'is_active' => (bool) ($this->activation_status == 0 ? false : true),
            'is_deactivated' => (bool) ($this->deleted_at == null ? false : true),
            'image' => (string) $this->profile_image == null ? null : $this->profile_image,
            'role' => (array) isset($this->roles[0]) ? new RoleResource($this->roles[0]) : [],
            'date_added' => (string) Carbon::parse($this->created_at)->format('j M Y h:i A'),
            'last_seen' => (string) $this->last_seen == null ? 'Never' : Carbon::parse($this->last_seen)->format('j M Y h:i A')
        ];
    }
}
