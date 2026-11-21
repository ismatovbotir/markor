<?php

namespace App\Domain\Marking\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class UserService
{
    public function paginate(string $search = '', int $perPage = 20): LengthAwarePaginator
    {
        return User::query()
            ->when($search, fn ($q) => $q->where('name', 'like', "%$search%")
                ->orWhere('email', 'like', "%$search%"))
            ->latest()
            ->paginate($perPage);
    }

    public function store(array $data): User
    {
        return User::create($data);
    }

    public function update(User $user, array $data): User
    {
        $user->update($data);
        return $user->fresh();
    }

    public function regenerateQrToken(User $user): User
    {
        $user->update(['qr_token' => Str::random(32)]);
        return $user->fresh();
    }

    public function destroy(User $user): void
    {
        $user->delete();
    }
}
