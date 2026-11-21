<?php

namespace App\Http\Livewire\Settings;

use App\Domain\Marking\Services\UserService;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class UserIndex extends Component
{
    use WithPagination;

    public string $search  = '';
    public bool $showForm  = false;
    public ?int $editingId = null;

    public string $name     = '';
    public string $email    = '';
    public string $password = '';
    public string $role     = 'user';
    public string $locale   = 'ru';
    public string $phone    = '';
    public bool $is_active  = true;

    protected function rules(): array
    {
        $unique = $this->editingId ? ",{$this->editingId}" : '';
        return [
            'name'      => 'required|string|max:255',
            'email'     => "required|email|unique:users,email{$unique}",
            'password'  => ($this->editingId ? 'nullable' : 'required') . '|string|min:8',
            'role'      => 'required|in:admin,user',
            'locale'    => 'required|in:uz,ru,zh',
            'phone'     => 'nullable|string|max:20',
            'is_active' => 'boolean',
        ];
    }

    public function updatingSearch(): void { $this->resetPage(); }

    public function openCreate(): void
    {
        $this->reset(['name', 'email', 'password', 'phone', 'editingId']);
        $this->role      = 'user';
        $this->locale    = 'ru';
        $this->is_active = true;
        $this->showForm  = true;
    }

    public function openEdit(int $id): void
    {
        $u = User::findOrFail($id);
        $this->fill($u->only(['name', 'email', 'role', 'locale', 'phone', 'is_active']));
        $this->editingId = $id;
        $this->showForm  = true;
    }

    public function save(UserService $service): void
    {
        $this->authorize($this->editingId ? 'update' : 'create', User::class);
        $data = $this->validate();
        if (empty($data['password'])) unset($data['password']);

        if ($this->editingId) {
            $service->update(User::findOrFail($this->editingId), $data);
        } else {
            $service->store($data);
        }

        $this->showForm = false;
        $this->dispatch('notify', type: 'success', message: __('common.saved'));
    }

    public function regenerateQr(int $id, UserService $service): void
    {
        $this->authorize('update', User::findOrFail($id));
        $service->regenerateQrToken(User::findOrFail($id));
        $this->dispatch('notify', type: 'success', message: __('users.qr_regenerated'));
    }

    public function delete(int $id, UserService $service): void
    {
        $u = User::findOrFail($id);
        $this->authorize('delete', $u);
        $service->destroy($u);
        $this->dispatch('notify', type: 'success', message: __('common.deleted'));
    }

    public function render(UserService $service)
    {
        return view('livewire.settings.user-index', [
            'users' => $service->paginate($this->search),
        ]);
    }
}
