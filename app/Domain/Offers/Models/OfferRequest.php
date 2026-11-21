<?php

namespace App\Domain\Offers\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class OfferRequest extends Model
{
    protected $fillable = [
        'offer_id', 'label_template_id', 'item_title', 'category', 'user_id',
        'name', 'phone', 'quantity', 'comment', 'locale', 'status',
    ];

    public function offer()
    {
        return $this->belongsTo(Offer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
