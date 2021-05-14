<?php

namespace Haxibiao\Question\Jobs;

use Haxibiao\Question\Answer;
use Haxibiao\Question\WrongAnswer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class RecordTestAnswers implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    protected $answerList;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(array $answerList)
    {
        $this->answerList = $answerList;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $answerList = $this->answerList;
        if (count($answerList)) {
            //批量插入答题记录
            Answer::bulkInsert($answerList);
            //找到错误的题目 && //批量插入答题记录
            $maxAnswerCount = 1;
            $wrongAnswers   = collect($answerList)->where('answered_count', $maxAnswerCount)->where('wrong_count', $maxAnswerCount)->toArray();
            $answerObjArray = [];
            foreach ($wrongAnswers as $wrongAnswer) {
                $answerObjArray[] = (new Answer)->forceFill($wrongAnswer);
            }
            if (count($answerObjArray)) {
                WrongAnswer::addAnswers($answerObjArray);
            }
        }
    }

}
