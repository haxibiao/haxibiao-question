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
        $this->installStub();
        $this->comment('迁移数据库变化...');
        $this->call('migrate');
        $this->comment('question模块安装完成');
    }

    protected function resolveStubPath($stub)
    {
        return __DIR__ . $stub;
    }

    protected function installStub()
    {
        $stubPath =  $this->stubPath();
        foreach ($stubPath as $key => $value) {
            copy($this->resolveStubPath($key), app_path($value));
        }
    }


    protected function stubPath()
    {
        return [
            '/stubs/Answer.stub' =>  'Answer.php',
            '/stubs/Question.stub' => 'Question.php',
            '/stubs/Category.stub' => 'Category.php',
            '/stubs/CategoryUser.stub' => 'CategoryUser.php',
            '/stubs/Explanation.stub' => 'Explanation.php',
            '/stubs/QuestionRecommend.stub' => 'QuestionRecommend.php',
            '/stubs/Tag.stub' => 'Tag.php',
            '/stubs/Taggable.stub' => 'Taggable.php',
            '/stubs/UserAction.stub' => 'UserAction.php',
            '/stubs/WrongAnswer.stub' => 'WrongAnswer.php',
            '/stubs/Audit.stub' => 'Audit.php',
            '/stubs/Curation.stub' => 'Curation.php',
            '/stubs/Audio.stub' => 'Audio.php',
        ];
    }
}
