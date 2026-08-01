<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Bir kullanıcının hangi kategorilere, kaç adet portföy öğesi
 * ekleyebileceğini belirleyen grup. Kayıt akışında (RegisterController)
 * ya otomatik atanır (kind=individual → "Bireysel Talep") ya da kullanıcı
 * seçer (kind=commercial → "Galericiler", "Plazalar", "Rent A Car" ...).
 *
 * agent_type (emlakci/galerici/her_ikisi) ENUM'u yerine bunun tercih
 * edilme sebebi: yeni bir iş kolu eklemek için artık migration/enum
 * değişikliği gerekmiyor, admin panelinden yeni bir satır eklemek yeterli.
 */
class AccountTypeGroup extends Model
{
    protected $fillable = [
        'name', 'slug', 'kind', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    // ── İlişkiler ─────────────────────────────────────────────

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'account_type_group_category')
            ->withPivot('portfolio_limit', 'can_offer')
            ->withTimestamps();
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    // ── Scope'lar ─────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeCommercial($query)
    {
        return $query->where('kind', 'commercial');
    }

    // ── Yardımcılar ───────────────────────────────────────────

    /**
     * Bu grubun, verilen kategoride tanımlı portföy limiti.
     * null = sınırsız (ya kasıtlı öyle tanımlanmış, ya da bu kategori
     * gruba hiç bağlı değil — ikisi de aynı şekilde ele alınır, çağıran
     * taraf önce isCategoryAllowed() ile erişimi ayrıca kontrol etmeli).
     */
    public function portfolioLimitFor(Category $category): ?int
    {
        $pivot = $this->categories()->where('categories.id', $category->id)->first()?->pivot;
        return $pivot?->portfolio_limit;
    }

    /** Bu grubun, verilen kategoride varsayılan olarak teklif verme izni var mı. */
    public function offerAllowedFor(Category $category): bool
    {
        $pivot = $this->categories()->where('categories.id', $category->id)->first()?->pivot;
        return (bool) ($pivot?->can_offer ?? false);
    }

    public function isCategoryAllowed(Category $category): bool
    {
        return $this->categories()->where('categories.id', $category->id)->exists();
    }
}
