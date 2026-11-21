<?php

namespace App\Http\Livewire\Aggregation;

use App\Domain\Aggregation\Models\Aggregation;
use App\Domain\Aggregation\Services\AggregationService;
use App\Domain\Marking\Services\MarkingCodeService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class AggregationScanner extends Component
{
    public ?int $aggregationId = null;
    public string $rawScan = '';
    public array $addedCodes = [];
    public ?string $lastMessage = null;
    public string $messageType = 'info'; // info / success / error

    public function updatedRawScan(string $value): void
    {
        if (!str_ends_with($value, "\n")) {
            return;
        }

        $this->rawScan = '';
        $raw = trim($value);

        if (empty($raw) || !$this->aggregationId) {
            return;
        }

        try {
            $aggregation = Aggregation::findOrFail($this->aggregationId);
            $this->authorize('update', $aggregation);

            $service = app(AggregationService::class);
            $code = $service->addCode($aggregation, $raw);

            $this->addedCodes[] = [
                'code'   => $code->code,
                'gtin'   => $code->gtin,
                'serial' => $code->serial,
                'status' => $code->status,
            ];

            $this->lastMessage = __('aggregation.code_added', ['code' => $code->code]);
            $this->messageType = 'success';

            if ($aggregation->fresh()->isFull()) {
                $this->lastMessage = __('aggregation.full');
            }

        } catch (\Throwable $e) {
            $this->lastMessage = $e->getMessage();
            $this->messageType = 'error';
        }
    }

    public function selectAggregation(int $id): void
    {
        $aggregation = Aggregation::findOrFail($id);
        $this->authorize('update', $aggregation);

        if ($aggregation->status !== 'open') {
            $this->lastMessage = __('aggregation.not_open');
            $this->messageType = 'error';
            return;
        }

        $this->aggregationId = $id;
        $this->addedCodes = [];
        $this->lastMessage = __('aggregation.ready_to_scan', ['sscc' => $aggregation->sscc]);
        $this->messageType = 'info';
    }

    public function render(AggregationService $service)
    {
        return view('livewire.aggregation.aggregation-scanner', [
            'openAggregations' => $service->paginate(),
            'current'          => $this->aggregationId ? Aggregation::find($this->aggregationId) : null,
        ]);
    }
}
