<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContractorType extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function contractors()
    {
        return $this->belongsToMany(Contractor::class, 'contractor_contractortype');
    }
}
