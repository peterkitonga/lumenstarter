<?php

namespace App\Http\Controllers\V1\Auth;

use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Mail\MailActivation;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class AuthsController extends Controller
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
        //
    }

    /**
     * Get the token array structure.
     *
     * @param  string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token)
    {
        return response()->json([
            'status' => 'success',
            'data' => [
                'access_token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => app('auth')->factory()->getTTL() * 60
            ]
        ]);
    }

    /**
     * Register a user and return a JWT
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|max:255',
            'email' => 'required|email|max:255|unique:users',
            'password' => 'required|min:6|confirmed|same:password_confirmation',
            'password_confirmation' => 'required|min:6'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()]);
        }

        try 
        {
            // Added new user with the given details
            $user = new User([
                'name' => $request->get('name'), 
                'email' => $request->get('email'), 
                'password' => Hash::make($request->get('password')),
                'activation_code' => str_random(60),
                'is_logged_in' => 0
            ]);
            $user->save();

            // Assign role 'subscriber' to added user
            $user->roles()->attach(2);

            // Send Activation Mail
            $email = new MailActivation(new User(['activation_code' => $user->activation_code, 'name' => $user->name]));
            Mail::to($user->email)->send($email);

            return response()->json(['status' => 'success', 'message' => 'You have successfully registered. Please click on the activation link sent to your email']);
        } catch(\Exception $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()]);
        }
    }

    /**
     * Get a JWT via given credentials.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required',
            'password' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()]);
        }

        try {
            $credentials = $request->all();
            
            // Check and authenticate credentials given
            if ($token = app('auth')->attempt($credentials)) 
            {
                // Retrieve user details
                $user = app('auth')->user();

                // Update user to status 'online'
                User::query()->findOrFail($user['id'])->update(['is_logged_in' => 1]);

                return $this->respondWithToken($token);
            } else {
                return response()->json(['status' => 'error', 'message' => 'Unauthorized, credentials given are not correct'], 401);
            }
        } catch(\Exception $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()]);
        }
    }

    /**
     * Activate the user with the given activation code.
     *
     * @param mixed $code
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function activate($code)
    {
        try {
            $user = User::query()->where('activation_code', '=', $code)->firstOrFail();

            // Activate the user
            $user->activate();

            return response()->json(['status' => 'success', 'message' => 'Your account has been successfully activated. You may proceed to login']);
        } catch(\Exception $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()]);
        }
    }

    /**
     * Get the authenticated user.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function profile()
    {
        try {
            // Retrieve user details
            $userId = app('auth')->user()->id;

            $user = User::query()->where('id', '=', $userId)->with('roles:id,role_name as name,created_at as date_added')->get()
                ->map(function ($item) {
                    return [
                        'id' => (int) $item['id'],
                        'name' => (string) $item['name'],
                        'email' => (string) $item['email'],
                        'active' => (bool) ($item['activation_status'] == 0 ? false : true),
                        'is_logged_in' => (bool) ($item['is_logged_in'] == 0 ? false : true),
                        'is_deactivated' => (bool) ($item['deleted_at'] == null ? false : true),
                        'image' => (string) $item['profile_image'] == null ? null : $item['profile_image'],
                        'role' => (array) isset($item['roles'][0]) ? $item['roles'][0] : [],
                        'date_added' => (string) Carbon::parse($item['created_at'])->format('j M Y h:i A'),
                        'last_seen' => (string) $item['last_seen'] == null ? 'Never' : Carbon::parse($item['last_seen'])->format('j M Y h:i A')
                    ];
                })->toArray();

            return response()->json(['status' => 'success', 'data' => $user[0]]);
        } catch(\Exception $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()]);
        }
    }

    /**
     * Update the authenticated user's profile.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|max:255',
            'email' => 'required|email|max:255',
            'image_select' => 'sometimes'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()]);
        }

        try {
            $userId = app('auth')->id();
            $user = User::query()->where('id', '=', $userId);

            // Check if an image upload exists in the request
            if($request->has('image_select'))
            {
                $image = $request->get('image_select');
            } else {
                $image = $user->first()['profile_image'];
            }

            // Update the user's details
            $user->update([
                'name' => $request->get('name'),
                'email' => $request->get('email'),
                'profile_image' => $image
            ]);

            $data = $user->with('roles:id,role_name as name,created_at as date_added')->get()->map(function ($item) {
                return [
                    'id' => (int) $item['id'],
                    'name' => (string) $item['name'],
                    'email' => (string) $item['email'],
                    'active' => (bool) ($item['activation_status'] == 0 ? false : true),
                    'is_logged_in' => (bool) ($item['is_logged_in'] == 0 ? false : true),
                    'is_deactivated' => (bool) ($item['deleted_at'] == null ? false : true),
                    'image' => (string) $item['profile_image'] == null ? null : $item['profile_image'],
                    'role' => (array) isset($item['roles'][0]) ? $item['roles'][0] : [],
                    'date_added' => (string) Carbon::parse($item['created_at'])->format('j M Y h:i A'),
                    'last_seen' => (string) $item['last_seen'] == null ? 'Never' : Carbon::parse($item['last_seen'])->format('j M Y h:i A')
                ];
            })->toArray();

            return response()->json(['status' => 'success', 'message' => 'Successfully updated your profile', 'data' => $data[0]]);
        } catch(\Exception $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()]);
        }
    }

    /**
     * Updates the authenticated user's password.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function password(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|min:6',
            'password' => 'required|min:6|confirmed|different:current_password',
            'password_confirmation' => 'required|min:6'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()]);
        }

        try {
            $user = app('auth')->user();
            $profileId = $user->id;
            $hashedPassword = $user->password;

            // Check if the current user's password matched the one in the request
            if (Hash::check($request->get('current_password'), $hashedPassword)) {
                $user = User::query()->findOrFail($profileId);

                // Update the password
                $user->update(['password' => Hash::make($request->get('password'))]);
    
                return response()->json(['status' => 'success', 'message' => 'Successfully updated your password']);
            } else {
                return response()->json(['status' => 'error', 'message' => 'The old password you entered is incorrect']);
            }
        } catch(\Exception $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()]);
        }
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        try {
            // Refresh the expired JWT token
            $token = app('auth')->refresh();

            return $this->respondWithToken($token);
        } catch(\Exception $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()]);
        }
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        try {
            // Retrieve user details
            $user = app('auth')->user();

            // Update user to status 'online'
            User::query()->findOrFail($user['id'])->update(['is_logged_in' => 0, 'last_seen' => Carbon::now()->toDateTimeString()]);

            // Logout user and invalidate JWT token
            app('auth')->logout();

            return response()->json(['status' => 'success', 'message' => 'Successfully logged out']);
        } catch(\Exception $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()]);
        }
    }
}
