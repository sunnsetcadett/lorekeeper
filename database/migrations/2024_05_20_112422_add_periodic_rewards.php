<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPeriodicRewards extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('periodic_rewards', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->integer('object_id')->unsigned()->default(0);
            $table->string('object_type');
            $table->string('group_name');
            $table->enum('group_operator', ['>', '=','<','!=','<=','>=']);
            $table->integer('group_quantity')->unsigned();
            $table->string('data', 5000)->nullable()->default(null);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('periodic_rewards');
    }
}
