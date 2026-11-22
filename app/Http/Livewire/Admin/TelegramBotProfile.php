<?php

namespace App\Http\Livewire\Admin;

use App\Domain\Reporting\AuditLog;
use App\Domain\Settings\Setting;
use App\Domain\Telegram\TelegramApi;
use App\Support\AdminI18n;
use Illuminate\Support\Facades\Validator;
use Livewire\Component;

/**
 * The bot's public profile at Telegram (Bot API setMy* / getMy*): name, description, short description and the
 * "/" command menu — for the default language (no language_code, used for every other language) and uz / ru / zh.
 * The form is kept as a draft in settings (survives a reload); "sent" is a snapshot of what Telegram has
 * (last load or last successful send), so the card can say that edits are not sent yet.
 * Picture and username cannot be changed through the Bot API — only in @BotFather.
 */
class TelegramBotProfile extends Component
{
    public const DRAFT_KEY = 'telegram.bot_profile_draft';
    public const SENT_KEY = 'telegram.bot_profile_sent';

    /** Telegram's own limits. */
    public const LIMITS = ['name' => 64, 'description' => 512, 'short' => 120, 'command' => 32, 'command_description' => 256, 'commands' => 100];

    /** Text parts of the profile (commands are a list). */
    public const TEXTS = ['name', 'description', 'short'];

    /** Send menu: option → parts. */
    public const SEND_PARTS = [
        'all'          => ['name', 'description', 'short', 'commands'],
        'name'         => ['name'],
        'descriptions' => ['description', 'short'],
        'commands'     => ['commands'],
    ];

    public array $form = [];
    public array $sent = [];
    public ?string $username = null;
    public ?string $botName = null;
    public int $botId = 0;
    public ?string $savedAt = null;

    /** Load from Telegram waits for "overwrite unsent edits?" */
    public bool $confirmLoad = false;
    /** Send waiting for confirmation: ['option' => …, 'parts' => [...], 'langs' => [...]] */
    public ?array $sendPlan = null;
    /** Per-call results of the last load / send: [['part', 'lang', 'ok', 'error', 'skipped']] */
    public array $results = [];
    public ?string $resultsTitle = null;

    /** Form languages: the default one (key "default", no language_code) and the content languages. */
    public static function langs(): array
    {
        return ['default', ...AdminI18n::locales()];
    }

    public static function emptyForm(): array
    {
        $form = [];
        foreach (static::langs() as $l) {
            foreach (static::TEXTS as $part) {
                $form[$part][$l] = '';
            }
            $form['commands'][$l] = [];
        }

        return $form;
    }

    public function boot(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }

    public function mount(): void
    {
        $this->form = static::normalize(Setting::json(self::DRAFT_KEY), keepEmptyRows: true);
        $this->sent = static::normalize(Setting::json(self::SENT_KEY));
    }

    /** wire:init — bot username and name for the preview. */
    public function init(TelegramApi $api): void
    {
        $me = $api->getMe();
        if ($me['ok']) {
            $this->username = $me['result']['username'] ?? null;
            $this->botName = $me['result']['first_name'] ?? null;
            $this->botId = (int) ($me['result']['id'] ?? 0);
        }
    }

    public function updatedForm($value, ?string $key = null): void
    {
        // commands.uz.0.command → lowercase, no leading "/"
        if ($key && preg_match('/^commands\.([a-z]+)\.(\d+)\.command$/', $key, $m)) {
            $this->form['commands'][$m[1]][(int) $m[2]]['command'] = static::commandName((string) $value);
        }
        $this->resetErrorBag('form.' . $key);
        $this->saveDraft();
    }

    public function addCommand(string $lang): void
    {
        $this->assertLang($lang);
        if (count($this->form['commands'][$lang]) < self::LIMITS['commands']) {
            $this->form['commands'][$lang][] = ['command' => '', 'description' => ''];
            $this->saveDraft();
        }
    }

    public function removeCommand(string $lang, int $i): void
    {
        $this->assertLang($lang);
        unset($this->form['commands'][$lang][$i]);
        $this->form['commands'][$lang] = array_values($this->form['commands'][$lang]);
        $this->resetErrorBag();
        $this->saveDraft();
    }

