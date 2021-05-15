<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateApisTable extends Migration
{
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('apis', function(Blueprint $table)
		{
			$table->increments('id');
			$table->string('apiMethod');
			$table->string('apiRoute');
			$table->string('scope')->nullable();
			$table->string('permission')->nullable();
			$table->string('name');
			$table->string('description')->nullable();
			$table->string('sourceApiMethod')->nullable();
			$table->string('sourceApiUrl')->nullable();
			$table->text('ETLCode')->nullable();
			$table->text('ETLCodeError')->nullable();
			$table->boolean('isFlatten')->default(0);
			$table->boolean('isMaskError')->default(0);
			$table->string('created_by')->nullable();
			$table->string('updated_by')->nullable();
			$table->string('deleted_by')->nullable();
			$table->softDeletes();
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
		Schema::drop('apis');
	}
}
