<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateClientRequestLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('client_request_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('transaction_id', 250)->nullable();
            $table->string('provider', 50)->default('klikqris');
            $table->string('event_type', 50)->nullable();
            $table->string('request_url', 255)->nullable();
            $table->string('request_method', 20)->nullable();
            $table->longText('request_headers')->nullable();
            $table->longText('request_body')->nullable();
            $table->string('signature', 255)->nullable();
            $table->tinyInteger('signature_valid')->nullable();
            $table->integer('response_status_code')->nullable();
            $table->longText('response_body')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->tinyInteger('is_success')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('request_time')->nullable();
            $table->timestamp('response_time')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies');
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
        Schema::dropIfExists('client_request_log');
    }
}
