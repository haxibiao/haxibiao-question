<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateQuestionSorcesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('question_scores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('question_id')->index()->comment("题目id");
            $table->unsignedBigInteger('user_id')->index()->comment("题目id");
            $table->tinyInteger('score')->comment("打分：1｜2｜3｜4｜5");
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
        Schema::dropIfExists('question_sorces');
    }
}
