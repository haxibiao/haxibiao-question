<?php

namespace Haxibiao\Question;

use Illuminate\Support\ServiceProvider;

class QuestionServiceProvider extends ServiceProvider
{

    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        'App\Events\PublishQuestion' => [
            'App\Listeners\RewardUser'
        ]
    ];
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->commands([
            Console\InstallCommand::class,
            Console\PublishCommand::class,
        ]);
        $this->bindPathsInContainer();
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        // $this->publishes([
        //     __DIR__ . '/Console/stubs/QuestionServiceProvider.stub' => app_path('Providers/QuestionServiceProvider.php'),
        // ], 'question-provider');

        if ($this->app->runningInConsole()) {

            $this->publishes([
                __DIR__ . '/../database/factories' => database_path('./factories'),
            ], 'question-db');

            $this->publishes([
                __DIR__ . '/../graphql' => base_path('graphql'),
            ], 'question-graphql');

            //注册 migrations paths
            $this->loadMigrationsFrom($this->app->make('path.haxibiao-task.migrations'));
        }
    }

    /**
     * Bind paths in container.
     *
     * @return void
     */
    protected function bindPathsInContainer()
    {
        foreach ([
            'path.haxibiao-task'            => $root = dirname(__DIR__),
            'path.haxibiao-task.config'     => $root . '/config',
            'path.haxibiao-task.graphql'    => $root . '/graphql',
            'path.haxibiao-task.database'   => $database = $root . '/database',
            'path.haxibiao-task.migrations' => $database . '/migrations',
            'path.haxibiao-task.seeds'      => $database . '/seeds',
        ] as $abstract => $instance) {
            $this->app->instance($abstract, $instance);
        }
    }

    public function bindModelObserve()
    {
        \Haxibiao\Question\Answer::observe(\Haxibiao\Question\Observers\AnswerObserver::class);
        \Haxibiao\Question\Question::observe(\Haxibiao\Question\Observers\QuestionObserver::class);
        \Haxibiao\Question\Tag::observe(\Haxibiao\Question\Observers\TagObserver::class);

        //Category FIXME:兼容 content包 需要费点心思
        \Haxibiao\Question\Category::observe(\Haxibiao\Question\Observers\CategoryObserver::class);
    }
}
