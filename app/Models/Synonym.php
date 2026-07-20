<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Synonym extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'type', 'terms', 'from_term', 'to_term',
        'is_active', 'created_by',
    ];

    protected $casts = [
        'terms' => 'array',
        'is_active' => 'boolean',
    ];

    public function toSynonymLine(): string
    {
        if ($this->type === 'one_way') {
            return "{$this->from_term} => {$this->to_term}";
        }
        return implode(', ', $this->terms);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
