<?php

namespace Haxibiao\Task\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class InstallCommand extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'question:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '安装 haxibiao-question';

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

        $this->info('发布 资源文件 ...');
        $this->callSilent('question:publish', ['--force' => true]);


        $this->info("复制 stubs ...");
        copy($this->resolveStubPath('/stubs/Task.stub'), app_path('Task.php'));
        copy($this->resolveStubPath('/stubs/Assignment.stub'), app_path('Assignment.php'));
        copy($this->resolveStubPath('/stubs/Nova/Task.stub'), app_path('Nova/Task.php'));
        copy($this->resolveStubPath('/stubs/Nova/Filters/Task/TaskType.stub'), app_path('Nova/Filters/Task/TaskType.php'));
        copy($this->resolveStubPath('/stubs/Nova/Filters/Task/TaskStatus.stub'), app_path('Nova/Filters/Task/TaskStatus.php'));
    }

    protected function resolveStubPath($stub)
    {
        return __DIR__ . $stub;
    }

    protected function installStub()
    {
    }
}
