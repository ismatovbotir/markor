<div>
    <x-admin.i18n-form submit="save" :cancel="route('admin.label-marks')">
        <x-slot:header>
            <x-admin.page-header :back="route('admin.label-marks')" :back-label="__('nav.group.content') . ' · ' . __('nav.label_marks')"
                                 :title="$mark?->name_uz ?: __('label_marks.add')" />
        </x-slot:header>

        <x-admin.lang-tabs />

        <x-admin.section :title="__('label_marks.sec_name')" :hint="__('label_marks.sec_name_hint')">
            <x-admin.i18n-field name="item.name" :label="__('label_marks.field_name')" :max="$limits['name']" required />
        </x-admin.section>

        <x-admin.section :title="__('label_marks.image')" :hint="__('label_marks.image_hint')" shared>
            <x-admin.image-upload model="photo" :image="$image" :label="__('label_marks.image')"
                                  :note="$photo ? __('label_marks.image_new') : ($mark?->path ? null : __('label_marks.no_image'))"
                                  :hint="$mark?->path ? __('label_marks.replace_hint') : __('label_marks.format_hint')" />
        </x-admin.section>

        <x-admin.section :title="__('label_marks.sec_place')" shared>
            <div class="grid sm:grid-cols-3 gap-4">
                <x-admin.field name="item.group" :label="__('label_marks.field_group')" required>
                    <select id="f-item-group" wire:model="item.group" data-key="item.group" data-label="{{ __('label_marks.field_group') }}" class="admin-input w-full">
                        @foreach($groups as $g => $label)<option value="{{ $g }}">{{ $label }}</option>@endforeach
                    </select>
                </x-admin.field>
                @if($mark)
                    <div>
                        <p class="text-[13px] font-medium text-stone-700 mb-1">{{ __('label_marks.field_key') }}</p>
                        <p class="font-mono text-sm py-2 text-stone-800">{{ $mark->key }}</p>
                        <p class="text-xs text-stone-500">{{ __('label_marks.key_fixed') }}</p>
                    </div>
                @else
                    <x-admin.field name="item.key" :label="__('label_marks.field_key')" max="40" required placeholder="halal" :hint="__('label_marks.key_hint')" />
                @endif
                <x-admin.field name="item.sort" :label="__('label_marks.field_sort')" type="number" />
            </div>
        </x-admin.section>

        <x-admin.section :title="__('label_marks.field_source')" :hint="__('label_marks.source_hint')" shared>
            <x-admin.field name="item.source" :label="__('label_marks.field_source')" :max="$limits['source']" />
        </x-admin.section>

        <x-slot:aside>
            <x-admin.publish-card model="item.is_active">
                <p class="text-xs text-stone-500">{{ __('label_marks.hide_hint') }}</p>
                @if($mark)<p class="text-xs text-stone-500">{{ __('admin.list.updated') }}: {{ $mark->updated_at?->format('d.m.Y H:i') }}</p>@endif
            </x-admin.publish-card>
        </x-slot:aside>
    </x-admin.i18n-form>
</div>
