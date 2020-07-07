<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateQuestionRecommendsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return voids
     */
    public function up()
    {
        Schema::create('question_recommends', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('question_id')->comment('题目id');
            $table->tinyInteger('rank')->comment('权重');

            $table->index('question_id');
            $table->index('rank');
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
        Schema::dropIfExists('question_recommends');
    }
}
