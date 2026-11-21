<?php

namespace App\Http\Livewire\Account;

use App\Http\Controllers\HelpController;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Illustrated guides for the client pages, in the user's language. */
#[Layout('layouts.app')]
class HelpPage extends Component
{
    public string $page = 'import';

    public function mount(?string $page = null): void
    {
        $this->page = $page ?? 'import';
        abort_unless(in_array($this->page, HelpController::PAGES, true), 404);
    }

    public function render()
    {
        return view('livewire.account.help-page', [
            'html'  => HelpController::render($this->page),
            'pages' => collect(HelpController::PAGES)->mapWithKeys(fn ($p) => [$p => HelpController::title($p)])->filter(),
        ]);
    }
}
