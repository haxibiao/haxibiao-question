<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWrongAnswersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('wrong_answers')) {
            return;
        }
        Schema::create('wrong_answers', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->unique()->comment('用户ID');
            $table->json('data')->nullable()->comment('错题数据');
            $table->unsignedInteger('count')->default(0)->comment('错题数');
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
        Schema::dropIfExists('wrong_answers');
    }
}
