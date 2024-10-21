<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCharacterPeriodicRewards extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('periodic_rewards', function (Blueprint $table) {
            $table->string('recipient_type')->default('User');
            $table->string('reward_key')->default('periodicRewards');
        });

        Schema::table('periodic_rewards_logs', function (Blueprint $table) {
            $table->string('user_type')->default('User');
            $table->string('log_key')->default('periodicRewards');
        });

        if (!Schema::hasColumn('submission_characters', 'is_focus')) {
            Schema::table('submission_characters', function (Blueprint $table) {
                $table->boolean('is_focus')->default(0);
            });
        }
        Schema::table('submission_characters', function (Blueprint $table) {
            $table->longtext('periodic_data')->nullable()->default(null);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('periodic_rewards', function (Blueprint $table) {
            $table->dropColumn('recipient_type');
            $table->dropColumn('reward_key');
        });
        Schema::table('periodic_rewards_logs', function (Blueprint $table) {
            $table->dropColumn('user_type');
            $table->dropColumn('log_key');
        });
        Schema::table('submission_characters', function (Blueprint $table) {
            $table->dropColumn('is_focus');
            $table->dropColumn('periodic_data');
        });
    }
}
