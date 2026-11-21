<?php

namespace App\Policies;

use App\Domain\Printing\Models\LabelTemplate;
use App\Models\User;

/**
 * Templates are made by the (super) admin; clients only pick one when printing.
 * The built-in default template is read-only for everyone: it can only be copied.
 */
class LabelTemplatePolicy
{
    public function viewAny(User $user): bool { return $user->isAdmin(); }
    public function view(User $user, LabelTemplate $model): bool { return true; } // clients preview templates
    public function create(User $user): bool { return $user->isAdmin(); }
    public function update(User $user, LabelTemplate $model): bool { return $user->isAdmin() && !$model->is_default; }
    public function delete(User $user, LabelTemplate $model): bool { return $user->isAdmin() && !$model->is_default; }
}