    public function moveCommand(string $lang, int $i, int $dir): void
    {
        $this->assertLang($lang);
        $rows = $this->form['commands'][$lang];
        $j = $i + ($dir < 0 ? -1 : 1);
        if (isset($rows[$i], $rows[$j])) {
            [$rows[$i], $rows[$j]] = [$rows[$j], $rows[$i]];
            $this->form['commands'][$lang] = $rows;
            $this->resetErrorBag();
            $this->saveDraft();
        }
    }

    /** Same command names as the default language, descriptions to translate. */
    public function copyDefaultCommands(string $lang): void
    {
        $this->assertLang($lang);
        if ($lang !== 'default' && $this->form['commands'][$lang] === []) {
            $this->form['commands'][$lang] = $this->form['commands']['default'];
            $this->saveDraft();
        }
    }

    /** Ready texts (fills only empty fields): what the bot really does, its real commands. */
    public function suggest(): void
    {
        foreach (static::suggestion() as $part => $values) {
            foreach ($values as $l => $value) {
                $empty = $part === 'commands' ? static::rows($this->form['commands'][$l]) === [] : trim($this->form[$part][$l]) === '';
                if ($empty) {
                    $this->form[$part][$l] = $value;
                }
            }
        }
        $this->saveDraft();
    }

    public static function suggestion(): array
    {
        $out = [];
        foreach (static::langs() as $l) {
            $locale = $l === 'default' ? AdminI18n::SOURCE : $l;   // default = the Uzbek text
            $out['name'][$l] = 'Markor';
            $out['description'][$l] = __('tg_profile.suggest.description', [], $locale);
            $out['short'][$l] = __('tg_profile.suggest.short', [], $locale);
            $out['commands'][$l] = [
                ['command' => 'start', 'description' => __('tg_profile.suggest.cmd_start', [], $locale)],
                ['command' => 'status', 'description' => __('tg_profile.suggest.cmd_status', [], $locale)],
            ];
        }

        return $out;
    }

    // ---------- load from Telegram ----------

    public function loadFromTelegram(TelegramApi $api, bool $confirmed = false): void
    {
        if (!$api->enabled()) {
            return;
        }
        if (!$confirmed && $this->unsent()) {
            $this->confirmLoad = true;
            return;
        }
        $this->confirmLoad = false;
        $this->resetErrorBag();

        $results = [];
        foreach (static::langs() as $l) {
            $code = $l === 'default' ? null : $l;
            $calls = [
                'name'        => [$api->getMyName($code), 'name'],
                'description' => [$api->getMyDescription($code), 'description'],
                'short'       => [$api->getMyShortDescription($code), 'short_description'],
                'commands'    => [$api->getMyCommands($code), null],
            ];
            foreach ($calls as $part => [$response, $field]) {
                if ($response['ok']) {
                    $value = $field
                        ? (string) ($response['result'][$field] ?? '')
                        : static::rows(array_map(fn ($c) => ['command' => (string) ($c['command'] ?? ''), 'description' => (string) ($c['description'] ?? '')], (array) $response['result']));
                    $this->form[$part][$l] = $value;
                    $this->sent[$part][$l] = $value;
                }
                $results[] = static::result($part, $l, $response);
            }
        }

        Setting::putRaw(self::SENT_KEY, json_encode($this->sent, JSON_UNESCAPED_UNICODE));
        $this->saveDraft();
        $this->results = $results;
        $this->resultsTitle = __('tg_profile.loaded');
    }

    public function cancelLoad(): void
    {
        $this->confirmLoad = false;
    }

    // ---------- send to Telegram ----------

