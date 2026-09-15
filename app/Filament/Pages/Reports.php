<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Widgets\ContactsByMonthChart;
use App\Filament\Widgets\OrdersByMonthChart;
use App\Filament\Widgets\OrdersByStatusChart;
use App\Filament\Widgets\TopProductsWidget;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/** The three legacy report entries (traffic, contacts, sales) had no queries; these widgets do. */
final class Reports extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.reports';

    public static function getNavigationGroup(): string
    {
        return __('admin.nav.system');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.reports.title');
    }

    public function getTitle(): string
    {
        return __('admin.reports.title');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('orders.manage') ?? false;
    }

    /** @return array<class-string> */
    protected function getHeaderWidgets(): array
    {
        return [OrdersByMonthChart::class, OrdersByStatusChart::class, ContactsByMonthChart::class, TopProductsWidget::class];
    }
}
