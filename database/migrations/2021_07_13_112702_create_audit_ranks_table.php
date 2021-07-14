<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAuditRanksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('audit_ranks', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('段位:青铜|白银|黄金|铂金');
            $table->integer('up_score')->comment('晋级分数');
            $table->integer('min_score')->comment('保底分数');
            $table->json('level_score')->comment('守护分数星级');
            $table->integer('count_users')->default(0)->comment('当前段位人数');
            $table->string('reward')->nullable()->comment('奖励说明');
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
        Schema::dropIfExists('audit_ranks');
    }
}
