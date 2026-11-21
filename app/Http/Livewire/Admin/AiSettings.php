<?php

namespace App\Http\Livewire\Admin;

use App\Domain\Ai\AiUsage;
use App\Domain\Ai\Claude;
use Livewire\Component;

/** Sozlamalar block: Claude status, model per task, this month's usage and cost, resume after a pause. */
class AiSettings extends Component
{
    /** task => level (haiku / sonnet / opus) */
    public array $levels = [];

    public function mount(\App\Domain\Ai\ModelResolver $resolver): void
    {
        foreach (array_keys(config('ai.levels')) as $task) {
            $this->levels[$task] = $resolver->level($task);
        }
    }

    public function updatedLevels($value, $task): void
    {
        abort_unless(array_key_exists($task, config('ai.levels')) && in_array($value, config('ai.level_options'), true), 422);
        \App\Domain\Settings\Setting::put(["ai.level.$task" => $value]);
        $this->dispatch('notify', type: 'success', message: __('common.saved'));
    }

    /** Ask the Models API again (e.g. right after a new Claude version is released). */
    public function refreshModels(\App\Domain\Ai\ModelResolver $resolver): void
    {
        $resolver->refresh();
        $this->dispatch('notify', type: 'success', message: __('ai.models_refreshed'));
    }

    public function resume(Claude $claude): void
    {
        $claude->resume();
        $this->dispatch('notify', type: 'success', message: __('ai.resumed'));
    }

    public function render(Claude $claude)
    {
        $month = AiUsage::where('created_at', '>=', now()->startOfMonth());

        return view('livewire.admin.ai-settings', [
            'reason'   => $claude->unavailableReason(),
            'until'    => $claude->pausedUntil(),
            'models'   => collect(config('ai.levels'))->keys()->mapWithKeys(fn ($task) => [$task => app(\App\Domain\Ai\ModelResolver::class)->forTask($task)])->all(),
            'pinned'   => array_filter(config('ai.pinned', [])),
            'cost'     => (clone $month)->sum('cost_usd'),
            'calls'    => (clone $month)->count(),
            'failed'   => (clone $month)->whereNotIn('status', ['ok'])->count(),
            'byFeature'=> (clone $month)->selectRaw('feature, count(*) as calls, sum(cost_usd) as cost')->groupBy('feature')->get(),
            'limit'    => (float) config('ai.monthly_limit_usd'),
            'lastError'=> AiUsage::whereNotIn('status', ['ok'])->latest('id')->first(),
        ]);
    }
}
