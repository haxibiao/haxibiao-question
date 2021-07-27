<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAuditTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('audit', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('question_id');
            $table->boolean('status')->default(0)->index()->comment('赞同/拒绝');
            $table->boolean('is_correct')->nullable()->index()->comment('正确/错误');
            $table->string('reason')->nullable()->comment('拒绝理由');
            $table->integer('score')->nullable()->comment('赞同分数');
            $table->timestamps();

            $table->unique(['user_id', 'question_id']);
            $table->index('question_id');
            $table->index('user_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('audit');
    }
}
