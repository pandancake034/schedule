<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany; // <--- Toevoegen

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'contract_days',  // <--- Nieuw
        'fixed_days', // <--- Toevoegen
        'contract_hours', // <--- Nieuw
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'fixed_days' => 'array', // <--- Zorgt dat het automatisch een array wordt
        ];
    }

    // ==========================================
    // NIEUWE RELATIES (TOEVOEGEN)
    // ==========================================

    /**
     * Relatie: Een gebruiker heeft meerdere beschikbaarheden (dagen).
     */
    public function availability(): HasMany
    {
        return $this->hasMany(Availability::class);
    }

    /**
     * Relatie: Een gebruiker heeft meerdere ingeplande diensten.
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }
}