<?php

namespace App\Policies;

use App\Domain\Printing\Models\LabelTemplate;
use App\Models\User;

/**
 * The super admin makes templates for everyone; a client designs its company's own ones.
 *  - a client's template is private to its company; the super admin sees it (with the owner) but
 *    never edits it, and may publish it for everyone only while the client agrees (share_consent);
 *  - a client keeps at most companies.template_limit designs (5 by default); deleting one hides it from
 *    the client, the super admin still sees it and may accept it as global (with consent);
 *  - the built-in default template can be changed by the super admin, never deleted.
 */
class LabelTemplatePolicy
{
    public function viewAny(User $user): bool { return $user->isAdmin() || (bool) $user->company_id; }

    // the global scope already hides other companies' private templates from clients
    public function view(User $user, LabelTemplate $model): bool
    {
        return $user->isAdmin() || $model->isGlobal() || $this->owns($user, $model);
    }

    public function create(User $user): bool { return $user->isAdmin() || (bool) $user->company_id; }

    public function update(User $user, LabelTemplate $model): bool
    {
        return $user->isAdmin() ? $model->company_id === null : $this->owns($user, $model);
    }

    public function delete(User $user, LabelTemplate $model): bool
    {
        if ($model->is_default) {
            return false;
        }

        // a client's delete only hides the design from the client: the super admin may still accept it as global
        return $user->isAdmin() ? $model->company_id === null : $this->owns($user, $model);
    }

    /** Super admin: make a client's template available to everyone — only with the client's consent. */
    public function publish(User $user, LabelTemplate $model): bool
    {
        return $user->isAdmin() && $model->company_id !== null && $model->share_consent;
    }

    private function owns(User $user, LabelTemplate $model): bool
    {
        return $user->company_id && $model->company_id === $user->company_id && $model->client_deleted_at === null;
    }
}
