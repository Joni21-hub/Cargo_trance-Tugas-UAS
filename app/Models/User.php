<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

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
        'phone',
        'company_name',
        'address',
        'city',
        'country',
        'identity_number',
        'bio',
        'is_verified',
        'verified_at',
        'password',
        'role',
        'location_id',
        'wallet_balance_usd',
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
            'verified_at' => 'datetime',
            'is_verified' => 'boolean',
            'password' => 'hashed',
            'wallet_balance_usd' => 'float',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isOperator(): bool
    {
        return $this->role === 'operator';
    }

    public function isCustomer(): bool
    {
        return $this->role === 'customer';
    }

    public function isSurveyor(): bool
    {
        return $this->role === 'surveyor';
    }

    public function shipments()
    {
        return $this->hasMany(Shipment::class, 'customer_id');
    }

    public function branchLocation()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function appNotifications()
    {
        return $this->hasMany(AppNotification::class, 'user_id')->latest();
    }

    public function walletMutations()
    {
        return $this->hasMany(WalletMutation::class, 'user_id')->latest();
    }

    public function exportProducts()
    {
        return $this->hasMany(ExportProduct::class, 'shipper_id');
    }

    public function pickupRequests()
    {
        return $this->hasMany(PickupRequest::class, 'shipper_id')->latest();
    }

    public function supportTickets()
    {
        return $this->hasMany(SupportTicket::class, 'user_id')->latest();
    }
}
