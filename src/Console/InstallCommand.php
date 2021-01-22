<?php

namespace Haxibiao\Question\Console;

use Illuminate\Console\Command;

class InstallCommand extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'question:install {--force}';

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
        $force = $this->option('force');

        $this->info('发布 资源文件 ...');
        $this->callSilent('question:publish', ['--force' => $force]);

        $this->info("复制 stubs ...");
        copyStubs(__DIR__, $force);

        $this->comment('迁移数据库变化...');
        $this->call('migrate');
        $this->comment('question 模块 安装完成');
    }
}
