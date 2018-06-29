<?php

namespace App\Http\Controllers\V1;

use App\Http\Resources\RoleCollection;
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
        $this->middleware('access.role:administrator');
    }

    /**
     * Displays a list of records.
     *
     * @param Request $request
     * @return RoleCollection|\Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            // Get the limit of records per page
            $limit = $request->has('limit') ? $request->get('limit') : 10;

            // Get a list of role records and parse them as an array
            $roles = Role::query()->paginate($limit);

            $response = new RoleCollection($roles);

            return $response;
        } catch(\Exception $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()]);
        }
    }
}
