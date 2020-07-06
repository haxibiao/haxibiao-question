<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTagsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->comment('标签名');
            $table->unsignedInteger('tag_id')->nullable()->index()->comment('标签ID');
            $table->unsignedInteger('user_id')->nullable()->index()->comment('用户ID');
            $table->unsignedInteger('count')->default(0)->comment('总数');
            $table->tinyInteger('status')->dafault(0)->comment('状态: -1:删除 0:不可见 1:可见');
            $table->integer('rank')->default(0)->comment('排名');
            $table->string('remark')->nullable()->comment('描述');
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
        Schema::dropIfExists('tags');
    }
}
