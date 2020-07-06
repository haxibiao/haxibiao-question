<?php

namespace Haxibiao\Question\Jobs;

use Haxibiao\Question\Answer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class RecordTestAnswers implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    protected $questions;
    protected $user_id;
    protected $incrementField;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($questions, $user_id, $incrementField)
    {
        $this->questions      = $questions;
        $this->user_id        = $user_id;
        $this->incrementField = $incrementField;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (empty($this->questions)) {
            return;
        }
        // 保存答题记录,增加答题次数
        foreach ($this->questions as $question) {
            if (optional($question)->id) {
                $answerData = [
                    'question_id'           => $question->id,
                    'user_id'               => $this->user_id,
                    'answered_count'        => 1,
                    'gold_awarded'          => $this->incrementField == "correct_count" ? $question->gold : 0,
                    'in_rank'               => $question->rank,
                    "$this->incrementField" => 1,
                ];
                Answer::create($answerData);
            }
        }
    }
}
