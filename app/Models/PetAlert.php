<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PetAlert extends Model
{
    use HasFactory, HasUuids;

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'report_id',
        'user_id',
        'alert_latitude',
        'alert_longitude',
        'radius_km',
        'is_read',
        'is_sent',
        'sent_at',
        'created_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'is_sent' => 'boolean',
            'sent_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Get the report this alert belongs to.
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(LostPetReport::class, 'report_id');
    }

    /**
     * Get the user this alert is for.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
