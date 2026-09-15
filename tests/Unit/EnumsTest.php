<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\CustomerType;
use App\Enums\OrderStatus;
use App\Enums\PageFilterType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** The enums, the legacy label arrays and the database enums must not drift apart. */
class EnumsTest extends TestCase
{
    public function test_order_enums_match_the_legacy_label_arrays(): void
    {
        $this->assertSame(Order::$status_names, OrderStatus::options());
        $this->assertSame(Order::$payment_status_names, PaymentStatus::options());
        $this->assertSame(Order::$payment_method_names, PaymentMethod::options());
        $this->assertSame(Customer::CUSTOMER_TYPES, array_keys(CustomerType::options()));
    }

    public function test_enums_match_the_database_columns(): void
    {
        // Order of the database enums is historical (values were appended), so compare as sets.
        $this->assertEqualsCanonicalizing(array_column(OrderStatus::cases(), 'value'), $this->enumValues('orders', 'status'));
        $this->assertEqualsCanonicalizing(array_column(PaymentStatus::cases(), 'value'), $this->enumValues('orders', 'payment_status'));
        $this->assertEqualsCanonicalizing(array_column(PaymentMethod::cases(), 'value'), $this->enumValues('orders', 'payment_method'));
        $this->assertEqualsCanonicalizing(array_column(CustomerType::cases(), 'value'), $this->enumValues('customers', 'customer_type'));
        $this->assertEqualsCanonicalizing(array_column(PageFilterType::cases(), 'value'), $this->enumValues('pages_contents', 'filter_type'));
    }

    /** @return list<string> */
    private function enumValues(string $table, string $column): array
    {
        $type = (string) DB::table('information_schema.columns')
            ->where('table_schema', DB::getDatabaseName())->where('table_name', $table)->where('column_name', $column)
            ->value('COLUMN_TYPE');
        preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $type, $matches);

        return array_map(fn (string $v) => str_replace("\\'", "'", $v), $matches[1]);
    }
}
