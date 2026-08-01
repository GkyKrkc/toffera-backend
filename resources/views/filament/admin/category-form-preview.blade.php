{{--
    category-form-preview.blade.php
    Kategori düzenleme sayfasındaki "Form Alanları (Dinamik)" repeater'ının
    yanında gösterilen canlı önizleme. $fields = form_schema'nın o anki
    (henüz kaydedilmemiş) hâli — CategoryResource.php'deki Placeholder'ın
    reaktif Get($get('form_schema')) çağrısından geliyor.

    Frontend'deki gerçek render (DynamicCategoryFields.jsx) ile BİREBİR
    aynı olması gerekmiyor, sadece admin'e "kullanıcı bunu böyle görecek"
    fikrini vermesi yeterli — bu yüzden sabit/güvenli Tailwind sınıfları
    kullanılıyor (dinamik renk enjeksiyonu yok, Tailwind tarayıcısı statik
    class isimlerini görebilsin diye).
--}}
<div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50 p-4">
    @php
        $fields = is_array($fields) ? $fields : [];
        $groups = collect($fields)->groupBy(fn ($f) => trim($f['section'] ?? ''));
    @endphp

    @if(empty($fields))
        <p class="text-sm text-gray-400 italic">
            Henüz alan eklenmedi. Soldaki "Yeni Alan Ekle" ile başlayın — eklediğiniz her alan burada anında görünür.
        </p>
    @else
        <div class="space-y-5">
            @foreach($groups as $sectionName => $sectionFields)
                <div>
                    @if($sectionName !== '')
                        <p class="text-xs font-bold uppercase tracking-wide text-primary-600 dark:text-primary-400 mb-2 pb-1.5 border-b border-gray-200 dark:border-gray-700">
                            {{ $sectionName }}
                        </p>
                    @endif

                    <div class="grid grid-cols-2 gap-3">
                        @foreach($sectionFields as $field)
                            @php $type = $field['type'] ?? 'text'; @endphp
                            <div class="{{ in_array($type, ['textarea', 'radio', 'checkbox']) ? 'col-span-2' : '' }}">
                                <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300 mb-1">
                                    {{ $field['label'] ?: '(isimsiz alan)' }}
                                    @if($field['required'] ?? false)
                                        <span class="text-danger-500">*</span>
                                    @endif
                                </label>

                                @if($type === 'textarea')
                                    <div class="w-full h-16 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900"></div>
                                @elseif($type === 'select')
                                    <div class="w-full h-9 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 flex items-center text-xs text-gray-400">
                                        {{ !empty($field['options']) ? implode(' / ', $field['options']) : 'Seçin...' }}
                                    </div>
                                @elseif(in_array($type, ['radio', 'checkbox']))
                                    <div class="flex flex-wrap gap-1.5">
                                        @forelse (($field['options'] ?? []) as $opt)
                                            <span class="px-2.5 py-1 rounded-md text-[11px] font-medium bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-300 border border-gray-300 dark:border-gray-600">
                                                {{ $opt }}
                                            </span>
                                        @empty
                                            <span class="text-xs text-gray-300 dark:text-gray-600 italic">Seçenek eklenmedi</span>
                                        @endforelse
                                    </div>
                                @elseif($type === 'date')
                                    <div class="w-full h-9 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 flex items-center text-xs text-gray-400">
                                        gg.aa.yyyy
                                    </div>
                                @else
                                    <div class="w-full h-9 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900"></div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
