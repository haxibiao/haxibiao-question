<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCurationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('curations')) {
            return;
        }

        Schema::create('curations', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->index()->comment('用户表ID');
            $table->unsignedInteger('question_id')->index()->comment('题库表ID');
            $table->string('content', 1000)->nullable()->comment('内容');
            $table->tinyInteger('type')->comment('类型');
            $table->unsignedInteger('gold_awarded')->default(0)->comment('所获智慧点');
            $table->string('remark')->nullable()->comment('备注');
            $table->boolean('status')->default(0)->comment('状态: -1:未通过 0:审核中 1:审核成功');
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
        Schema::dropIfExists('curations');
    }
}
