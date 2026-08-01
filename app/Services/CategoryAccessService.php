<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Offer;
use App\Models\PortfolioItem;
use App\Models\User;
use App\Models\UserCategoryPermission;

/**
 * Kategori bazlı yetkilendirmenin TEK giriş noktası. Üç katmanı ayırır:
 *
 *   1) YETKİ (capability)  — bu kategoride portföy ekleyebilir/teklif
 *      verebilir mi? Kaynak: user_category_permissions (tek gerçek kaynak).
 *   2) KONTENJAN (quota)   — ekleyebiliyorsa kaç adet? Önce kullanıcının
 *      AKTİF ABONELİĞİ (Subscription->billableProduct.portfolio_limit_override /
 *      unlimited_portfolio) kontrol edilir — varsa ve bu kategoriye
 *      uygulanıyorsa o değer geçerlidir (limit yükseltme/sınırsız burada
 *      devreye girer). Yoksa user_category_permissions.portfolio_limit
 *      kullanılır (null = sınırsız).
 *   3) KAPASİTE (capacity) — abonelik/kontör bakiyesi var mı? Bu katmana
 *      DOKUNULMADI, User::canOfferInCategory()/consumeOfferEntitlement()
 *      aynen kullanılıyor.
 *
 * PortfolioController ve OfferController artık doğrudan AccountTypeGroup
 * pivot'una ya da User'daki eski metotlara değil, SADECE bu servise
 * bakmalı.
 */
class CategoryAccessService
{
    /**
     * Kullanıcının account_type_group'undaki kategori atamalarını
     * user_category_permissions'a senkronize eder.
     *
     * - source='group' olan satırlar güncellenir/eklenir.
     * - source='manual' olan satırlara DOKUNULMAZ (admin override korunur).
     * - Gruptan çıkarılmış ama hâlâ source='group' olan kategoriler silinir
     *   (admin elle override etmediyse, artık grubun parçası değilse
     *   yetkisi de kalmamalı).
     *
     * Çağrılması gereken yerler:
     *   - Kullanıcının account_type_group_id'si değiştiğinde (RegisterController,
     *     AdminController vb.)
     *   - Bir AccountTypeGroup'un kategori pivot'u (limit/can_offer) admin
     *     panelinden değiştiğinde — o gruptaki HER kullanıcı için tekrar
     *     çağrılmalı (toplu, tercihen queue'lanmış bir job üzerinden).
     */
    public function syncFromGroup(User $user): void
    {
        $group = $user->accountTypeGroup;

        $groupCategoryIds = $group
            ? $group->categories()->pluck('categories.id')->all()
            : [];

        // Gruptaki her kategori için upsert (manual olanlara dokunma).
        if ($group) {
            foreach ($group->categories as $category) {
                $existing = UserCategoryPermission::where('user_id', $user->id)
                    ->where('category_id', $category->id)
                    ->first();

                if ($existing && $existing->source === 'manual') {
                    continue; // admin override — grup senkronu ezmez
                }

                UserCategoryPermission::updateOrCreate(
                    ['user_id' => $user->id, 'category_id' => $category->id],
                    [
                        'can_add_portfolio' => true,
                        'portfolio_limit'   => $category->pivot->portfolio_limit,
                        'can_offer'         => (bool) $category->pivot->can_offer,
                        'source'            => 'group',
                    ]
                );
            }
        }

        // Artık grubun parçası olmayan, ama hâlâ source='group' olan
        // satırları temizle (manual olanlar korunur).
        UserCategoryPermission::where('user_id', $user->id)
            ->where('source', 'group')
            ->when(
                count($groupCategoryIds) > 0,
                fn($q) => $q->whereNotIn('category_id', $groupCategoryIds),
                fn($q) => $q // grup yoksa / hiç kategorisi yoksa hepsini temizle
            )
            ->delete();
    }

    /** Tüm gruptaki kullanıcılar için senkron — grubun kategori ataması değiştiğinde çağrılır. */
    public function syncAllUsersInGroup(\App\Models\AccountTypeGroup $group): void
    {
        $group->users()->each(fn(User $user) => $this->syncFromGroup($user));
    }