    /** Step 1: validate and show what will be sent. $scope: all | current; $lang: the current tab. */
    public function prepareSend(string $option, string $scope = 'all', string $lang = 'default'): void
    {
        abort_unless(isset(self::SEND_PARTS[$option]) && in_array($scope, ['all', 'current'], true), 422);
        $this->assertLang($lang);

        // fully empty command rows are dropped, not reported
        foreach (static::langs() as $l) {
            $this->form['commands'][$l] = static::rows($this->form['commands'][$l]);
        }
        $this->saveDraft();

        $langs = $scope === 'current' ? [$lang] : static::langs();
        if (!$this->check($langs, self::SEND_PARTS[$option])) {
            return;
        }

        $this->sendPlan = ['option' => $option, 'parts' => self::SEND_PARTS[$option], 'langs' => $langs];
    }

    public function cancelSend(): void
    {
        $this->sendPlan = null;
    }

    /** Step 2: the calls, one per part × language; a 429 skips the following calls of the same method. */
    public function send(TelegramApi $api): void
    {
        $plan = $this->sendPlan;
        $this->sendPlan = null;
        if (!$plan || !$api->enabled() || !$this->check($plan['langs'], $plan['parts'])) {
            return;
        }

        $results = [];
        $blocked = [];   // part → retry_after
        foreach ($plan['langs'] as $l) {
            $code = $l === 'default' ? null : $l;
            foreach ($plan['parts'] as $part) {
                if (isset($blocked[$part])) {
                    $results[] = ['part' => $part, 'lang' => $l, 'ok' => false, 'skipped' => true,
                        'error' => __('tg_profile.skipped', ['s' => $blocked[$part]])];
                    continue;
                }
                $value = $part === 'commands' ? static::rows($this->form['commands'][$l]) : trim($this->form[$part][$l]);
                $response = match ($part) {
                    'name'        => $api->setMyName($value, $code),
                    'description' => $api->setMyDescription($value, $code),
                    'short'       => $api->setMyShortDescription($value, $code),
                    'commands'    => $api->setMyCommands($value, $code),
                };
                if ($response['ok']) {
                    $this->sent[$part][$l] = $value;
                } elseif ((int) $response['code'] === 429) {
                    $blocked[$part] = (int) ($response['retry_after'] ?? 0);
                }
                $results[] = static::result($part, $l, $response);
            }
        }

        Setting::putRaw(self::SENT_KEY, json_encode($this->sent, JSON_UNESCAPED_UNICODE));
        $this->results = $results;
        $this->resultsTitle = __('tg_profile.sent_title');

        AuditLog::record('telegram.bot_profile.sent', 'telegram_bot', $this->botId, [
            'option' => $plan['option'],
            'langs'  => $plan['langs'],
            'calls'  => array_map(fn ($r) => array_filter(['part' => $r['part'], 'lang' => $r['lang'], 'ok' => $r['ok'], 'error' => $r['error']], fn ($v) => $v !== null), $results),
        ]);
    }

    // ---------- helpers ----------

    /** Validates the given languages × parts; errors go to the bag, the tab with the first error is opened. */
    private function check(array $langs, array $parts): bool
    {
        $rules = $attributes = [];
        $labels = ['name' => __('tg_profile.name'), 'description' => __('tg_profile.description'), 'short' => __('tg_profile.short')];
        foreach ($langs as $l) {
            $tag = ' (' . static::langLabel($l) . ')';
            foreach (array_intersect(self::TEXTS, $parts) as $part) {
                $rules["form.$part.$l"] = ['nullable', 'string', 'max:' . self::LIMITS[$part]];
                $attributes["form.$part.$l"] = $labels[$part] . $tag;
            }
            if (in_array('commands', $parts, true)) {
                $rules["form.commands.$l"] = ['array', 'max:' . self::LIMITS['commands']];
                $rules["form.commands.$l.*.command"] = ['required', 'string', 'max:' . self::LIMITS['command'], 'regex:/^[a-z0-9_]+$/', 'distinct'];
                $rules["form.commands.$l.*.description"] = ['required', 'string', 'max:' . self::LIMITS['command_description']];
                $attributes["form.commands.$l"] = __('tg_profile.commands') . $tag;
                $attributes["form.commands.$l.*.command"] = __('tg_profile.command') . $tag;
                $attributes["form.commands.$l.*.description"] = __('tg_profile.command_description') . $tag;
            }
        }

        $validator = Validator::make(['form' => $this->form], $rules, ['regex' => __('tg_profile.command_format')], $attributes);
        if ($validator->fails()) {
            $this->setErrorBag($validator->errors());
            $first = explode('.', array_key_first($validator->errors()->messages()))[2] ?? 'default';
            $this->dispatch('bot-profile-errors', lang: $first);

            return false;
        }
        $this->resetErrorBag();

        return true;
    }

