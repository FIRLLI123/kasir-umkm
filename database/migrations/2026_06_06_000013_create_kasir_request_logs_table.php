<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateKasirRequestLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('kasir_request_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('request_user')->nullable();
            $table->string('transaction_id', 250)->nullable();
            $table->string('provider', 50)->default('klikqris');
            $table->string('action', 50)->nullable();
            $table->string('request_url', 255)->nullable();
            $table->string('request_method', 20)->nullable();
            $table->longText('request_headers')->nullable();
            $table->longText('request_body')->nullable();
            $table->integer('response_status_code')->nullable();
            $table->longText('response_headers')->nullable();
            $table->longText('response_body')->nullable();
            $table->tinyInteger('is_success')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('request_time')->nullable();
            $table->timestamp('response_time')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies');
            $table->foreign('request_user')->references('id')->on('users');
            $table->index('transaction_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('kasir_request_log');
    }
}
