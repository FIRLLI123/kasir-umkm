<?php

namespace App\Models\Concerns;

use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToCompany
{
    public static function bootBelongsToCompany()
    {
        static::addGlobalScope('company', function (Builder $builder) {
            if (app()->runningInConsole()) {
                return;
            }

            if (auth()->check() && auth()->user()->company_id) {
                $builder->where(
                    $builder->getModel()->getTable().'.company_id',
                    auth()->user()->company_id
                );
            }
        });

        static::creating(function ($model) {
            if (! $model->company_id && auth()->check() && auth()->user()->company_id) {
                $model->company_id = auth()->user()->company_id;
            }
        });
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
