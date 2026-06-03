<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateCompaniesAndAttachCompanyIdToTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('company_code', 100)->unique();
            $table->text('address')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('logo')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });

        DB::table('companies')->insert([
            'id' => 1,
            'company_name' => 'Demo Company',
            'company_code' => 'DEMO',
            'address' => null,
            'phone' => null,
            'email' => null,
            'logo' => null,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tables = [
            'users',
            'customer_groups',
            'customers',
            'products',
            'product_prices',
            'payment_methods',
            'app_settings',
            'sales_h',
            'sales_d',
            'stock_mutations',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unsignedBigInteger('company_id')->nullable()->after('id');
            });
        }

        foreach ($tables as $table) {
            DB::table($table)->update(['company_id' => 1]);
        }

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->foreign('company_id')->references('id')->on('companies');
            });
        }

        DB::statement('ALTER TABLE users MODIFY company_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE customer_groups MODIFY company_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE customers MODIFY company_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE products MODIFY company_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE product_prices MODIFY company_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE payment_methods MODIFY company_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE app_settings MODIFY company_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE sales_h MODIFY company_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE sales_d MODIFY company_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE stock_mutations MODIFY company_id BIGINT UNSIGNED NOT NULL');

        Schema::table('customer_groups', function (Blueprint $table) {
            $table->dropUnique('customer_groups_group_code_unique');
            $table->unique(['company_id', 'group_code'], 'customer_groups_company_id_group_code_unique');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique('customers_customer_code_unique');
            $table->unique(['company_id', 'customer_code'], 'customers_company_id_customer_code_unique');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique('products_product_code_unique');
            $table->unique(['company_id', 'product_code'], 'products_company_id_product_code_unique');
        });

        Schema::table('product_prices', function (Blueprint $table) {
            $table->index('product_id', 'product_prices_product_id_index');
            $table->dropUnique('product_prices_product_id_customer_group_id_unique');
            $table->unique(
                ['company_id', 'product_id', 'customer_group_id'],
                'product_prices_company_id_product_id_customer_group_id_unique'
            );
        });

        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropUnique('payment_methods_method_code_unique');
            $table->unique(['company_id', 'method_code'], 'payment_methods_company_id_method_code_unique');
        });

        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropUnique('app_settings_setting_key_unique');
            $table->unique(['company_id', 'setting_key'], 'app_settings_company_id_setting_key_unique');
        });

        Schema::table('sales_h', function (Blueprint $table) {
            $table->dropUnique('sales_h_invoice_no_unique');
            $table->unique(['company_id', 'invoice_no'], 'sales_h_company_id_invoice_no_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('sales_h', function (Blueprint $table) {
            $table->dropUnique('sales_h_company_id_invoice_no_unique');
            $table->unique('invoice_no', 'sales_h_invoice_no_unique');
        });

        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropUnique('app_settings_company_id_setting_key_unique');
            $table->unique('setting_key', 'app_settings_setting_key_unique');
        });

        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropUnique('payment_methods_company_id_method_code_unique');
            $table->unique('method_code', 'payment_methods_method_code_unique');
        });

        Schema::table('product_prices', function (Blueprint $table) {
            $table->dropUnique('product_prices_company_id_product_id_customer_group_id_unique');
            $table->unique(['product_id', 'customer_group_id'], 'product_prices_product_id_customer_group_id_unique');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique('products_company_id_product_code_unique');
            $table->unique('product_code', 'products_product_code_unique');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique('customers_company_id_customer_code_unique');
            $table->unique('customer_code', 'customers_customer_code_unique');
        });

        Schema::table('customer_groups', function (Blueprint $table) {
            $table->dropUnique('customer_groups_company_id_group_code_unique');
            $table->unique('group_code', 'customer_groups_group_code_unique');
        });

        $tables = [
            'users',
            'customer_groups',
            'customers',
            'products',
            'product_prices',
            'payment_methods',
            'app_settings',
            'sales_h',
            'sales_d',
            'stock_mutations',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropForeign(['company_id']);
                $blueprint->dropColumn('company_id');
            });
        }

        Schema::dropIfExists('companies');
    }
}
