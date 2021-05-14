<?php

namespace Haxibiao\Question\Jobs;

use Haxibiao\Question\Question;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class QuestionCheck implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    protected $question;
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
        //一些需要异步检查的

        $question = $this->question;
        //1.检查上传的图片是否包含二维码（包含下架）
        if ($question && $question->status == Question::REVIEW_SUBMIT) {
            if ($question->cover ?? null) {
                $hasQrcode = false;
                try {
                    $hasQrcode = Question::checkImgIsQrCode($question->cover);
                } catch (\Throwable $ex) {}

                //图片包含二维码，直接下架
                if ($hasQrcode) {
                    $question->update(['status' => Question::REMOVED_SUBMIT, 'rejected_at' => now(), 'remark' => "题目图片包含二维码"]);
                }
            }
        }
    }
}
