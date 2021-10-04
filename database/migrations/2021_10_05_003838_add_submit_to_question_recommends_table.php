<?php

use App\Question;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSubmitToQuestionRecommendsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('question_recommends', function (Blueprint $table) {
            $table->unsignedTinyInteger('submit')->default(Question::SUBMITTED_SUBMIT);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('question_recommends', function (Blueprint $table) {
            //
        });
    }
}
