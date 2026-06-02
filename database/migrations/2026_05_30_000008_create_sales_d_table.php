<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSalesDTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sales_d', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_h_id')->constrained('sales_h')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->string('product_name_snapshot');
            $table->decimal('qty', 15, 2);
            $table->decimal('cost_price', 15, 2)->default(0);
            $table->decimal('selling_price', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('margin', 15, 2)->default(0);
            $table->string('status', 2)->default('00');
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
        Schema::dropIfExists('sales_d');
    }
}
