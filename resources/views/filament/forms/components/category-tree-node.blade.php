@php
    $hasChildren = ! empty($node['children']);
@endphp

<div style="padding-left: {{ $depth * 18 }}px" x-data="{ open: true }">
    <div class="flex items-center gap-1.5 py-1">
        @if ($hasChildren)
            <button
                type="button"
                @click="open = !open"
                class="w-4 h-4 flex items-center justify-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 flex-shrink-0"
            >
                <svg x-show="!open" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3.5 h-3.5">
                    <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd" />
                </svg>
                <svg x-show="open" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3.5 h-3.5">
                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
                </svg>
            </button>
        @else
            {{-- yaprak düğümlerde ok yerine hizalama boşluğu --}}
            <span class="w-4 h-4 flex-shrink-0"></span>
        @endif

        @if ($hasChildren)
            <label class="flex items-center gap-1.5 text-sm font-semibold text-gray-700 dark:text-gray-200 cursor-pointer select-none">
                <input
                    type="checkbox"
                    class="rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500"
                    title="Bu dalın altındaki tüm kategorileri seç/kaldır"
                    @change="
                        const checked = $event.target.checked;
                        (@json($node['leaf_ids'])).forEach((id) => $wire.set('{{ $statePath }}.' + id, checked));
                    "
                />
                {{ $node['name'] }}
                <span class="text-xs font-normal text-gray-400">({{ count($node['leaf_ids']) }})</span>
            </label>
        @else
            <label class="flex items-center gap-1.5 text-sm text-gray-600 dark:text-gray-300 cursor-pointer select-none">
                <input
                    type="checkbox"
                    class="rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500"
                    wire:model="{{ $statePath }}.{{ $node['id'] }}"
                />
                {{ $node['name'] }}
            </label>
        @endif
    </div>

    @if ($hasChildren)
        <div x-show="open">
            @foreach ($node['children'] as $child)
                @include('filament.forms.components.category-tree-node', [
                    'node'      => $child,
                    'depth'     => $depth + 1,
                    'statePath' => $statePath,
                ])
            @endforeach
        </div>
    @endif
</div>
