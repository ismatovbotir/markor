<?php

namespace App\Domain\Printing\Models;

use Illuminate\Database\Eloquent\Model;

class TemplateRequest extends Model
{
    protected $fillable = ['company_id', 'user_id', 'width_mm', 'height_mm', 'columns', 'rows', 'gap_mm', 'comment', 'status', 'admin_note', 'template_id', 'decided_at'];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime', 'gap_mm' => 'float'];
    }

    public function company() { return $this->belongsTo(\App\Domain\Companies\Company::class); }
    public function user() { return $this->belongsTo(\App\Models\User::class); }
    public function template() { return $this->belongsTo(LabelTemplate::class); }

    public function size(): string
    {
        return $this->width_mm . '×' . $this->height_mm . ' mm' . ($this->columns > 1 ? " · {$this->columns}" . __('templates.per_row_short') : '') . ($this->rows ? " · {$this->rows} " . __('template_requests.rows_short') : '');
    }
}
