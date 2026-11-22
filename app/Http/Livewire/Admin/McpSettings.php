<?php

namespace App\Http\Livewire\Admin;

use App\Support\McpToken;
use Livewire\Component;

/** Super admin: token for the content MCP server — create (shown once), replace, switch off. */
class McpSettings extends Component
{
    public ?string $newToken = null;

    public function generate(): void
    {
        $this->newToken = McpToken::create();
        \App\Domain\Reporting\AuditLog::record('mcp.token_created', 'setting', 0);
    }

    public function revoke(): void
    {
        McpToken::revoke();
        $this->newToken = null;
        \App\Domain\Reporting\AuditLog::record('mcp.token_revoked', 'setting', 0);
        $this->dispatch('notify', type: 'success', message: __('common.saved'));
    }

    public function render()
    {
        return view('livewire.admin.mcp-settings', ['exists' => McpToken::exists(), 'createdAt' => McpToken::createdAt(), 'endpoint' => url('/mcp/content')]);
    }
}
