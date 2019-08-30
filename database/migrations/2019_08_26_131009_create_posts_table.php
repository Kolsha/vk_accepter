<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePostsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->bigIncrements('id');


            $table->unsignedBigInteger('group_id');

            $table->unsignedBigInteger('user_id')->nullable();


            $table->foreign('group_id')
                ->references('vk_group_id')
                ->on('groups')
                ->onDelete('cascade');

            $table->unsignedBigInteger('post_id');

            $table->enum('post_type', [
                'suggested',
                'to_update',
                'updated',
                'to_delete'
            ])->default('suggested');

            $table->json('object')->nullable();


            $table->timestamps();

            $table->unique(['group_id', 'post_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('posts');
    }
}
