<?php

namespace App\Domain\Catalog\Models;

use Illuminate\Database\Eloquent\Model;

/** A company's own name for a shared product (see the migration). */
class CompanyProductName extends Model
{
    protected $fillable = ['company_id', 'product_id', 'name_uz', 'name_ru', 'sku', 'named_by'];
}
