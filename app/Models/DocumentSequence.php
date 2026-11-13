<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Compteur d'une série de références (un préfixe, une année). */
class DocumentSequence extends Model
{
    public $timestamps = false;

    protected $fillable = ['prefix', 'year', 'last_number'];

    protected function casts(): array
    {
        return ['year' => 'integer', 'last_number' => 'integer'];
    }
}
