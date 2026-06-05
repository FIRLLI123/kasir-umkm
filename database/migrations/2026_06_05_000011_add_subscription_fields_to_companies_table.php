<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddSubscriptionFieldsToCompaniesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->unsignedBigInteger('owner_user_id')->nullable()->after('id');
            $table->string('subscription_status', 20)->default('trial')->after('status');
            $table->timestamp('trial_starts_at')->nullable()->after('subscription_status');
            $table->timestamp('trial_ends_at')->nullable()->after('trial_starts_at');
            $table->timestamp('subscription_starts_at')->nullable()->after('trial_ends_at');
            $table->timestamp('subscription_ends_at')->nullable()->after('subscription_starts_at');
            $table->timestamp('activated_at')->nullable()->after('subscription_ends_at');
            $table->timestamp('expired_at')->nullable()->after('activated_at');
        });

        DB::table('companies')->update([
            'subscription_status' => 'active',
            'subscription_starts_at' => now(),
            'activated_at' => now(),
        ]);

        Schema::table('companies', function (Blueprint $table) {
            $table->foreign('owner_user_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropForeign(['owner_user_id']);
            $table->dropColumn([
                'owner_user_id',
                'subscription_status',
                'trial_starts_at',
                'trial_ends_at',
                'subscription_starts_at',
                'subscription_ends_at',
                'activated_at',
                'expired_at',
            ]);
        });
    }
}
