<?php

namespace App\Jobs;

use App\User;
use Illuminate\Support\Facades\Hash;

class AddSampleUsers extends Job
{
    protected $user;

    /**
     * Create a new job instance.
     *
     * @param $user
     */
    public function __construct($user)
    {
        $this->user = $user;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $check = User::query()->where('email', '=', $this->user['email'])->count();

        if ($check == 0)
        {
            $user = new User([
                'name' => (string) ($this->user['name']['first'].' '.$this->user['name']['last']),
                'email' => (string) $this->user['email'],
                'password' => Hash::make($this->user['login']['password']),
                'profile_image' => $this->user['picture']['large'],
                'activation_status' => 1
            ]);
            $user->save();

            $user->roles()->attach(2);
        }
    }
}
