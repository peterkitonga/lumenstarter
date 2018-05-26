<?php

namespace App\Http\Controllers\V1;

use App\Http\Resources\UserCollection;
use App\Role;
use App\User;
use App\Mail\MailCredentials;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\User as UserResource;

class UsersController extends Controller
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

    /**
     * Displays a list of records.
     *
     * @return UserCollection|\Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            // Get a list of user records and parse them as an array
            $users = User::query()->withTrashed()->paginate(10);

            $response = new UserCollection($users);

            return $response;
        } catch(\Exception $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()], 500);
        }
    }

    /**
     * Stores a given record.
     *
     * @param Request $request
     * @return UserResource|\Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|max:255',
            'email' => 'required|email|max:255|unique:users',
            'role_select' => 'required'
        ]);

        if ($validator->fails()) {
            $errorResponse = [];
            $errors = array_map(function ($value) {
                return implode(' ', $value);
            }, $validator->errors()->toArray());
            $errorKeys = $validator->errors()->keys();

            foreach ($errorKeys as $key)
            {
                $array = [$key => $errors[$key]];
                array_push($errorResponse, $array);
            }

            return response()->json(['status' => 'error', 'message' => $errorResponse], 500);
        }

        try {
            // Get the role selected
            $roleId = $request->get('role_select');

            // Create a random string of 10 Characters for the password
            $password = str_random(10);

            // Create the new user record
            $user = new User([
                'name' => $request->get('name'), 
                'email' => $request->get('email'),
                'password' => Hash::make($password),
                'activation_status' => 1,
                'is_logged_in' => 0
            ]);
            $user->save();

            // Attach role selected to the user
            $user->roles()->attach($roleId);

            // Send an email with the password generated
            $email = new MailCredentials(new User(['password' => $password, 'name' => $request->get('name'), 'email' => $request->get('email')]));
            Mail::to($request->get('email'))->sendNow($email);

            $resource = new UserResource($user);
            $response = $resource->additional(['status' => 'success', 'message' => 'Successfully created user']);

            return $response;
        } catch(\Exception $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()], 500);
        }
    }

    /**
     * Show the given record.
     *
     * @param $id
     * @return UserResource|\Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $user = User::query()->withTrashed()->where('id', '=', $id)->first();

            if (count($user) !== 0)
            {
                $resource = new UserResource($user);
                $response = $resource->additional(['status' => 'success']);

                return $response;
            } else {
                return response()->json(['status' => 'error', 'message' => 'User with id '.$id.' not found']);
            }
        } catch(\Exception $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()], 500);
        }
    }

    /**
     * Update the given record.
     *
     * @param Request $request
     * @param $id
     * @return UserResource|\Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|max:255',
            'email' => 'required|email|max:255'
        ]);

        if ($validator->fails()) {
            $errorResponse = [];
            $errors = array_map(function ($value) {
                return implode(' ', $value);
            }, $validator->errors()->toArray());
            $errorKeys = $validator->errors()->keys();

            foreach ($errorKeys as $key)
            {
                $array = [$key => $errors[$key]];
                array_push($errorResponse, $array);
            }

            return response()->json(['status' => 'error', 'message' => $errorResponse], 500);
        }

        try {
            $user = User::query()->findOrFail($id);

            // Update the user's details
            $user->update([
                'name' => $request->get('name'),
                'email' => $request->get('email')
            ]);

            $resource = new UserResource($user);
            $response = $resource->additional(['status' => 'success', 'message' => 'Successfully updated '.$user['name']]);

            return $response;
        } catch(\Exception $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()], 500);
        }
    }

    /**
     * Update the role of the given record.
     *
     * @param Request $request
     * @param $id
     * @return UserResource|\Illuminate\Http\JsonResponse
     */
    public function role(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'role_select' => 'required'
        ]);

        if ($validator->fails()) {
            $errorResponse = [];
            $errors = array_map(function ($value) {
                return implode(' ', $value);
            }, $validator->errors()->toArray());
            $errorKeys = $validator->errors()->keys();

            foreach ($errorKeys as $key)
            {
                $array = [$key => $errors[$key]];
                array_push($errorResponse, $array);
            }

            return response()->json(['status' => 'error', 'message' => $errorResponse], 500);
        }

        try {
            $roleId = $request->get('role_select');

            $user = User::query()->findOrFail($id);

            // Detach the role form the user and attach a new role
            $user->roles()->detach();
            $user->roles()->attach($roleId);

            $role = Role::query()->findOrFail($roleId);

            $resource = new UserResource($user);
            $response = $resource->additional(['status' => 'success', 'message' => 'Successfully updated the role for '.$user['name'].' to '.$role['role_name']]);

            return $response;
        } catch(\Exception $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()], 500);
        }
    }

    /**
     * Deactivate the given record.
     *
     * @param $id
     * @return UserResource|\Illuminate\Http\JsonResponse
     */
    public function deactivate($id)
    {
        try {
            $user = User::query()->findOrFail($id);

            // Perform a soft delete(deactivate)
            $user->update(['activation_status' => 0]);
            $user->delete();

            $resource = new UserResource($user);
            $response = $resource->additional(['status' => 'success', 'message' => 'Successfully deactivated '.$user['name']]);

            return $response;
        } catch(\Exception $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()], 500);
        }
    }

    /**
     * Reactivate the given record.
     *
     * @param $id
     * @return UserResource|\Illuminate\Http\JsonResponse
     */
    public function reactivate($id)
    {
        try {
            $user = User::query()->withTrashed()->where('id', '=', $id);
            $user->update(['activation_status' => 1]);

            // Restore(reactivate) the user model
            $user->restore();

            $resource = new UserResource($user->first());
            $response = $resource->additional(['status' => 'success', 'message' => 'Successfully reactivated '.$user->first()['name']]);

            return $response;
        } catch(\Exception $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()], 500);
        }
    }

    /**
     * Delete the given record.
     *
     * @param $id
     * @return UserResource|\Illuminate\Http\JsonResponse
     */
    public function delete($id)
    {
        try {
            $user = User::query()->withTrashed()->where('id', '=', $id);

            if (count($user->first()) !== 0)
            {
                $data = $user->first();

                // Perform a permanent delete
                $user->forceDelete();

                $resource = new UserResource($data);
                $response = $resource->additional(['status' => 'success', 'message' => 'Successfully deleted '.$data['name']]);

                return $response;
            } else {
                return response()->json(['status' => 'error', 'message' => 'User with id '.$id.' not found']);
            }
        } catch(\Exception $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()], 500);
        }
    }
}
