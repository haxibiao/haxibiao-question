<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateForkExplanationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('fork_explanations')) {
            return;
        }
        Schema::create('fork_explanations', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('fork_question_id')->nullable()->index();
            $table->string('answer')->nullable()->comment('答案');
            $table->string('cover')->nullable()->comment('解析图片');
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
        Schema::dropIfExists('explanations');
    }
}
