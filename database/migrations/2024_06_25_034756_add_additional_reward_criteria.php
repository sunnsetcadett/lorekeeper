<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAdditionalRewardCriteria extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('periodic_rewards', function (Blueprint $table) {
            $table->text('reward_timeframe')->nullable()->default(null);
            //default or usual
            DB::statement("ALTER TABLE periodic_rewards MODIFY COLUMN group_operator ENUM('>', '=','<','!=','<=','>=','every')");
        });

        Schema::create('periodic_defaults', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->string('name');
            $table->string('summary', 256)->nullable()->default(null);
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
            $table->dropColumn('reward_timeframe');
        });

        Schema::dropIfExists('periodic_defaults');
    }
}
