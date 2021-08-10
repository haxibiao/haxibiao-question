<?php

namespace Haxibiao\Question\Jobs;

use Haxibiao\Question\Audit;
use Haxibiao\Question\Question;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AuditReward implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $question;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($question)
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
        //奖励审题争取的用户
        //何为审题正确？审题选择和最终题目 收录/拒绝 结果一样即为正确
        //获取刷新status
        $question = Question::find($this->question->id);
        Audit::rewardAuditUser($question);
    }
}
