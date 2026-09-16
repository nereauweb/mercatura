<?php

declare(strict_types=1);

namespace App\Support\Checkout;

use App\Models\User;

/** Who may complete an order: customers with a customer profile, never admins. */
final class CheckoutGuard
{
    public static function blockCode(User $user): ?string
    {
        if ($user->hasRole('admin')) {
            return 'admin';
        }
        if (! $user->hasRole('customer')) {
            return 'role_not_customer';
        }
        if (! $user->customer) {
            return 'customer_missing';
        }

        return null;
    }

    public static function blockMessage(string $blockCode): string
    {
        return $blockCode === 'customer_missing'
            ? __('frontend.checkout.blocked_customer_missing')
            : __('frontend.checkout.blocked_admin');
    }
}
