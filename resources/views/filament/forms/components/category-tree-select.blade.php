{{--
    Kategori seçimi için GERÇEK bir ağaç menü (expand/collapse ok işaretli,
    her seviyede checkbox). Bkz. CategoriesRelationManager::categoryTreeData()
    — $roots burada nested düz array olarak geliyor (Eloquent model değil),
    her düğümde: id, name, children[], leaf_ids[] (kendi altındaki tüm
    yaprak kategori id'leri, dal/kök checkbox'ının "hepsini seç" davranışı
    için).

    $getStatePath() → bu ViewField'in Livewire'daki tam state yolu (ör.
    "mountedActionsData.0.selected_categories"). Yaprak checkbox'lar
    doğrudan bu yolun altına wire:model ile yazıyor, dal/kök checkbox'ı ise
    $wire.set ile kendi altındaki tüm yaprakları tek seferde set ediyor.
--}}
<div class="rounded-lg border border-gray-200 dark:border-white/10 max-h-96 overflow-y-auto p-2 space-y-0.5 bg-white dark:bg-gray-900">
    @forelse ($roots as $node)
        @include('filament.forms.components.category-tree-node', [
            'node'      => $node,
            'depth'     => 0,
            'statePath' => $getStatePath(),
        ])
    @empty
        <p class="text-sm text-gray-400 px-2 py-1">Hiç kategori bulunamadı.</p>
    @endforelse
</div>
