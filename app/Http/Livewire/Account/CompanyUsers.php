<?php

namespace App\Http\Livewire\Account;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Client cabinet: users of the company, shown as a section of the company page. The owner adds and manages them; everyone sees the list. */
#[Layout('layouts.app')]
class CompanyUsers extends Component
{
    public bool $embedded = false;   // true: a section of the company page (no page title)
    public bool $showForm = false;
    public ?int $editingId = null;
    public array $form = [];

    private function members()
    {
        return User::where('company_id', auth()->user()->company_id)->orderByRaw("company_role = 'owner' desc")->orderBy('name');
    }

    private function member(int $id): User
    {
        $user = $this->members()->findOrFail($id);
        abort_unless(auth()->user()->isCompanyOwner(), 403);

        return $user;
    }

    public function openCreate(): void
    {
        abort_unless(auth()->user()->isCompanyOwner(), 403);
        $this->resetValidation();
        $this->editingId = null;
        $this->form = ['name' => '', 'email' => '', 'phone' => '', 'password' => Str::password(10, symbols: false), 'locale' => auth()->user()->locale ?: 'uz'];
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $user = $this->member($id);
        $this->resetValidation();
        $this->editingId = $id;
        $this->form = $user->only(['name', 'email', 'phone', 'locale']) + ['password' => ''];
        $this->showForm = true;
    }

    public function save(): void
    {
        abort_unless(auth()->user()->isCompanyOwner(), 403);
        $editing = $this->editingId ? $this->member($this->editingId) : null;

        $data = $this->validate([
            'form.name'     => 'required|string|max:255',
            'form.email'    => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($editing?->id)],
            'form.phone'    => ['required', 'string', 'max:30', 'regex:/^\+?[\d\s\-()]{7,}$/'],
            'form.locale'   => 'required|in:uz,ru,zh',
            'form.password' => [$editing ? 'nullable' : 'required', Password::min(8)],
        ])['form'];

        $values = ['name' => $data['name'], 'email' => strtolower($data['email']), 'phone' => $data['phone'], 'locale' => $data['locale']];
        if (filled($data['password'])) {
            $values['password'] = Hash::make($data['password']);
        }

        if ($editing) {
            $editing->update($values);
        } else {
            User::create($values + [
                'company_id' => auth()->user()->company_id, 'company_role' => 'member',
                'role' => 'user', 'is_active' => true, 'qr_token' => Str::random(32),
            ]);
        }

        $this->showForm = false;
        $this->dispatch('notify', type: 'success', message: $editing ? __('common.saved') : __('company_users.created'));
    }

    public function toggleActive(int $id): void
    {
        $user = $this->member($id);
        abort_if($user->id === auth()->id(), 422);
        $user->update(['is_active' => !$user->is_active]);
    }

    public function remove(int $id): void
    {
        $user = $this->member($id);
        abort_if($user->id === auth()->id() || $user->company_role === 'owner', 422);
        // the person leaves the company; batches and codes stay with the company
        $user->update(['company_id' => null, 'company_role' => null, 'is_active' => false, 'telegram_chat_id' => null]);
        $this->dispatch('notify', type: 'success', message: __('common.deleted'));
    }

    public function render()
    {
        return view('livewire.account.company-users', [
            'users'   => $this->members()->get(),
            'isOwner' => auth()->user()->isCompanyOwner(),
            'company' => auth()->user()->company,
        ]);
    }
}
