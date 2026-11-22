<!DOCTYPE html>
<html lang="{{ \App\Http\Middleware\SetLocale::HREFLANG[app()->getLocale()] ?? 'uz' }}">
<head>
@include('partials.public-head', [
    'seoTitle' => \App\Support\Seo::text('studio', 'title'),
    'seoDescription' => \App\Support\Seo::text('studio', 'description'),
    'seoRoute' => 'studio',
    'jsonLd' => [[
        '@context' => 'https://schema.org', '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => config('app.name'), 'item' => route('home')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => __('nav.studio'), 'item' => route('studio')],
        ],
    ], ...(($studioFaq = $catalog->flatMap(fn ($c) => $c->isTemplates() ? [] : $c->offers)->flatMap(fn ($o) => $o->localizedFaq())->take(30))->isNotEmpty()
        ? [['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $studioFaq->map(fn ($f) => ['@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']]])->values()->all()]]
        : [])],
])
@livewireStyles
</head>
<body class="bg-stone-50 text-stone-800 font-body antialiased">
@php
    $phone = config('contact.phone');
    $tel = $phone ? 'tel:' . preg_replace('/[^\d+]/', '', $phone) : null;
    $chip = 'px-2.5 py-1 border border-stone-300 rounded-md hover:bg-stone-100 bg-white';
    $input = 'border border-stone-200 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 bg-white';
@endphp

@include('partials.public-header')

<main class="max-w-6xl mx-auto px-4 py-8"
      x-data="labelStudio(@js([
          'parseUrl' => route('studio.parse'),
          'generateUrl' => route('studio.generate'),
          'printUrl' => route('studio.print'),
          'printBatch' => \App\Http\Controllers\Guest\LabelStudioController::PRINT_BATCH,
          'printLoading' => __('studio.print_loading'),
          'popupBlocked' => __('studio.popup_blocked'),
          'maxLabels' => $maxLabels,
          'offersCount' => $offers->count(),
          'sampleTitle' => __('studio.sample_title'),
          'sampleText' => __('studio.sample_text'),
          'columnLabel' => __('studio.column'),
          'genericError' => __('studio.generic_error'),
          'upload' => \App\Support\Uploads::forJs('studio'),
          'markingLimit' => \App\Domain\Studio\MarkingTrial::LIMIT,
          'tooManyError' => __('studio.too_many'),
          'imageUpload' => \App\Support\Uploads::forJs('studio_image'),
      ]))"
      x-on:pointermove.window="move($event)" x-on:pointerup.window="stop()">

    <div class="flex items-start justify-between gap-4 flex-wrap mb-6">
        <div>
            <h1 class="font-display font-bold text-2xl text-stone-900 tracking-tight">{{ __('studio.title') }}</h1>
            <p class="text-stone-500 mt-1">{{ __('studio.subtitle') }}</p>
            <p class="text-xs text-stone-400 mt-1">
                <span x-show="consent === 'yes'">{{ __('studio.autosave') }}</span>
                <span x-show="consent !== 'yes'" x-cloak>{{ __('studio.storage_off') }}</span>
                · <button type="button" x-on:click="consentModal = true" class="underline hover:text-amber-700">{{ __('studio.storage_change') }}</button>
            </p>
        </div>
        <button type="button" x-on:click="if (confirm(@js(__('studio.start_over_confirm')))) startOver()"
                class="inline-flex items-center gap-1.5 text-sm text-stone-500 hover:text-rose-600 mt-1">
            @svg('heroicon-o-trash', 'w-4 h-4') {{ __('studio.start_over') }}
        </button>
    </div>

    {{-- 1. Data --}}
    <section class="bg-white border border-stone-200 rounded-xl p-5 mb-4">
        <h2 class="font-semibold mb-3"><span class="text-amber-700">1.</span> {{ __('studio.step_data') }}</h2>
        <div class="flex items-center gap-4 flex-wrap text-sm">
            <label class="inline-flex items-center gap-2 px-4 py-2 bg-amber-700 text-white rounded-lg cursor-pointer hover:bg-amber-800 transition" :class="uploading && 'opacity-50 pointer-events-none'">
                @svg('heroicon-o-arrow-up-tray', 'w-4 h-4')
                <span x-text="uploading ? @js(__('common.loading')) : @js(__('studio.upload'))"></span>
                <input type="file" accept=".xlsx,.ods,.csv,.txt" class="hidden" x-on:change="upload($event)">
            </label>
            <template x-if="!fileName">
                <span class="flex items-center gap-2 text-stone-500">
                    {{ __('studio.or_copies') }}
                    <input x-model.number="copies" type="number" min="1" max="{{ $maxLabels }}" class="{{ $input }} w-20">
                    {{ __('studio.copies_unit') }}
                </span>
            </template>
            <template x-if="fileName">
                <span class="flex items-center gap-2">
                    <span class="font-medium" x-text="fileName"></span>
                    <span class="text-stone-500">— <span x-text="rows.length"></span> {{ __('studio.rows_unit') }}</span>
                    <button type="button" x-on:click="clearFile()" class="text-xs text-rose-600 hover:underline">{{ __('studio.remove_file') }}</button>
                </span>
            </template>
        </div>
        <label x-show="fileName && !marking" x-cloak class="flex items-center gap-2 text-sm mt-3">
            <input type="checkbox" x-model="hasHeader" x-on:change="toggleHeader()" class="rounded border-stone-300 text-amber-600">
            {{ __('studio.first_row_header') }}
        </label>
        <p class="text-xs text-stone-500 mt-2">{{ __('studio.upload_hint', ['max' => $maxLabels]) }}</p>
        <p x-show="total > rows.length" x-cloak class="text-xs text-amber-700 mt-1">
            {{ __('studio.truncated', ['max' => $maxLabels]) }} (<span x-text="total > {{ $maxLabels }} ? '{{ $maxLabels }}+' : total"></span>)
        </p>
        {{-- Asl Belgisi codes in the file: free trial of real GS1 marks --}}
        <div x-show="marking" x-cloak class="mt-3 rounded-lg border px-4 py-3 text-sm flex items-start justify-between gap-4 flex-wrap"
             :class="overMarkingLimit ? 'border-amber-300 bg-amber-50 text-amber-900' : 'border-emerald-300 bg-emerald-50 text-emerald-900'">
            <div class="space-y-1">
                <p class="font-semibold flex items-center gap-2">@svg('heroicon-o-qr-code', 'w-5 h-5') {{ __('studio.marking_found') }} <span x-text="labelCount"></span></p>
                <p>{{ __('studio.marking_trial', ['limit' => \App\Domain\Studio\MarkingTrial::LIMIT]) }}</p>
                <p x-show="overMarkingLimit" class="font-medium">{{ __('studio.marking_first', ['limit' => \App\Domain\Studio\MarkingTrial::LIMIT]) }}</p>
            </div>
            <a href="{{ auth()->check() ? route('import-batches.index') : route('register') }}" class="px-4 py-2 bg-amber-700 text-white rounded-lg text-sm font-medium hover:bg-amber-800 whitespace-nowrap">{{ __('studio.marking_cta') }}</a>
        </div>
        <div x-show="rows.length" x-cloak class="mt-3 overflow-x-auto border border-stone-200 rounded-lg">
            <table class="text-xs w-full">
                <thead class="bg-stone-50"><tr><template x-for="h in headers" :key="h"><th class="text-left px-2 py-1.5 font-medium text-stone-600 whitespace-nowrap" x-text="h"></th></template></tr></thead>
                <tbody>
                    <template x-for="(row, i) in rows.slice(0, 3)" :key="i">
                        <tr class="border-t border-stone-100"><template x-for="h in headers" :key="h"><td class="px-2 py-1.5 whitespace-nowrap max-w-48 truncate" x-text="row[h]"></td></template></tr>
                    </template>
                </tbody>
            </table>
        </div>
    </section>

    {{-- 2. Design --}}
    <section class="bg-white border border-stone-200 rounded-xl p-5 mb-4">
        <div class="flex items-center justify-between gap-4 flex-wrap mb-3">
            <h2 class="font-semibold"><span class="text-amber-700">2.</span> {{ __('studio.step_design') }}</h2>
            <div class="flex items-center gap-2 text-sm text-stone-600 flex-wrap">
                {{ __('templates.size') }}
                <input x-model.number="width" x-on:change="clampAll()" type="number" min="10" max="300" class="{{ $input }} w-20">
                ×
                <input x-model.number="height" x-on:change="clampAll()" type="number" min="10" max="300" class="{{ $input }} w-20"> mm
                <button type="button" x-on:click="rotateLabel()" title="{{ __('templates.rotate_label') }}"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1.5 border border-stone-300 rounded-lg text-xs hover:bg-stone-100 whitespace-nowrap">
                    @svg('heroicon-o-arrow-path', 'w-3.5 h-3.5') {{ __('templates.rotate_label') }}
                </button>
                <span class="ml-3">{{ __('templates.columns') }}</span>
                <input x-model.number="columns" type="number" min="1" max="10" class="{{ $input }} w-16">
                <span class="ml-3">{{ __('templates.gap') }}</span>
                <input x-model.number="gap" type="number" min="0" max="30" step="0.5" class="{{ $input }} w-16">
                <span class="ml-3 inline-flex items-center gap-2 flex-wrap">@include('partials.label-watermark-toggle')</span>
            </div>
        </div>

        <div class="flex items-center gap-2 mb-2 flex-wrap text-xs">
            <span class="text-stone-500">{{ __('templates.add') }}:</span>
            <button type="button" x-on:click="add('text', @js(__('templates.text')))" class="{{ $chip }}">{{ __('templates.text') }}</button>
            <button type="button" x-on:click="add('barcode', '123456', 'qr', headers[0] || '')" class="{{ $chip }}">QR</button>
            <button type="button" x-on:click="add('barcode', '123456', 'datamatrix', headers[0] || '')" class="{{ $chip }}">DataMatrix</button>
            <button type="button" x-on:click="add('barcode', '123456', 'code128', headers[0] || '')" class="{{ $chip }}">Code 128</button>
            <button type="button" x-on:click="add('barcode', '4780000000007', 'ean13', headers[0] || '')" class="{{ $chip }}">EAN-13</button>
            <button type="button" x-on:click="pickImage()" class="{{ $chip }} inline-flex items-center gap-1">@svg('heroicon-o-photo', 'w-3.5 h-3.5') {{ __('studio.image_add') }}</button>
            <button type="button" x-on:click="add('mark', 'aslbelgi')" class="{{ $chip }} inline-flex items-center gap-1" title="{{ __('labels.mark_hint') }}"><img src="/images/marks/aslbelgi.png" alt="" class="w-3.5 h-3.5"> {{ __('labels.mark_aslbelgi') }}</button>
            <input x-ref="imageInput" type="file" accept=".png,.jpg,.jpeg,image/png,image/jpeg" class="hidden" x-on:change="imageChosen($event)">
            <span class="w-px h-4 bg-stone-300 mx-1"></span>
            <button type="button" x-on:click="add('text', '', 'qr', '@date')" class="{{ $chip }}">{{ __('studio.source_date') }}</button>
            <button type="button" x-on:click="add('text', '', 'qr', '@serial')" class="{{ $chip }}">{{ __('studio.source_serial') }}</button>
        </div>
        <div x-show="headers.length" x-cloak class="flex items-center gap-2 mb-3 flex-wrap text-xs">
            <span class="text-stone-500">{{ __('studio.columns') }}:</span>
            <template x-for="h in headers" :key="h">
                <button type="button" x-on:click="add('text', '', 'qr', h)" class="{{ $chip }} border-amber-300 text-amber-800" x-text="h"></button>
            </template>
        </div>

        <p x-show="imageError" x-cloak x-text="imageError" class="mb-3 text-xs text-rose-600"></p>
        <p x-show="imageNotSaved" x-cloak class="mb-3 text-xs text-amber-700">{{ __('studio.image_not_saved') }}</p>
        @include('partials.label-editor-body')
    </section>

    {{-- 3. PDF --}}
    <section class="bg-white border border-stone-200 rounded-xl p-5 mb-10">
        <h2 class="font-semibold mb-3"><span class="text-amber-700">3.</span> {{ __('studio.step_pdf') }}</h2>
        <div class="flex items-center gap-4 flex-wrap">
            <button type="button" x-on:click="generate()" :disabled="state === 'working' || !elements.length"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-amber-700 text-white rounded-lg text-sm font-medium hover:bg-amber-800 transition disabled:opacity-50">
                @svg('heroicon-o-arrow-down-tray', 'w-4 h-4') {{ __('studio.download') }} (<span x-text="pdfCount"></span>)
            </button>
            <button type="button" x-on:click="printLabels()" :disabled="printing || !elements.length"
                    class="inline-flex items-center gap-2 px-5 py-2.5 border border-amber-700 text-amber-800 bg-white rounded-lg text-sm font-medium hover:bg-amber-50 transition disabled:opacity-50">
                <span x-show="!printing">@svg('heroicon-o-printer', 'w-4 h-4')</span>
                <span x-show="printing" x-cloak>@svg('heroicon-o-arrow-path', 'w-4 h-4 animate-spin')</span>
                {{ __('studio.print') }} (<span x-text="Math.min(pdfCount, {{ \App\Http\Controllers\Guest\LabelStudioController::PRINT_BATCH }})"></span>)
            </button>
        </div>
        <p class="text-xs text-stone-500 mt-3">{{ __('studio.print_help') }}</p>
        <p x-show="overMarkingLimit" x-cloak class="text-sm text-amber-800 mt-3">{{ __('studio.marking_first', ['limit' => \App\Domain\Studio\MarkingTrial::LIMIT]) }}
            <a href="{{ auth()->check() ? route('import-batches.index') : route('register') }}" class="font-semibold underline">{{ __('studio.marking_cta') }}</a></p>
        <p x-show="error" x-cloak x-text="error" class="text-sm text-rose-600 mt-3"></p>
    </section>

    {{-- Saving the design in this browser: asked on entering the page, nothing is saved before "allow" --}}
    <div x-show="consentModal" x-cloak x-transition.opacity class="fixed inset-0 bg-black/40 z-50 flex items-end sm:items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6 space-y-4" role="dialog" aria-modal="true" aria-labelledby="storage-title">
            <div class="flex items-start gap-3">
                <span class="shrink-0 w-10 h-10 rounded-full bg-amber-100 text-amber-700 flex items-center justify-center">@svg('heroicon-o-bookmark-square', 'w-6 h-6')</span>
                <div>
                    <h2 id="storage-title" class="font-semibold text-lg">{{ __('studio.storage_title') }}</h2>
                    <p class="text-sm text-stone-600 mt-1">{{ __('studio.storage_intro') }}</p>
                </div>
            </div>
            <div class="text-sm space-y-2">
                <p class="font-medium text-stone-800">{{ __('studio.storage_what') }}</p>
                <ul class="text-stone-600 space-y-1 list-disc pl-5">
                    <li>{{ __('studio.storage_item_design') }}</li>
                    <li>{{ __('studio.storage_item_not') }}</li>
                </ul>
                <p class="font-medium text-stone-800 pt-1">{{ __('studio.storage_where') }}</p>
                <ul class="text-stone-600 space-y-1 list-disc pl-5">
                    <li>{{ __('studio.storage_where_1') }}</li>
                    <li>{{ __('studio.storage_where_2') }}</li>
                    <li>{{ __('studio.storage_where_3') }}</li>
                </ul>
            </div>
            <div class="flex flex-col-reverse sm:flex-row gap-2 pt-1">
                <button type="button" x-on:click="declineStorage()" class="flex-1 px-4 py-2.5 border border-stone-300 rounded-lg text-sm hover:bg-stone-50">{{ __('studio.storage_decline') }}</button>
                <button type="button" x-on:click="acceptStorage()" class="flex-1 px-4 py-2.5 bg-amber-700 text-white rounded-lg text-sm font-medium hover:bg-amber-800">{{ __('studio.storage_accept') }}</button>
            </div>
        </div>
    </div>

    {{-- Asl Belgisi codes found: warning, offer of the Asl Belgisi section, or continue with 10 codes --}}
    <div x-show="markingModal" x-cloak x-transition.opacity class="fixed inset-0 bg-black/40 z-40 flex items-center justify-center px-4" x-on:keydown.escape.window="markingModal = false">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6 space-y-4" x-on:click.outside="markingModal = false">
            <div class="flex items-start gap-3">
                <span class="shrink-0 w-10 h-10 rounded-full bg-amber-100 text-amber-700 flex items-center justify-center">@svg('heroicon-o-exclamation-triangle', 'w-6 h-6')</span>
                <div>
                    <h2 class="font-semibold text-lg">{{ __('studio.marking_modal_title') }}</h2>
                    <p class="text-sm text-stone-500 mt-0.5"><span x-text="(marking?.count || labelCount)"></span> {{ __('studio.marking_modal_count') }}</p>
                </div>
            </div>
            <p class="text-sm text-stone-700">{{ __('studio.marking_modal_text', ['limit' => \App\Domain\Studio\MarkingTrial::LIMIT]) }}</p>
            <ul class="text-sm text-stone-600 space-y-1">
                <li>✓ {{ __('studio.marking_modal_p1') }}</li>
                <li>✓ {{ __('studio.marking_modal_p2') }}</li>
                <li>✓ {{ __('studio.marking_modal_p3') }}</li>
            </ul>
            <div class="flex flex-col sm:flex-row gap-2 pt-2">
                <a href="{{ auth()->check() ? route('import-batches.index') : route('register') }}" class="flex-1 text-center px-4 py-2.5 bg-amber-700 text-white rounded-lg text-sm font-medium hover:bg-amber-800">{{ __('studio.marking_cta') }}</a>
                <button type="button" x-on:click="markingModal = false" class="flex-1 px-4 py-2.5 border border-stone-300 rounded-lg text-sm hover:bg-stone-50">{{ __('studio.marking_continue', ['limit' => \App\Domain\Studio\MarkingTrial::LIMIT]) }}</button>
            </div>
        </div>
    </div>

    {{-- Generating dialog: progress + one of our products --}}
    <div x-show="state !== 'idle'" x-cloak class="fixed inset-0 bg-black/40 z-40 flex items-center justify-center px-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
            @include('partials.promo-offer')
            <div x-show="state === 'working'">
                <p class="text-sm mb-2">{{ __('import.generating') }}</p>
                <div class="h-2 bg-stone-100 rounded-full overflow-hidden"><div class="h-full bg-amber-600 transition-all duration-200" :style="`width:${progress}%`"></div></div>
            </div>
            <p x-show="state === 'done'" class="text-sm text-emerald-700 flex items-center gap-2">@svg('heroicon-o-check-circle', 'w-5 h-5') {{ __('import.ready') }}</p>
            <div x-show="state === 'done'" class="flex justify-end pt-5">
                <button type="button" x-on:click="state = 'idle'" class="px-4 py-2 bg-amber-700 text-white rounded-lg text-sm hover:bg-amber-800 transition">OK</button>
            </div>
        </div>
    </div>

    {{-- Asl Belgisi + what we sell --}}
    <section class="bg-amber-50 border border-amber-200 rounded-xl p-5 mb-8 flex items-center justify-between gap-4 flex-wrap">
        <div>
            <h2 class="font-semibold">{{ __('studio.aslbelgi_title') }}</h2>
            <p class="text-sm text-stone-600 mt-1">{{ __('studio.aslbelgi_text') }}</p>
        </div>
        <a href="{{ auth()->check() ? route('import-batches.index') : route('login') }}" class="px-4 py-2 bg-amber-700 text-white rounded-lg text-sm hover:bg-amber-800 transition whitespace-nowrap">{{ __('studio.aslbelgi_login') }}</a>
    </section>

    @if($catalog->isNotEmpty())
        <h2 class="font-display font-bold text-xl text-stone-900 mb-5">{{ __('nav.offers') }}</h2>
        @include('partials.offer-catalog', ['catalog' => $catalog, 'templatePreviews' => $templatePreviews])
    @endif
</main>

@include('partials.public-footer')

@livewireScripts
</body>
</html>
