<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Contracts\TransactionalMailer;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Billable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use Billable, HasApiTokens, HasFactory, HasRoles, Notifiable;

    /** Admin panel access (app/Providers/Filament/AdminPanelProvider): the admin role only. */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasRole('admin');
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class, 'user_id', 'id');
    }

    public function getCustomerAttribute()
    {
        // Controlla se la relazione è già caricata
        if ($this->relationLoaded('customer')) {
            return $this->getRelation('customer');
        }

        // Carica la relazione SENZA chiamare l'accessor
        $customer = $this->getRelationValue('customer');

        // Se non esiste, creala
        if (! $customer) {
            $customer = $this->customer()->create([
                'user_id' => $this->id,
            ]);
            $this->setRelation('customer', $customer);
        }

        return $customer;
    }

    public function sendPasswordResetNotification($token)
    {
        Log::info('User::sendPasswordResetNotification called', [
            'user_id' => $this->id,
            'email' => $this->email,
            'token' => substr($token, 0, 8).'...',
            'provider' => config('services.mail_provider'),
        ]);

        $this->sendPasswordResetMail($token);
    }

    protected function sendPasswordResetMail($token)
    {
        $resetUrl = route('password.reset', [
            'token' => $token,
            'email' => $this->email,
        ]);

        $mailer = app(TransactionalMailer::class);
        $mailer->attribute('reset_link', $resetUrl);
        $mailer->to($this->email);
        $mailer->send('password_reset');
        $mailer->reset();
    }
}
