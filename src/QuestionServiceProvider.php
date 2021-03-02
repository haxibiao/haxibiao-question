<?php

namespace Haxibiao\Question;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class QuestionServiceProvider extends ServiceProvider
{

    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        'Haxibiao\Question\Events\PublishQuestion' => [
            'Haxibiao\Question\Listeners\RewardUser',
        ],
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
                __DIR__ . '/../graphql' => base_path('graphql/question'),
            ], 'question-graphql');

            //注册 migrations paths
            $this->loadMigrationsFrom($this->app->make('path.haxibiao-question.migrations'));

            //注册监听器
            $this->registerEvent();
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
            'path.haxibiao-question'            => $root = dirname(__DIR__),
            'path.haxibiao-question.config'     => $root . '/config',
            'path.haxibiao-question.graphql'    => $root . '/graphql',
            'path.haxibiao-question.database'   => $database = $root . '/database',
            'path.haxibiao-question.migrations' => $database . '/migrations',
            'path.haxibiao-question.seeds'      => $database . '/seeds',
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

    public function registerEvent()
    {
        foreach ($this->listen as $event => $listeners) {
            foreach ($listeners as $listener) {
                Event::listen($event, $listener);
            }
        }
    }
}
