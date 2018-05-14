<?php

namespace App\Http\Controllers\V1;

use App\Role;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class RolesController extends Controller
{
    /**
     * Constructor for the controller
     *
     * __construct
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('access.role:admin');
    }

    public function index()
    {
        try {
            // Get a list of role records and parse them as an array
            $roles = Role::query()->get()->map(function ($item) {
                return [
                    'id' => (int) $item['id'],
                    'name' => (string) $item['role_name'],
                    'slug' => (string) $item['role_slug'],
                    'permissions' => (array) $item['role_permissions'],
                    'date_added' => (string) Carbon::parse($item['created_at'])->format('j M Y h:i A'),
                ];
            })->toArray();

            return response()->json(['status' => 'success', 'data' => $roles]);
        } catch(\Exception $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()]);
        }
    }
}
