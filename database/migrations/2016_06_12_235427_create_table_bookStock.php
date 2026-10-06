<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableBookStock extends Migration {

    public function up()
    {
        Schema::create('bookStock', function(Blueprint $table)
        {
            $table->bigIncrements('id');
            $table->string('code');
            $table->integer('quantity')->unsigned();
        });
    }

    public function down()
    {
        Schema::dropIfExists('bookStock');
    }

}
