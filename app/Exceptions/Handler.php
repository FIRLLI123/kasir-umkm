<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        $this->renderable(function (AuthenticationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Silakan login terlebih dahulu.',
                    'data' => null,
                ], 401);
            }
        });

        $this->renderable(function (ValidationException $e, $request) {
            if ($request->is('api/*')) {
                $errors = $e->errors();
                $firstField = array_key_first($errors);
                $firstMessage = $firstField ? $this->transformValidationMessage($firstField, $errors[$firstField][0]) : 'Validasi gagal.';

                return response()->json([
                    'success' => false,
                    'message' => $firstMessage,
                    'data' => null,
                    'errors' => $this->transformValidationErrors($errors),
                ], 422);
            }
        });

        $this->renderable(function (ModelNotFoundException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => $this->modelNotFoundMessage(class_basename($e->getModel())),
                    'data' => null,
                ], 404);
            }
        });

        $this->renderable(function (QueryException $e, $request) {
            if ($request->is('api/*')) {
                if ((int) $e->getCode() === 23000) {
                    return response()->json([
                        'success' => false,
                        'message' => $this->duplicateOrConstraintMessage($e->getMessage()),
                        'data' => null,
                    ], 422);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Terjadi kesalahan pada database.',
                    'data' => null,
                ], 500);
            }
        });

        $this->renderable(function (Throwable $e, $request) {
            if ($request->is('api/*') && $e instanceof HttpExceptionInterface && ! $e instanceof HttpResponseException) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage() ?: 'Terjadi kesalahan pada permintaan.',
                    'data' => null,
                ], $e->getStatusCode());
            }
        });
    }

    protected function transformValidationErrors(array $errors)
    {
        $formatted = [];

        foreach ($errors as $field => $messages) {
            $formatted[$field] = array_map(function ($message) use ($field) {
                return $this->transformValidationMessage($field, $message);
            }, $messages);
        }

        return $formatted;
    }

    protected function transformValidationMessage($field, $message)
    {
        $label = $this->attributeLabel($field);
        $messageLower = strtolower($message);

        if (strpos($messageLower, 'required') !== false) {
            return $label.' wajib diisi.';
        }

        if (strpos($messageLower, 'selected') !== false && strpos($messageLower, 'invalid') !== false) {
            return $label.' tidak ditemukan atau tidak valid.';
        }

        if (strpos($messageLower, 'already been taken') !== false) {
            return $label.' sudah terdaftar, tidak boleh duplikat.';
        }

        if (strpos($messageLower, 'must be distinct') !== false) {
            return $label.' tidak boleh duplikat dalam satu input.';
        }

        if (strpos($messageLower, 'must be an array') !== false) {
            return $label.' harus berupa array.';
        }

        if (strpos($messageLower, 'must be a string') !== false) {
            return $label.' harus berupa teks.';
        }

        if (strpos($messageLower, 'must be a number') !== false || strpos($messageLower, 'must be numeric') !== false) {
            return $label.' harus berupa angka.';
        }

        if (strpos($messageLower, 'greater than or equal to 0') !== false) {
            return $label.' tidak boleh kurang dari 0.';
        }

        if (strpos($messageLower, 'must be at least') !== false) {
            return $label.' belum memenuhi nilai minimum.';
        }

        if (strpos($messageLower, 'may not be greater than') !== false) {
            return $label.' melebihi batas maksimum.';
        }

        if (strpos($messageLower, 'is not a valid date') !== false) {
            return $label.' harus berupa tanggal yang valid.';
        }

        return $message;
    }

    protected function duplicateOrConstraintMessage($message)
    {
        $messageLower = strtolower($message);

        if (strpos($messageLower, 'users_email_unique') !== false) {
            return 'Email sudah terdaftar, tidak boleh duplikat.';
        }

        if (strpos($messageLower, 'customer_groups_group_code_unique') !== false) {
            return 'Kode customer group sudah terdaftar, tidak boleh duplikat.';
        }

        if (strpos($messageLower, 'customers_customer_code_unique') !== false) {
            return 'Kode customer sudah terdaftar, tidak boleh duplikat.';
        }

        if (strpos($messageLower, 'products_product_code_unique') !== false) {
            return 'Kode produk sudah terdaftar, tidak boleh duplikat.';
        }

        if (strpos($messageLower, 'product_prices_product_id_customer_group_id_unique') !== false) {
            return 'Harga produk untuk customer group tersebut sudah ada.';
        }

        if (strpos($messageLower, 'payment_methods_method_code_unique') !== false) {
            return 'Kode metode pembayaran sudah terdaftar, tidak boleh duplikat.';
        }

        if (strpos($messageLower, 'app_settings_setting_key_unique') !== false) {
            return 'Setting key sudah terdaftar, tidak boleh duplikat.';
        }

        if (strpos($messageLower, 'sales_h_invoice_no_unique') !== false) {
            return 'Nomor invoice sudah digunakan. Silakan ulangi transaksi.';
        }

        return 'Data gagal diproses karena duplikat atau melanggar relasi database.';
    }

    protected function modelNotFoundMessage($model)
    {
        $messages = [
            'Customer' => 'Customer tidak ditemukan.',
            'CustomerGroup' => 'Customer group tidak ditemukan.',
            'PaymentMethod' => 'Metode pembayaran tidak ditemukan.',
            'Product' => 'Produk tidak ditemukan.',
            'SalesHeader' => 'Transaksi penjualan tidak ditemukan.',
            'User' => 'User tidak ditemukan.',
        ];

        return isset($messages[$model]) ? $messages[$model] : 'Data tidak ditemukan.';
    }

    protected function attributeLabel($field)
    {
        $normalized = preg_replace('/\.\d+\./', '.*.', $field);

        $labels = [
            'email' => 'Email',
            'password' => 'Password',
            'device_id' => 'Device ID',
            'device_name' => 'Device name',
            'group_code' => 'Kode customer group',
            'group_name' => 'Nama customer group',
            'customer_code' => 'Kode customer',
            'customer_name' => 'Nama customer',
            'customer_group_id' => 'Customer group',
            'phone' => 'Nomor telepon',
            'address' => 'Alamat',
            'product_code' => 'Kode produk',
            'product_name' => 'Nama produk',
            'unit' => 'Satuan',
            'cost_price' => 'Harga modal',
            'stock' => 'Stok',
            'prices' => 'Daftar harga produk',
            'prices.*.customer_group_id' => 'Customer group pada harga produk',
            'prices.*.selling_price' => 'Harga jual',
            'method_code' => 'Kode metode pembayaran',
            'method_name' => 'Nama metode pembayaran',
            'settings' => 'App settings',
            'settings.*.setting_key' => 'Setting key',
            'settings.*.setting_value' => 'Setting value',
            'customer_id' => 'Customer',
            'payment_method_id' => 'Metode pembayaran',
            'discount' => 'Diskon',
            'paid_amount' => 'Nominal bayar',
            'items' => 'Item penjualan',
            'items.*.product_id' => 'Produk pada item penjualan',
            'items.*.qty' => 'Qty item penjualan',
            'void_reason' => 'Alasan void',
            'product_id' => 'Produk',
            'new_stock' => 'Stok baru',
            'note' => 'Catatan',
            'mutation_date' => 'Tanggal mutasi',
        ];

        return isset($labels[$normalized]) ? $labels[$normalized] : ucfirst(str_replace('_', ' ', $field));
    }
}
