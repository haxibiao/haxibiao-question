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
            //有2票否决就拒绝
            if ($this->question->declined_count >= 2) {
                $this->question->submit = Question::REFUSED_SUBMIT;
                $audit                  = $this->question->audits()->deny()->latest()->first();
                $remark                 = '审题被拒绝';
                if ($audit) {
                    $remark = $audit->reason;
                }
                $this->question->remark      = $remark;
                $this->question->rejected_at = now();
            } else {
                //赞同数
                $accepted_count = $this->question->accepted_count;
                if ($accepted_count == 0) {
                    $this->question->rank = 1;
                } else if ($accepted_count < 5) {
                    $this->question->rank = 2;
                } else if ($accepted_count < 10) {
                    $this->question->rank = 3;
                }
            }
            $this->question->saveDataOnly();
        }
    }
}
