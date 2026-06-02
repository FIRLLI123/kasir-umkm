<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStockMutationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('stock_mutations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products');
            $table->dateTime('mutation_date');
            $table->string('mutation_type');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->decimal('qty_in', 15, 2)->default(0);
            $table->decimal('qty_out', 15, 2)->default(0);
            $table->decimal('stock_before', 15, 2)->default(0);
            $table->decimal('stock_after', 15, 2)->default(0);
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('status', 2)->default('00');
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('stock_mutations');
    }
}
