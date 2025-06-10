<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class InitializeVideos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:initialize-videos';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'initialized new videos';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $exitCode = self::FAILURE;


        return $exitCode;
    }
}
