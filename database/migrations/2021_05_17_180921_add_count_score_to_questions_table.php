<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCountScoreToQuestionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('questions', function (Blueprint $table) {
            //
            if (!Schema::hasColumn('questions', 'score')) {
                $table->integer('score')->default(0)->comment('评分');
            }
            if (!Schema::hasColumn('questions', 'count_score')) {
                $table->integer('count_score')->default(0)->comment('评分人数｜打分人数');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('questions', function (Blueprint $table) {
            //
        });
    }
}