    private function permissionFor(User $user, Category $category): ?UserCategoryPermission
    {
        return UserCategoryPermission::where('user_id', $user->id)
            ->where('category_id', $category->id)
            ->first();
    }

    // ── YETKİ + KONTENJAN ────────────────────────────────────────

    /**
     * Etkili portföy limiti (null = sınırsız).
     *
     * Öncelik sırası:
     *   1) Aktif abonelik var VE ürünün kategori kısıtı yok ya da bu
     *      kategoriyi kapsıyorsa: unlimited_portfolio true ise sınırsız,
     *      değilse portfolio_limit_override (tanımlıysa) kullanılır.
     *   2) Yoksa: user_category_permissions.portfolio_limit (grup limiti /
     *      admin manuel override).
     */
    private function effectivePortfolioLimit(User $user, Category $category, ?UserCategoryPermission $perm): ?int
    {
        $subscription = $user->activeSubscription();

        if ($subscription) {
            $product = $subscription->billableProduct;
            $categoryOk = is_null($product->categories) || in_array($category->slug, $product->categories, true);

            if ($categoryOk) {
                if ($product->unlimited_portfolio) {
                    return null; // sınırsız
                }

                if (!is_null($product->portfolio_limit_override)) {
                    return $product->portfolio_limit_override;
                }
            }
        }

        return $perm?->portfolio_limit;
    }

    public function canAddPortfolio(User $user, Category $category): bool
    {
        $perm = $this->permissionFor($user, $category);

        if (!$perm || !$perm->can_add_portfolio) {
            return false;
        }

        $limit = $this->effectivePortfolioLimit($user, $category, $perm);

        if (is_null($limit)) {
            return true; // sınırsız
        }

        $current = PortfolioItem::where('user_id', $user->id)
            ->where('category_id', $category->id)
            ->count();

        return $current < $limit;
    }

    /** null = sınırsız. Erişimi olup olmadığını değil, SAYIYI döner — önce canAddPortfolio() ile eriş kontrol edilmeli. */
    public function remainingPortfolioQuota(User $user, Category $category): ?int
    {
        $perm = $this->permissionFor($user, $category);

        if (!$perm) {
            return 0;
        }

        $limit = $this->effectivePortfolioLimit($user, $category, $perm);

        if (is_null($limit)) {
            return null;
        }

        $current = PortfolioItem::where('user_id', $user->id)
            ->where('category_id', $category->id)
            ->count();

        return max(0, $limit - $current);
    }

    /**
     * Sadece YETKİ (capability) katmanı — kontör/abonelik kontrolü içermez.
     * OfferController::store() bunu ve User::canOfferInCategory()'yi (KAPASİTE)
     * bilerek ayrı ayrı çağırıyor, bkz. oradaki açıklama.
     *
     * KÖK/DAL KATEGORİ EŞLEŞMESİ: talep formu (demands/create/vehicle,
     * demands/create/realestate) hâlâ kök "vasita"/"gayrimenkul"
     * kategorisiyle talep oluşturuyor (useDemandForm({categorySlug: ...})) —
     * portföy tarafında olduğu gibi yaprak kategori seçtirmiyor. Kullanıcı
     * izinleri (user_category_permissions) ise SADECE yaprak kategorilerde
     * tanımlanıyor (AccountTypeGroup → CategoryAccessService::syncFromGroup).
     * Bu yüzden kök kategoride TAM eşleşme aranmaz — kategori kendisinde
     * VEYA alt ağacındaki (selfAndDescendantIds) herhangi bir yaprakta
     * can_offer=true varsa yeterli sayılır. Aksi halde tüm hesap tipleri,
     * gerçekte yetkili oldukları talepler için bile 403 alıyordu.
     */
    public function hasOfferCapability(User $user, Category $category): bool
    {
        return UserCategoryPermission::where('user_id', $user->id)
            ->whereIn('category_id', $category->selfAndDescendantIds())
            ->where('can_offer', true)
            ->exists();
    }
}
