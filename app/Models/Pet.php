<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pet extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'owner_id',
        'name',
        'species',
        'breed',
        'age',
        'size',
        'gender',
        'description',
        'image_url',
        'views',
        'status',
        'is_approved',
        'adoption_date',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_approved' => 'boolean',
            'adoption_date' => 'datetime',
        ];
    }

    /**
     * Get the owner of the pet.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get the images for the pet.
     */
    public function images(): HasMany
    {
        return $this->hasMany(PetImage::class);
    }

    /**
     * Get the lost pet reports associated with this pet.
     */
    public function lostPetReports(): HasMany
    {
        return $this->hasMany(LostPetReport::class);
    }
}
