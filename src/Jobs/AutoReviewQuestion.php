<?php

namespace Haxibiao\Question\Jobs;

use Haxibiao\Question\Question;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AutoReviewQuestion implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public $question;

    public function __construct(Question $question)
    {
        $this->question = $question;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //48小时内审核没完成过就自动通过
        if ($this->question->isReviewing()) {
            $this->question->publishReviewQuestion();
            $this->question->rank = 2; //自动通过的题目权重最低
            $this->question->saveDataOnly();
        }
    }
}
