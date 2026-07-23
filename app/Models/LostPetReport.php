<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LostPetReport extends Model
{
    use HasFactory, HasUuids;

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = ['is_exact_location'];

    /**
     * Default value for the is_exact_location virtual attribute.
     * Overridden at runtime by the controller's applyPrivacy method.
     */
    public function getIsExactLocationAttribute(): bool
    {
        return $this->attributes['is_exact_location'] ?? false;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'pet_id',
        'pet_name',
        'species',
        'breed',
        'color',
        'description',
        'photo_url',
        'status',
        'is_found',
        'latitude',
        'longitude',
        'location_description',
        'contact_phone',
        'contact_email',
        'radius_km',
        'lost_at',
        'found_at',
        'has_reward',
        'reward_amount',
        'reward_description',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_found' => 'boolean',
            'has_reward' => 'boolean',
            'reward_amount' => 'decimal:2',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'radius_km' => 'integer',
            'lost_at' => 'datetime',
            'found_at' => 'datetime',
        ];
    }

    /**
     * Get the user that filed the report.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the pet associated with the report (optional).
     */
    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    /**
     * Get the photos for the report.
     */
    public function photos(): HasMany
    {
        return $this->hasMany(LostPetReportPhoto::class, 'report_id');
    }

    /**
     * Get the status updates for the report.
     */
    public function updates(): HasMany
    {
        return $this->hasMany(LostPetReportUpdate::class, 'report_id');
    }

    /**
     * Get the alerts for the report.
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(PetAlert::class, 'report_id');
    }
}
