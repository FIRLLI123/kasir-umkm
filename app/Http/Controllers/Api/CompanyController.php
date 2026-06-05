<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\CompanyProvisioningService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CompanyController extends Controller
{
    use ApiResponse;

    protected $companyProvisioningService;

    public function __construct(CompanyProvisioningService $companyProvisioningService)
    {
        $this->companyProvisioningService = $companyProvisioningService;
    }

    public function index()
    {
        $this->ensureSuperAdmin();

        return $this->successResponse(
            Company::query()->orderBy('company_name')->get(),
            'Daftar company berhasil diambil'
        );
    }

    public function store(Request $request)
    {
        $this->ensureSuperAdmin();

        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'company_code' => 'required|string|max:100|unique:companies,company_code',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'logo' => 'nullable|string|max:255',
            'status' => 'nullable|in:0,1',
        ]);

        $company = DB::transaction(function () use ($validated) {
            $company = Company::create([
                'company_name' => $validated['company_name'],
                'company_code' => strtoupper($validated['company_code']),
                'address' => $validated['address'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'logo' => $validated['logo'] ?? null,
                'status' => isset($validated['status']) ? (int) $validated['status'] : 1,
                'subscription_status' => 'active',
                'subscription_starts_at' => now(),
                'activated_at' => now(),
            ]);

            $this->companyProvisioningService->seedDefaultCompanyData($company);

            return $company;
        });

        return $this->successResponse($company, 'Company berhasil dibuat', 201);
    }

    public function update(Request $request, $id)
    {
        $this->ensureSuperAdmin();
        $company = Company::findOrFail($id);

        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'company_code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('companies', 'company_code')->ignore($company->id),
            ],
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'logo' => 'nullable|string|max:255',
            'status' => 'nullable|in:0,1',
        ]);

        $company->update([
            'company_name' => $validated['company_name'],
            'company_code' => strtoupper($validated['company_code']),
            'address' => $validated['address'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'logo' => $validated['logo'] ?? null,
            'status' => isset($validated['status']) ? (int) $validated['status'] : $company->status,
        ]);

        return $this->successResponse($company->fresh(), 'Company berhasil diupdate');
    }

    public function destroy($id)
    {
        $this->ensureSuperAdmin();
        $company = Company::findOrFail($id);
        $company->update(['status' => 0]);

        return $this->successResponse($company->fresh(), 'Company berhasil dinonaktifkan');
    }

    protected function ensureSuperAdmin()
    {
        abort_unless(auth()->check() && auth()->user()->isSuperAdmin(), 403, 'Akses hanya untuk super admin.');
    }
}
