@php use App\Domain\Content\PrinterDriver; use App\Domain\Offers\Models\Offer; @endphp
<div>
    <x-admin.i18n-form submit="save" :cancel="route('admin.drivers')">
        <x-slot:header>
            <x-admin.page-header :back="route('admin.drivers')" :back-label="__('nav.group.content') . ' · ' . __('nav.drivers')"
                                 :title="$driver?->name() ?: __('drivers.add')" />
        </x-slot:header>

        <x-admin.lang-tabs />

        <x-admin.section :title="__('drivers.sec_texts')" :hint="__('drivers.sec_texts_hint')">
            <x-admin.i18n-field name="item.summary" :label="__('drivers.summary')" type="textarea" rows="3" :max="$limits['summary']" />
            <x-admin.i18n-field name="item.notes" :label="__('drivers.notes')" type="textarea" rows="6" :max="$limits['notes']" />
        </x-admin.section>

        <x-admin.section :title="__('drivers.sec_name')" shared>
            <div class="grid sm:grid-cols-2 gap-4">
                <x-admin.field name="item.brand" :label="__('drivers.brand')" :max="$limits['brand']" placeholder="Zebra" required />
                <x-admin.field name="item.model" :label="__('drivers.driver_name') . ' · ' . __('drivers.optional')" :max="$limits['model']" placeholder="ZDesigner, GoLabel…" />
            </div>
            <x-admin.field name="item.driver_url" :label="__('drivers.url')" type="url" :max="$limits['driver_url']" placeholder="https://www.zebra.com/…" :hint="__('drivers.url_hint')" required />
        </x-admin.section>

        <x-admin.section :title="__('drivers.products')" :hint="__('drivers.products_hint')" shared>
            @if($chosen->isNotEmpty())
                <ul class="border border-stone-200 rounded-lg divide-y divide-stone-100">
                    @foreach($chosen as $o)
                        <li class="flex items-center gap-3 px-3 py-2" wire:key="chosen-{{ $o->id }}">
                            <span class="w-9 h-9 rounded bg-stone-50 ring-1 ring-stone-200 flex items-center justify-center overflow-hidden shrink-0 text-stone-400">
                                @if($o->image_path)<img src="{{ Offer::publicUrl($o->image_path) }}" alt="" class="w-full h-full object-contain">@else @svg('heroicon-o-printer', 'w-4 h-4') @endif
                            </span>
                            <span class="flex-1 min-w-0 text-sm text-stone-900 truncate">{{ $o->title_uz }}</span>
                            @unless($o->is_active)<x-admin.status-pill status="hidden" />@endunless
                            <button type="button" wire:click="removeOffer({{ $o->id }})" aria-label="{{ __('banners.remove_image') }}: {{ $o->title_uz }}"
                                    class="admin-focus p-2 rounded-lg text-stone-500 hover:text-rose-700 hover:bg-rose-50">@svg('heroicon-o-x-mark', 'w-4 h-4')</button>
                        </li>
                    @endforeach
                </ul>
            @endif
            <x-admin.field name="offerIds.*" :label="__('drivers.add_product')" id="f-add-offer" class="max-w-md">
                <select id="f-add-offer" wire:model.live="addOffer" class="admin-input" data-no-dirty>
                    <option value="">+ {{ __('drivers.add_product') }}</option>
                    @foreach($choices as $cat => $list)
                        <optgroup label="{{ $cat }}">
                            @foreach($list as $o)<option value="{{ $o->id }}">{{ $o->title_uz }}</option>@endforeach
                        </optgroup>
                    @endforeach
                </select>
            </x-admin.field>
        </x-admin.section>

        <x-admin.section :title="__('drivers.sec_more')" shared collapsed>
            <div class="grid sm:grid-cols-2 gap-4">
                <x-admin.field name="item.kind" :label="__('drivers.kind')" id="f-item-kind">
                    <select id="f-item-kind" wire:model="item.kind" data-key="item.kind" data-label="{{ __('drivers.kind') }}" class="admin-input">
                        @foreach(PrinterDriver::KINDS as $k)<option value="{{ $k }}">{{ __('drivers.kinds.' . $k) }}</option>@endforeach
                    </select>
                </x-admin.field>
                <x-admin.field name="item.language" :label="__('drivers.language')" :max="$limits['language']" placeholder="ZPL" />
            </div>
            <fieldset>
                <legend class="text-[13px] font-medium text-stone-700 mb-1">{{ __('drivers.os') }}</legend>
                <div class="flex flex-wrap items-center gap-x-5 gap-y-1">
                    @foreach(PrinterDriver::OS as $os)
                        <label class="inline-flex items-center gap-2 text-sm text-stone-800 min-h-10 cursor-pointer">
                            <input wire:model="item.os" type="checkbox" value="{{ $os }}" data-key="item.os" data-label="{{ __('drivers.os') }}" class="admin-focus rounded border-stone-400 text-amber-700"> {{ __('drivers.os_names.' . $os) }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
        </x-admin.section>

        <x-slot:aside>
            <x-admin.publish-card model="item.is_active" :view-url="$driver?->is_active ? route('drivers.show', $driver) : null">
                @if($driver)
                    <p class="text-xs text-stone-600 font-mono break-all">/drivers/{{ $driver->slug }}</p>
                    <p class="text-xs text-stone-500">{{ __('admin.list.updated') }}: {{ $driver->updated_at?->format('d.m.Y H:i') }}</p>
                @endif
            </x-admin.publish-card>
        </x-slot:aside>
    </x-admin.i18n-form>
</div>
