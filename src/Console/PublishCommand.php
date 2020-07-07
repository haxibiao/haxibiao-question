<?php

namespace Haxibiao\Task\Console;

use Illuminate\Console\Command;

class PublishCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'question:publish {--force : 强制覆盖}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '发布 haxibiao-question';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {


        $this->call('vendor:publish', [
            '--tag'   => 'question-db',
            '--force' => $this->option('force'),
        ]);

        $this->call('vendor:publish', [
            '--tag'   => 'question-graphql',
            '--force' => $this->option('force'),
        ]);

        $this->call('vendor:publish', [
            '--tag'   => 'question-tests',
            '--force' => $this->option('force'),
        ]);
    }
}
