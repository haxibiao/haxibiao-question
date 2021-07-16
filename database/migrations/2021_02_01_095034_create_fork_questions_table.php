<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateForkQuestionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('fork_questions')) {
            return;
        }
        Schema::create('fork_questions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('description', 1000)->comment('题目描述');
            $table->json('selections')->comment('题目选项');

            //关联images表
            $table->unsignedInteger('image_id')->nullable()->index()->comment('主配图');

            $table->unsignedInteger('video_id')->nullable()->index()->comment('视频');

            //关联到categories表
            $table->unsignedInteger('category_id')->nullable()->index()->comment('分类ID');

            //预留type 考虑后期扩展 拼图 语音答题
            $table->string('type', 10)->default('text')->comment('类型：text 文字答题');

            //关联到users表
            $table->unsignedInteger('user_id')->nullable()->index();
            $table->integer('submit')->default(0)->index()->comment('提交状态: 1 - 已收录 -2 - 已拒绝 -3 - 已撤回 -1 - 已移除 0 - 待审核 ');

            $table->index(['category_id', 'user_id']); //for where

            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('questions');
    }
}
