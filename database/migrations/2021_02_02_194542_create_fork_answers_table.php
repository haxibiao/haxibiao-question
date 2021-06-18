<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateForkAnswersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('fork_answers')) {
            return;
        }
        Schema::create('fork_answers', function (Blueprint $table) {
            $table->increments('id');
            //关联users表
            $table->unsignedInteger('user_id')->index()->comment('用户ID');
            //关联fork_questions表
            $table->unsignedInteger('fork_question_id')->index()->comment('分支题目ID');

            $table->string('answer')->comment('选项abcd');
            $table->timestamps();

            $table->index(['created_at', 'user_id', 'fork_question_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('answer');
    }
}
