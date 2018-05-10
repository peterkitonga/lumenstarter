<?php

namespace App\Http\Controllers\V1;

use App\Role;
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
            $roles = Role::query()->get()->toArray();

            return response()->json(['status' => 'success', 'data' => $roles]);
        } catch(\Exception $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()]);
        }
    }
}
