<?php

namespace App\Http\Controllers\V1;

use App\User;
use App\Mail\MailCredentials;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\Controller;
use Symfony\Component\HttpKernel\Exception\HttpException;

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
     * @return void
     */
    public function index()
    {
        try {
            // Get a list of user records and parse them as an array
            $users = User::query()->get()->toArray();

            return response()->json(['status' => 'success', 'data' => $users]);
        } catch(\Exception $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()]);
        }
    }

    /**
     * Stores a given record.
     *
     * @param Request $request
     * 
     * @return void
     */
    public function store(Request $request)
    {
        $this->validate($request, [
            'name' => 'required|max:255',
            'email' => 'required|email|max:255|unique:users',
            'role_select' => 'required'
        ]);

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
            Mail::to($request->get('email'))->send($email);
            
            return response()->json(['status' => 'success', 'message' => 'Successfully created user', 'data' => $user]);
        } catch(\Exception $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()]);
        }
    }

    /**
     * Show the given record.
     *
     * @param mixed $id
     * 
     * @return void
     */
    public function show($id)
    {
        try {
            $user = User::query()->findOrFail($id);

            return response()->json(['status' => 'success', 'data' => $user]);
        } catch(\Exception $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()]);
        }
    }

    /**
     * Update the given record.
     *
     * @param Request $request
     * @param mixed $id
     * 
     * @return void
     */
    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'name' => 'required|max:255',
            'email' => 'required|email|max:255'
        ]);

        try {
            $user = User::query()->findOrFail($id);

            // Update the user's details
            $user->update([
                'name' => $request->get('name'),
                'email' => $request->get('email')
            ]);

            return response()->json(['status' => 'success', 'message' => 'Successfully updated '.$user['name'], 'data' => $user]);
        } catch(\Exception $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()]);
        }
    }

    /**
     * Update the role of the given record.
     *
     * @param Request $request
     * @param mixed $id
     * 
     * @return void
     */
    public function role(Request $request, $id)
    {
        $this->validate($request, [
            'role_select' => 'required'
        ]);

        try {
            $roleId = $request->get('role_select');

            $user = User::query()->findOrFail($id);

            // Detach the role form the user and attach a new role
            $user->roles()->detach();
            $user->roles()->attach($roleId);

            return response()->json(['status' => 'success', 'message' => 'Successfully updated the role attached to '.$user['name'], 'data' => $user]);
        } catch(\Exception $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()]);
        }
    }

    /**
     * Deactivate the given record.
     *
     * @param mixed $id
     * 
     * @return void
     */
    public function deactivate($id)
    {
        try {
            $user = User::query()->findOrFail($id);

            // Perform a soft delete(deactivate)
            $user->delete();

            return response()->json(['status' => 'success', 'message' => 'Successfully deactivated '.$user['name'], 'data' => $user]);
        } catch(\Exception $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()]);
        }
    }
    
    /**
     * Reactivate the given record.
     *
     * @param mixed $id
     * 
     * @return void
     */
    public function reactivate($id)
    {
        try {
            $user = User::query()->withTrashed()->where('id', '=', $id);

            // Restore(reactivate) the user model
            $user->restore();

            return response()->json(['status' => 'success', 'message' => 'Successfully reactivated '.$user['name'], 'data' => $user]);
        } catch(\Exception $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()]);
        }
    }

    /**
     * Delete the given record.
     *
     * @param mixed $id
     * 
     * @return void
     */
    public function delete($id)
    {
        try {
            $user = User::query()->withTrashed()->where('id', '=', $id);

            // Perform a permanent delete
            $user->forceDelete();

            return response()->json(['status' => 'success', 'message' => 'Successfully deleted '.$user['name'], 'data' => $user]);
        } catch(\Exception $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()]);
        }
    }
}
