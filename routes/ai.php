<?php

use App\Http\Middleware\VerifyMcpToken;
use App\Mcp\Servers\ContentServer;
use Laravel\Mcp\Facades\Mcp;

/*
 * MCP servers for Claude clients (Claude Code / Claude Desktop).
 *   /mcp/content — manage site news and marketplace products; header "Authorization: Bearer <token>" (token: Admin → Settings → MCP)
 */
Mcp::web('/mcp/content', ContentServer::class)->middleware([VerifyMcpToken::class, 'throttle:60,1']);