    private static function result(string $part, string $lang, array $response): array
    {
        $error = $response['ok'] ? null : $response['error'] . (filled($response['retry_after']) ? ' · retry_after ' . $response['retry_after'] . ' s' : '');

        return ['part' => $part, 'lang' => $lang, 'ok' => (bool) $response['ok'], 'skipped' => false, 'error' => $error];
    }

    /** Draft differs from what Telegram has (last load / send). */
    public function unsent(?string $lang = null): bool
    {
        $form = static::normalize($this->form);
        $sent = static::normalize($this->sent);
        if ($lang === null) {
            return $form !== $sent;
        }
        foreach ([...self::TEXTS, 'commands'] as $part) {
            if ($form[$part][$lang] !== $sent[$part][$lang]) {
                return true;
            }
        }

        return false;
    }

    private function saveDraft(): void
    {
        Setting::putRaw(self::DRAFT_KEY, json_encode($this->form, JSON_UNESCAPED_UNICODE));
        $this->savedAt = now()->format('H:i');
    }

    private function assertLang(string $lang): void
    {
        abort_unless(in_array($lang, static::langs(), true), 422);
    }

    /** Any stored shape → the full form; strings trimmed only for comparison (keepEmptyRows keeps the editor's blank rows). */
    public static function normalize(array $data, bool $keepEmptyRows = false): array
    {
        $form = static::emptyForm();
        foreach (static::langs() as $l) {
            foreach (self::TEXTS as $part) {
                $value = $data[$part][$l] ?? '';
                $form[$part][$l] = is_string($value) ? ($keepEmptyRows ? $value : trim($value)) : '';
            }
            $rows = [];
            foreach ((array) ($data['commands'][$l] ?? []) as $row) {
                if (is_array($row)) {
                    $rows[] = ['command' => (string) ($row['command'] ?? ''), 'description' => (string) ($row['description'] ?? '')];
                }
            }
            $form['commands'][$l] = $keepEmptyRows ? $rows : static::rows($rows);
        }

        return $form;
    }

    /** Command rows without fully empty ones; names normalized, descriptions trimmed. */
    public static function rows(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $command = static::commandName((string) ($row['command'] ?? ''));
            $description = trim((string) ($row['description'] ?? ''));
            if ($command !== '' || $description !== '') {
                $out[] = ['command' => $command, 'description' => $description];
            }
        }

        return $out;
    }

    public static function commandName(string $value): string
    {
        return mb_strtolower(ltrim(trim($value), '/'));
    }

    public static function langLabel(string $lang): string
    {
        return $lang === 'default' ? __('tg_profile.lang_default') : strtoupper($lang);
    }

    public function render(TelegramApi $api)
    {
        $errorsByLang = [];
        foreach (array_keys($this->getErrorBag()->messages()) as $key) {
            $l = explode('.', $key)[2] ?? null;
            if ($l) {
                $errorsByLang[$l] = ($errorsByLang[$l] ?? 0) + 1;
            }
        }
        $normalized = static::normalize($this->form);
        $unsentLangs = array_values(array_filter(static::langs(), fn ($l) => $this->unsent($l)));
        $isEmpty = $normalized === static::emptyForm();

        return view('livewire.admin.telegram-bot-profile', [
            'enabled'      => $api->enabled(),
            'langs'        => static::langs(),
            'limits'       => self::LIMITS,
            'errorsByLang' => $errorsByLang,
            'unsentLangs'  => $unsentLangs,
            'isEmpty'      => $isEmpty,
            'partLabels'   => ['name' => __('tg_profile.name'), 'description' => __('tg_profile.description'), 'short' => __('tg_profile.short'), 'commands' => __('tg_profile.commands')],
        ]);
    }
}
