<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'icon',
        'is_active',
        'form_component',
        'form_schema',
        'required_documents',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active'          => 'boolean',
            'form_schema'        => 'array',
            'required_documents' => 'array',
        ];
    }

    // ── İlişkiler ─────────────────────────────────────────────

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /** Doğrudan çocuklar (bir alt seviye). */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order');
    }

    /** Tüm alt ağaç, sınırsız derinlikte (recursive). */
    public function childrenRecursive(): HasMany
    {
        return $this->children()->with('childrenRecursive');
    }

    public function demands(): HasMany
    {
        return $this->hasMany(Demand::class);
    }

    public function portfolioItems(): HasMany
    {
        return $this->hasMany(PortfolioItem::class);
    }

    /**
     * Bu kategoride ilan/portföy oluşturabilen hesap grupları
     * (Bireysel Talep, Galericiler, Plazalar, Rent A Car ...).
     * Admin panelinden (AccountTypeGroupResource) yönetilir.
     */
    public function accountTypeGroups(): BelongsToMany
    {
        return $this->belongsToMany(AccountTypeGroup::class, 'account_type_group_category')
            ->withPivot('portfolio_limit')
            ->withTimestamps();
    }

    // ── Scope'lar ─────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    /** Çocuğu olmayan, somut/yaprak kategoriler — talep oluşturmada seçilebilir olanlar. */
    public function scopeLeaves($query)
    {
        return $query->whereDoesntHave('children');
    }

    // ── Yardımcılar ───────────────────────────────────────────

    public function isLeaf(): bool
    {
        return $this->children()->doesntExist();
    }

    /**
     * Ağacın en tepesindeki atayı döner (kendisi zaten kök ise kendisini).
     * PortfolioItem.type kolonu (vasita/gayrimenkul/elektronik gibi 3
     * genel "bucket") her zaman bu kökün slug'ından türetilir — category_id
     * ise en spesifik YAPRAK kategoriyi tutar (kota/izin buradan okunur).
     * İki alan kasıtlı olarak farklı granülaritede: type = genel bucket
     * (vitrin filtresi, istatistik), category_id = tam kategori (kota,
     * yetki, sol menü sayaçları).
     */
    public function root(): Category
    {
        return $this->parent ? $this->parent->root() : $this;
    }

    /**
     * Bu kategori ve tüm alt ağacının ID'lerini düz bir dizi olarak döner.
     * Pazaryeri filtresinde ve yetkilendirme kontrolünde kullanılır.
     */
    public function selfAndDescendantIds(): array
    {
        $ids = [$this->id];

        foreach ($this->children as $child) {
            $ids = array_merge($ids, $child->selfAndDescendantIds());
        }

        return $ids;
    }
}
