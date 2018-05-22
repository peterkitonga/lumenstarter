<?php

namespace App\Console\Commands;

use GuzzleHttp\Client;
use Illuminate\Console\Command;

class AddSampleUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sample:users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Adds sample users to the database';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $client = new Client();
        $url = 'https://api.randomuser.me/?results=50';

        $data = $client->get($url,
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                ]
            ]
        );

        $users = json_decode((string) $data->getBody(), true);

        foreach ($users['results'] as $user)
        {
            dispatch(new \App\Jobs\AddSampleUsers($user));
        }

        $this->info('Initialised seeding of sample users successfully');

        return $this;
    }
}
