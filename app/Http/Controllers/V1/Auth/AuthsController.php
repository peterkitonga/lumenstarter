<?php

namespace App\Http\Controllers\V1\Auth;

use App\Mail\MailResetPassword;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Mail\MailActivation;
use Illuminate\Http\Testing\MimeType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\User as UserResource;

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
            $errorResponse = [];
            $errors = array_map(function ($value) {
                return implode(' ', $value);
            }, $validator->errors()->toArray());
            $errorKeys = $validator->errors()->keys();

            foreach ($errorKeys as $key)
            {
                $array = ['field' => $key, 'error' => $errors[$key]];
                array_push($errorResponse, $array);
            }

            return response()->json(['status' => 'error', 'message' => $errorResponse], 500);
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
            Mail::to($user->email)->sendNow($email);

            return response()->json(['status' => 'success', 'message' => 'You have successfully registered. Please click on the activation link sent to your email']);
        } catch(\Exception $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()], 500);
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
            $errorResponse = [];
            $errors = array_map(function ($value) {
                return implode(' ', $value);
            }, $validator->errors()->toArray());
            $errorKeys = $validator->errors()->keys();

            foreach ($errorKeys as $key)
            {
                $array = ['field' => $key, 'error' => $errors[$key]];
                array_push($errorResponse, $array);
            }

            return response()->json(['status' => 'error', 'message' => $errorResponse], 500);
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
                return response()->json(['status' => 'error', 'message' => 'Unauthorized, credentials given are not correct'], 500);
            }
        } catch(\Exception $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()], 500);
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
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()], 500);
        }
    }

    /**
     * Get the authenticated user.
     *
     * @return UserResource|\Illuminate\Http\JsonResponse
     */
    public function profile()
    {
        try {
            // Retrieve user details
            $userId = app('auth')->user()->id;

            $user = User::query()->findOrFail($userId);

            $resource = new UserResource($user);
            $response = $resource->additional(['status' => 'success']);

            return $response;
        } catch(\Exception $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()], 500);
        }
    }

    /**
     * Update the authenticated user's profile.
     *
     * @param Request $request
     * @return UserResource|\Illuminate\Http\JsonResponse
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|max:255',
            'email' => 'required|email|max:255',
            'image_select' => 'sometimes'
        ]);

        if ($validator->fails()) {
            $errorResponse = [];
            $errors = array_map(function ($value) {
                return implode(' ', $value);
            }, $validator->errors()->toArray());
            $errorKeys = $validator->errors()->keys();

            foreach ($errorKeys as $key)
            {
                $array = ['field' => $key, 'error' => $errors[$key]];
                array_push($errorResponse, $array);
            }

            return response()->json(['status' => 'error', 'message' => $errorResponse], 500);
        }

        try {
            $userId = app('auth')->id();
            $user = User::query()->findOrFail($userId);

            // Check if an image upload exists in the request
            if($request->has('image_select') && $request->get('image_select') !== '')
            {
                if ($user['profile_image'] !== null)
                {
                    $filename = trim(str_replace(Storage::url(''), '', $user['profile_image']), '/');
                    Storage::disk(env('FILESYSTEM_DRIVER'))->delete($filename);
                }

                $explodeEncodedString = explode('base64,', $request->get('image_select'));
                $mime = trim(str_replace('data:', '', $explodeEncodedString[0]), ';');
                $extension = MimeType::search($mime);
                $filename = Carbon::now()->timestamp.'.'.$extension;
                $image = Storage::url($filename);

                Storage::disk(env('FILESYSTEM_DRIVER'))->put($filename, base64_decode($explodeEncodedString[1]));
            } else {
                $image = $user['profile_image'];
            }

            // Update the user's details
            $user->update([
                'name' => $request->get('name'),
                'email' => $request->get('email'),
                'profile_image' => $image
            ]);

            $resource = new UserResource($user);
            $response = $resource->additional(['status' => 'success', 'message' => 'Successfully updated your profile']);

            return $response;
        } catch(\Exception $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()], 500);
        }
    }

    /**
     * Updates the authenticated user's password.
     *
     * @param Request $request
     * @return UserResource|\Illuminate\Http\JsonResponse
     */
    public function password(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|min:6',
            'password' => 'required|min:6|confirmed|different:current_password',
            'password_confirmation' => 'required|min:6'
        ]);

        if ($validator->fails()) {
            $errorResponse = [];
            $errors = array_map(function ($value) {
                return implode(' ', $value);
            }, $validator->errors()->toArray());
            $errorKeys = $validator->errors()->keys();

            foreach ($errorKeys as $key)
            {
                $array = ['field' => $key, 'error' => $errors[$key]];
                array_push($errorResponse, $array);
            }

            return response()->json(['status' => 'error', 'message' => $errorResponse], 500);
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

                $resource = new UserResource($user);
                $response = $resource->additional(['status' => 'success', 'message' => 'Successfully updated your password']);

                return $response;
            } else {
                return response()->json(['status' => 'error', 'message' => 'The old password you entered is incorrect'], 500);
            }
        } catch(\Exception $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()], 500);
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
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()], 500);
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
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()], 500);
        }
    }

    /**
     * Send a reset password email with token
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function email(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255'
        ]);

        if ($validator->fails())
        {
            $errorResponse = [];
            $errors = array_map(function ($value) {
                return implode(' ', $value);
            }, $validator->errors()->toArray());
            $errorKeys = $validator->errors()->keys();

            foreach ($errorKeys as $key)
            {
                $array = ['field' => $key, 'error' => $errors[$key]];
                array_push($errorResponse, $array);
            }

            return response()->json(['status' => 'error', 'message' => $errorResponse], 500);
        }

        try {
            // Retrieve user details
            $user = User::query()->where('email', '=', $request->get('email'))->first();

            if ($user)
            {
                $token = str_random(64);
                DB::table('password_resets')->insert(['email' => $user->email, 'token' => $token, 'created_at' => Carbon::now()->toDateTimeString()]);

                // Send Reset Password Mail
                $email = new MailResetPassword(new User(['name' => $user->name, 'email' => $user->email]), $token);
                Mail::to($user->email)->sendNow($email);

                return response()->json(['status' => 'success', 'message' => 'Successfully sent you a reset password link. Please check your email'], 200);
            } else {
                return response()->json(['status' => 'error', 'message' => 'We do not have any record of that email'], 500);
            }
        } catch(\Exception $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()], 500);
        }
    }

    /**
     * Resets password for the email with the given token
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function reset(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|min:64|max:255',
            'password' => 'required|min:6|confirmed',
            'password_confirmation' => 'required|min:6'
        ]);

        if ($validator->fails())
        {
            $errorResponse = [];
            $errors = array_map(function ($value) {
                return implode(' ', $value);
            }, $validator->errors()->toArray());
            $errorKeys = $validator->errors()->keys();

            foreach ($errorKeys as $key)
            {
                $array = ['field' => $key, 'error' => $errors[$key]];
                array_push($errorResponse, $array);
            }

            return response()->json(['status' => 'error', 'message' => $errorResponse], 500);
        }

        try {
            // Retrieve password reset details
            $reset = DB::table('password_resets')->where('token', '=', $request->get('token'));

            if ($reset)
            {
                $email = $reset->first()->email;
                $user = User::query()->where('email', '=', $email)->update(['password' => Hash::make($request->get('password'))]);

                if ($user)
                {
                    $reset->delete();

                    return response()->json(['status' => 'success', 'message' => 'Successfully reset your password. You may proceed to login'], 200);
                } else {
                    return response()->json(['status' => 'error', 'message' => 'Something went wrong. Please try again'], 500);
                }
            } else {
                return response()->json(['status' => 'error', 'message' => 'Token given does not match our records'], 500);
            }
        } catch(\Exception $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()], 500);
        }
    }
}
