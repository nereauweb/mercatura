<?php

declare(strict_types=1);

namespace App\Http\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Base for the customer's own lists (orders, quotations, messages): a
 * sortable, paginated table of the current user's records.
 */
abstract class FrontendCustomerTable extends Component
{
    use WithPagination;

    #[Url(as: 'sort', except: 'created_at')]
    public string $sortBy = 'created_at';

    #[Url(as: 'dir', except: 'desc')]
    public string $sortDirection = 'desc';

    /** @return Builder<covariant \Illuminate\Database\Eloquent\Model> */
    abstract protected function query(): Builder;

    /** @return array<string, string> column => label */
    abstract protected function columns(): array;

    abstract protected function view(): string;

    /** Route for a row, or null when rows are not links. */
    protected function rowUrl(object $row): ?string
    {
        return null;
    }

    /** @return array<string, string> */
    protected function formatRow(object $row): array
    {
        $cells = [];
        foreach (array_keys($this->columns()) as $column) {
            $cells[$column] = (string) ($row->{$column} ?? '');
        }

        return $cells;
    }

    public function sort(string $column): void
    {
        if (! array_key_exists($column, $this->columns())) {
            return;
        }
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
        $this->resetPage();
    }

    public function render(): View
    {
        $sortBy = array_key_exists($this->sortBy, $this->columns()) ? $this->sortBy : 'created_at';
        $direction = $this->sortDirection === 'asc' ? 'asc' : 'desc';
        $rows = $this->query()->orderBy($sortBy, $direction)->paginate(20);

        return view($this->view(), [
            'rows' => $rows,
            'columns' => $this->columns(),
            'cells' => $rows->getCollection()->map(fn ($row) => ['url' => $this->rowUrl($row), 'cells' => $this->formatRow($row)])->all(),
            'sortBy' => $sortBy,
            'sortDirection' => $direction,
        ]);
    }
}
