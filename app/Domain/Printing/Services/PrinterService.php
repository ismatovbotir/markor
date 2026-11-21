<?php

namespace App\Domain\Printing\Services;

use App\Domain\Printing\Models\Printer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PrinterService
{
    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        return Printer::latest()->paginate($perPage);
    }

    public function store(array $data): Printer
    {
        return Printer::create($data);
    }

    public function update(Printer $printer, array $data): Printer
    {
        $printer->update($data);
        return $printer->fresh();
    }

    public function destroy(Printer $printer): void
    {
        $printer->delete();
    }
}
