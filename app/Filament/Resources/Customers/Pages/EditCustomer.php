<?php

declare(strict_types=1);

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Models\Customer;
use App\Models\CustomerAddress;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

/** Edits the profile and its two addresses (customer_addresses rows linked by billing/shipping_address_id). */
final class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [ViewAction::make()];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Customer $customer */
        $customer = $this->getRecord();
        $data['billing'] = $customer->billing_address?->only(['address', 'city', 'province', 'zip_code', 'country', 'notes']) ?? [];
        $data['shipping'] = $customer->shipping_address?->only(['address', 'city', 'province', 'zip_code', 'country', 'notes']) ?? [];

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Customer $record */
        $billing = (array) ($data['billing'] ?? []);
        $shipping = (array) ($data['shipping'] ?? []);
        unset($data['billing'], $data['shipping']);

        $record->fill($data);
        $billingAddress = $this->saveAddress($record->billing_address, $billing);
        $shippingAddress = $this->saveAddress($record->shipping_address, $shipping);
        $record->billing_address_id = $billingAddress instanceof CustomerAddress ? $billingAddress->id : $record->billing_address_id;
        $record->shipping_address_id = $shippingAddress instanceof CustomerAddress ? $shippingAddress->id : $record->shipping_address_id;
        $record->save();

        return $record;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function saveAddress(mixed $existing, array $data): ?CustomerAddress
    {
        $filled = array_filter($data, fn ($v) => $v !== null && $v !== '');
        if ($existing instanceof CustomerAddress) {
            $existing->fill($data)->save();

            return $existing;
        }
        if ($filled === []) {
            return null;
        }

        return CustomerAddress::query()->create($data);
    }

    protected function getSavedNotificationTitle(): string
    {
        return __('admin.customer.updated');
    }
}
