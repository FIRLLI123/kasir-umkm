<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSalesHTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sales_h', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no')->unique();
            $table->dateTime('invoice_date');
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('customer_group_id')->constrained('customer_groups');
            $table->foreignId('payment_method_id')->constrained('payment_methods');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->decimal('total_modal', 15, 2)->default(0);
            $table->decimal('total_margin', 15, 2)->default(0);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('change_amount', 15, 2)->default(0);
            $table->string('status', 2)->default('00');
            $table->text('void_reason')->nullable();
            $table->unsignedBigInteger('void_by')->nullable();
            $table->dateTime('void_at')->nullable();
            $table->timestamps();

            $table->foreign('void_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sales_h');
    }
}
