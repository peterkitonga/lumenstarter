<?php

namespace App\Http\Controllers\V1;

use App\User;
use App\Mail\MailCredentials;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

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
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            // Get a list of user records and parse them as an array
            $users = User::query()->withTrashed()->with('roles')->get()
                ->map(function ($item) {
                    return [
                        'id' => (int) $item['id'],
                        'name' => (string) $item['name'],
                        'email' => (string) $item['email'],
                        'active' => (bool) ($item['activation_status'] == 0 ? false : true),
                        'is_logged_in' => (bool) ($item['is_logged_in'] == 0 ? false : true),
                        'is_deactivated' => (bool) ($item['deleted_at'] == null ? false : true),
                        'image' => (string) $item['profile_image'] == null ? null : $item['profile_image'],
                        'role' => (string) isset($item['roles'][0]) ? $item['roles'][0]['role_name'] : null,
                        'date_added' => (string) Carbon::parse($item['created_at'])->format('j M Y h:i A'),
                        'last_seen' => (string) $item['last_seen'] == null ? 'Never' : Carbon::parse($item['last_seen'])->format('j M Y h:i A')
                    ];
                })->toArray();

            return response()->json(['status' => 'success', 'data' => $users]);
        } catch(\Exception $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()]);
        }
    }

    /**
     * Stores a given record.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|max:255',
            'email' => 'required|email|max:255|unique:users',
            'role_select' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()]);
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
            Mail::to($request->get('email'))->queue($email);

            $data = User::query()->where('id', '=', $user->id)->with('roles')->get()->map(function ($item) {
                return [
                    'id' => (int) $item['id'],
                    'name' => (string) $item['name'],
                    'email' => (string) $item['email'],
                    'active' => (bool) ($item['activation_status'] == 0 ? false : true),
                    'is_logged_in' => (bool) ($item['is_logged_in'] == 0 ? false : true),
                    'is_deactivated' => (bool) ($item['deleted_at'] == null ? false : true),
                    'image' => (string) $item['profile_image'] == null ? null : $item['profile_image'],
                    'role' => (string) isset($item['roles'][0]) ? $item['roles'][0]['role_name'] : null,
                    'date_added' => (string) Carbon::parse($item['created_at'])->format('j M Y h:i A'),
                    'last_seen' => (string) $item['last_seen'] == null ? 'Never' : Carbon::parse($item['last_seen'])->format('j M Y h:i A')
                ];
            })->toArray();
            
            return response()->json(['status' => 'success', 'message' => 'Successfully created user', 'data' => $data[0]]);
        } catch(\Exception $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()]);
        }
    }

    /**
     * Show the given record.
     *
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $user = User::query()->withTrashed()->where('id', '=', $id)->with('roles')->get()
                ->map(function ($item) {
                    return [
                        'id' => (int) $item['id'],
                        'name' => (string) $item['name'],
                        'email' => (string) $item['email'],
                        'active' => (bool) ($item['activation_status'] == 0 ? false : true),
                        'is_logged_in' => (bool) ($item['is_logged_in'] == 0 ? false : true),
                        'is_deactivated' => (bool) ($item['deleted_at'] == null ? false : true),
                        'image' => (string) $item['profile_image'] == null ? null : $item['profile_image'],
                        'role' => (string) isset($item['roles'][0]) ? $item['roles'][0]['role_name'] : null,
                        'date_added' => (string) Carbon::parse($item['created_at'])->format('j M Y h:i A'),
                        'last_seen' => (string) $item['last_seen'] == null ? 'Never' : Carbon::parse($item['last_seen'])->format('j M Y h:i A')
                    ];
                })->toArray();

            if (count($user) !== 0)
            {
                return response()->json(['status' => 'success', 'data' => $user[0]]);
            } else {
                return response()->json(['status' => 'error', 'message' => 'User with id '.$id.' not found']);
            }
        } catch(\Exception $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()]);
        }
    }

    /**
     * Update the given record.
     *
     * @param Request $request
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|max:255',
            'email' => 'required|email|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()]);
        }

        try {
            $user = User::query()->where('id', '=', $id)->with('roles');

            // Update the user's details
            $user->update([
                'name' => $request->get('name'),
                'email' => $request->get('email')
            ]);

            $data = $user->get()->map(function ($item) {
                return [
                    'id' => (int) $item['id'],
                    'name' => (string) $item['name'],
                    'email' => (string) $item['email'],
                    'active' => (bool) ($item['activation_status'] == 0 ? false : true),
                    'is_logged_in' => (bool) ($item['is_logged_in'] == 0 ? false : true),
                    'is_deactivated' => (bool) ($item['deleted_at'] == null ? false : true),
                    'image' => (string) $item['profile_image'] == null ? null : $item['profile_image'],
                    'role' => (string) isset($item['roles'][0]) ? $item['roles'][0]['role_name'] : null,
                    'date_added' => (string) Carbon::parse($item['created_at'])->format('j M Y h:i A'),
                    'last_seen' => (string) $item['last_seen'] == null ? 'Never' : Carbon::parse($item['last_seen'])->format('j M Y h:i A')
                ];
            })->toArray();

            return response()->json(['status' => 'success', 'message' => 'Successfully updated '.$data[0]['name'], 'data' => $data[0]]);
        } catch(\Exception $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()]);
        }
    }

    /**
     * Update the role of the given record.
     *
     * @param Request $request
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function role(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'role_select' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()]);
        }

        try {
            $roleId = $request->get('role_select');

            $user = User::query();

            // Detach the role form the user and attach a new role
            $user->findOrFail($id)->roles()->detach();
            $user->findOrFail($id)->roles()->attach($roleId);

            $data = $user->where('id', '=', $id)->get()->map(function ($item) {
                return [
                    'id' => (int) $item['id'],
                    'name' => (string) $item['name'],
                    'email' => (string) $item['email'],
                    'active' => (bool) ($item['activation_status'] == 0 ? false : true),
                    'is_logged_in' => (bool) ($item['is_logged_in'] == 0 ? false : true),
                    'is_deactivated' => (bool) ($item['deleted_at'] == null ? false : true),
                    'image' => (string) $item['profile_image'] == null ? null : $item['profile_image'],
                    'role' => (string) isset($item['roles'][0]) ? $item['roles'][0]['role_name'] : null,
                    'date_added' => (string) Carbon::parse($item['created_at'])->format('j M Y h:i A'),
                    'last_seen' => (string) $item['last_seen'] == null ? 'Never' : Carbon::parse($item['last_seen'])->format('j M Y h:i A')
                ];
            })->toArray();

            return response()->json(['status' => 'success', 'message' => 'Successfully updated the role for '.$data[0]['name'].' to '.$data[0]['role'], 'data' => $data[0]]);
        } catch(\Exception $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()]);
        }
    }

    /**
     * Deactivate the given record.
     *
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function deactivate($id)
    {
        try {
            $user = User::query();

            // Perform a soft delete(deactivate)
            $user->findOrFail($id)->delete();

            $data = $user->withTrashed()->where('id', '=', $id)->get()->map(function ($item) {
                return [
                    'id' => (int) $item['id'],
                    'name' => (string) $item['name'],
                    'email' => (string) $item['email'],
                    'active' => (bool) ($item['activation_status'] == 0 ? false : true),
                    'is_logged_in' => (bool) ($item['is_logged_in'] == 0 ? false : true),
                    'is_deactivated' => (bool) ($item['deleted_at'] == null ? false : true),
                    'image' => (string) $item['profile_image'] == null ? null : $item['profile_image'],
                    'role' => (string) isset($item['roles'][0]) ? $item['roles'][0]['role_name'] : null,
                    'date_added' => (string) Carbon::parse($item['created_at'])->format('j M Y h:i A'),
                    'last_seen' => (string) $item['last_seen'] == null ? 'Never' : Carbon::parse($item['last_seen'])->format('j M Y h:i A')
                ];
            })->toArray();

            return response()->json(['status' => 'success', 'message' => 'Successfully deactivated '.$data[0]['name'], 'data' => $data[0]]);
        } catch(\Exception $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()]);
        }
    }

    /**
     * Reactivate the given record.
     *
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function reactivate($id)
    {
        try {
            $user = User::query()->withTrashed()->where('id', '=', $id);

            // Restore(reactivate) the user model
            $user->restore();

            $data = $user->get()->map(function ($item) {
                return [
                    'id' => (int) $item['id'],
                    'name' => (string) $item['name'],
                    'email' => (string) $item['email'],
                    'active' => (bool) ($item['activation_status'] == 0 ? false : true),
                    'is_logged_in' => (bool) ($item['is_logged_in'] == 0 ? false : true),
                    'is_deactivated' => (bool) ($item['deleted_at'] == null ? false : true),
                    'image' => (string) $item['profile_image'] == null ? null : $item['profile_image'],
                    'role' => (string) isset($item['roles'][0]) ? $item['roles'][0]['role_name'] : null,
                    'date_added' => (string) Carbon::parse($item['created_at'])->format('j M Y h:i A'),
                    'last_seen' => (string) $item['last_seen'] == null ? 'Never' : Carbon::parse($item['last_seen'])->format('j M Y h:i A')
                ];
            })->toArray();

            return response()->json(['status' => 'success', 'message' => 'Successfully reactivated '.$data[0]['name'], 'data' => $data[0]]);
        } catch(\Exception $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()]);
        }
    }

    /**
     * Delete the given record.
     *
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete($id)
    {
        try {
            $user = User::query()->withTrashed()->where('id', '=', $id);

            if (count($user->first()) !== 0)
            {
                $data = $user->get()->map(function ($item) {
                    return [
                        'id' => (int) $item['id'],
                        'name' => (string) $item['name'],
                        'email' => (string) $item['email'],
                        'active' => (bool) ($item['activation_status'] == 0 ? false : true),
                        'is_logged_in' => (bool) ($item['is_logged_in'] == 0 ? false : true),
                        'is_deactivated' => (bool) ($item['deleted_at'] == null ? false : true),
                        'image' => (string) $item['profile_image'] == null ? null : $item['profile_image'],
                        'role' => (string) isset($item['roles'][0]) ? $item['roles'][0]['role_name'] : null,
                        'date_added' => (string) Carbon::parse($item['created_at'])->format('j M Y h:i A'),
                        'last_seen' => (string) $item['last_seen'] == null ? 'Never' : Carbon::parse($item['last_seen'])->format('j M Y h:i A')
                    ];
                })->toArray();

                // Perform a permanent delete
                $user->forceDelete();

                return response()->json(['status' => 'success', 'message' => 'Successfully deleted '.$data[0]['name'], 'data' => $data[0]]);
            } else {
                return response()->json(['status' => 'error', 'message' => 'User with id '.$id.' not found']);
            }
        } catch(\Exception $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()]);
        }
    }
}
