<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateGroupsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->bigIncrements('id');


            $table->string('title')->default('Community');
            $table->string('vk_user_access_token');
            $table->unsignedBigInteger('vk_group_id')->unique(); // vk_owner_id

            // callback params
            $table->string('vk_confirmation')->nullable()->default(null);
            $table->string('vk_secret')->nullable()->default(null);


            $table->string('poll_title')->default('Rate this');
            $table->text('poll_vars')->default('Ok|Not ok|Hmm');


            $table->boolean('post_flag_upd_url')->default(false);
            $table->boolean('post_flag_watermark')->default(false);
            $table->boolean('post_flag_add_text')->default(false);
            $table->boolean('post_flag_comment')->default(true);
            $table->boolean('post_flag_poll')->default(true);


            $table->text('post_add_text')->default('');
            $table->text('comment_text_mask')->default('');
            $table->text('update_url_mask')->default('');
            $table->mediumText('post_watermark_filename')->default('');

            $table->text('post_ban_keys')->default('');


            $table->boolean('allow_from_not_alive')->default(false);
            $table->boolean('allow_from_not_member')->default(false);
            $table->boolean('allow_with_empty_text')->default(false);


            $table->unsignedBigInteger('delete_timeout_sec')->default(60 * 60 * 24 * 2);


            $table->boolean('use_notification')->default(false);
            $table->unsignedBigInteger('notification_topic')->default(0);


            //TODO: rename use_delete_link to use_chatbot
            $table->boolean('use_delete_link')->default(false);


            $table->boolean('enabled')->default(true);


            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('groups');
    }
}
