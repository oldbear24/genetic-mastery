{{-- Evidence thumbnails with zoom; originals as downloads for admins only (UPL-5, OPR-6). --}}
@props(['images', 'originals' => false, 'height' => 'h-[180px]'])
<div x-data="lightbox" @keydown.escape.window="close">
    <div class="grid grid-cols-[repeat(auto-fit,minmax(min(260px,100%),1fr))] gap-3.5">
        @foreach ($images as $image)
            <figure class="m-0 flex flex-col gap-2">
                <a href="{{ route('evidence.show', [$image, 'display']) }}" @click.prevent="show" data-alt="{{ $image->kind->label() }}"
                   class="block {{ $height }} cursor-zoom-in overflow-hidden rounded-[10px] border border-line bg-raised"
                   aria-label="{{ __('submissions.show.open_full', ['kind' => mb_strtolower($image->kind->label())]) }}">
                    <img src="{{ route('evidence.show', [$image, 'thumb']) }}" alt="{{ $image->kind->label() }}" loading="lazy" class="size-full object-cover object-top">
                </a>
                <figcaption class="flex items-baseline justify-between gap-3 text-sm font-medium">
                    <span>{{ $image->kind->label() }}</span>
                    @if ($originals)
                        <a href="{{ route('evidence.show', [$image, 'original']) }}" class="link-underline font-normal">{{ __('admin.detail.original') }}</a>
                    @endif
                </figcaption>
                @if ($originals)
                    <span class="-mt-1 truncate font-mono text-xs text-ink-3" title="{{ $image->original_name }}">{{ $image->original_name }} · {{ __('submissions.show.image_meta', ['size' => \App\Support\Format::bytes($image->bytes), 'width' => $image->width, 'height' => $image->height]) }}</span>
                @endif
            </figure>
        @endforeach
    </div>

    <div x-show="isOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/85 p-4" role="dialog" aria-modal="true" @click.self="close">
        <button type="button" x-ref="close" @click="close" class="absolute top-4 right-4 flex size-11 cursor-pointer items-center justify-center rounded-[10px] border border-line bg-surface text-ink" aria-label="{{ __('submissions.show.close') }}">
            <x-icon.x />
        </button>
        <img :src="src" :alt="alt" class="max-h-full max-w-full rounded-lg object-contain">
    </div>
</div>
