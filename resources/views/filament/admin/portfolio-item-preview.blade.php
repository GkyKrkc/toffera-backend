<div class="space-y-5">

    {{-- Fotoğraf Galerisi --}}
    @if($itemImages->isNotEmpty())
        <div class="grid grid-cols-4 gap-2">
            @foreach($itemImages as $image)
                <a href="{{ $image->url }}" target="_blank"
                   class="block aspect-square rounded-lg overflow-hidden border {{ $image->is_cover ?? false ? 'ring-2 ring-primary-500' : '' }} border-gray-200 dark:border-gray-700">
                    <img src="{{ $image->url }}" alt="" class="w-full h-full object-cover" />
                </a>
            @endforeach
        </div>
    @else
        <div class="text-sm text-gray-400 italic">Bu ilana ait fotoğraf yüklenmemiş.</div>
    @endif

    {{-- Temel Bilgiler --}}
    <div class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm border-t border-gray-100 dark:border-gray-800 pt-4">
        <div>
            <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Kategori</p>
            <p class="font-semibold">{{ $item->category?->name ?? $item->type }}</p>
        </div>
        <div>
            <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Fiyat</p>
            <p class="font-semibold">{{ number_format($item->price ?? 0, 0, ',', '.') }} ₺</p>
        </div>
        <div>
            <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">İlçe / Bölge</p>
            <p class="font-semibold">{{ $item->district ?? '—' }}</p>
        </div>
        <div>
            <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Durum</p>
            <p class="font-semibold">{{ ['available' => 'Satışta', 'reserved' => 'Rezerve', 'sold' => 'Satıldı'][$item->status] ?? $item->status }}</p>
        </div>
        <div>
            <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Sahibi</p>
            <p class="font-semibold">{{ $item->user?->name ?? '—' }}</p>
        </div>
        <div>
            <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Telefon</p>
            <p class="font-semibold">{{ $item->user?->phone ?? '—' }}</p>
        </div>
        <div>
            <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Sahiplik Belgesi</p>
            <p class="font-semibold">
                @if($item->ownership_verified_at)
                    <span class="text-success-600">Doğrulandı ({{ $item->ownership_verified_at->format('d.m.Y') }})</span>
                @else
                    <span class="text-warning-600">Doğrulanmadı</span>
                @endif
            </p>
        </div>
        <div>
            <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Oluşturulma</p>
            <p class="font-semibold">{{ $item->created_at->format('d.m.Y H:i') }}</p>
        </div>
    </div>

    {{-- Açıklama --}}
    @if($item->description)
        <div class="border-t border-gray-100 dark:border-gray-800 pt-4">
            <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1">Açıklama</p>
            <p class="text-sm whitespace-pre-line">{{ $item->description }}</p>
        </div>
    @endif

    {{-- Özellikler (kategoriye özel alanlar / features JSON) --}}
    @if(!empty($item->features))
        <div class="border-t border-gray-100 dark:border-gray-800 pt-4">
            <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-2">Özellikler</p>
            <div class="grid grid-cols-2 gap-x-6 gap-y-2 text-sm">
                @foreach($item->features as $key => $value)
                    <div class="flex justify-between border-b border-gray-50 dark:border-gray-800 pb-1">
                        <span class="text-gray-400">{{ is_string($key) ? $key : '' }}</span>
                        <span class="font-medium">{{ is_array($value) ? implode(', ', $value) : $value }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Belgeler --}}
    @if(($item->documents ?? collect())->isNotEmpty())
        <div class="border-t border-gray-100 dark:border-gray-800 pt-4">
            <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-2">Yüklenen Belgeler</p>
            <div class="space-y-1">
                @foreach($item->documents as $doc)
                    <a href="{{ $doc->url ?? '#' }}" target="_blank"
                       class="flex items-center gap-2 text-sm text-primary-600 hover:underline">
                        <x-filament::icon icon="heroicon-o-paper-clip" class="w-4 h-4" />
                        {{ $doc->name ?? $doc->type ?? 'Belge' }}
                    </a>
                @endforeach
            </div>
        </div>
    @endif

</div>
