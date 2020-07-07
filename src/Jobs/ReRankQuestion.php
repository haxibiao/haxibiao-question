<?php

namespace Haxibiao\Question\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class ReRankQuestion implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    protected $question;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($question)
    {
        $this->question = $question;
        $this->onQueue('questions');
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->question->reRank();
    }
}
