<div>
    <x-admin.i18n-form submit="save" :cancel="route('admin.news')">
        <x-slot:header>
            <x-admin.page-header :back="route('admin.news')" :back-label="__('nav.group.content') . ' · ' . __('nav.news')"
                                 :title="$post?->title_uz ?: __('news.add')" />
        </x-slot:header>

        <x-admin.lang-tabs />

        <x-admin.section :title="__('news.sec_text')" :hint="__('news.sec_text_hint')">
            <x-admin.i18n-field name="item.title" :label="__('news.field_title')" :max="$limits['title']" required />
            <x-admin.i18n-field name="item.excerpt" :label="__('news.field_excerpt')" type="textarea" rows="3" :max="$limits['excerpt']" />
            <x-admin.i18n-field name="item.body" :label="__('news.field_body')" type="markdown" rows="14" :max="$limits['body']" required :hint="__('news.body_hint')" />
        </x-admin.section>

        <x-admin.section :title="__('news.sec_date')" shared>
            <x-admin.field name="item.published_at" :label="__('news.published_at')" type="datetime-local" required :hint="__('news.date_hint')" class="max-w-xs" />
        </x-admin.section>

        <x-admin.section :title="__('news.sec_source')" :hint="__('news.source_hint')" shared collapsed>
            <div class="grid sm:grid-cols-[1fr_2fr] gap-4">
                <x-admin.field name="item.source_name" :label="__('news.source_name')" :max="$limits['source_name']" placeholder="CRPT Turon" />
                <x-admin.field name="item.source_url" :label="__('news.source_url')" type="url" :max="$limits['source_url']" placeholder="https://…" />
            </div>
        </x-admin.section>

        <x-slot:aside>
            <x-admin.publish-card model="item.is_active" date="item.published_at" :view-url="$post?->isPublished() ? route('news.show', $post) : null">
                @if($post)<p class="text-xs text-stone-500">{{ __('admin.list.updated') }}: {{ $post->updated_at?->format('d.m.Y H:i') }}</p>@endif
            </x-admin.publish-card>
        </x-slot:aside>
    </x-admin.i18n-form>
</div>
