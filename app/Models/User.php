<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'phone_verified_at',
        'password',
        'company_name',
        'status',
        'agent_type',
        'account_type_group_id',
        'admin_note',
        'is_banned',
        'ban_reason',
        // Adres alanları
        'city',
        'district',
        'neighborhood',
        'full_address',
        'notification_preferences',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $guard_name = 'web';

    protected function casts(): array
    {
        return [
            'phone_verified_at'         => 'datetime',
            'is_banned'                 => 'boolean',
            'password'                  => 'hashed',
            'notification_preferences'  => 'array',
        ];
    }

    // ── Filament Erişim ───────────────────────────────────────

    public function canAccessPanel(Panel $panel): bool
    {
        // İKİ AYRI PANEL VAR (bkz. BayilikPanelProvider):
        //   - 'admin'   (genel merkez, /admin)         → SADECE admin.
        //   - 'bayilik' (bayi/personel, /admin/bayilik) → admin + dealer + dealer_staff.
        // dealer/dealer_staff artık /admin'e HİÇ giremiyor — kendi ayrı
        // panelinde, kendine özel scope'lanmış moderasyon ekranlarını
        // görür (bkz. her Filament Resource'daki canViewAny()/
        // getEloquentQuery() override'ları — panel erişimi TEK BAŞINA
        // yetki anlamına gelmiyor). Muhasebe (gelir payı) dealer_staff'a
        // hiç açılmıyor — bkz. DealerRevenueShareResource.
        if ($panel->getId() === 'bayilik') {
            return $this->hasRole('admin') || $this->hasRole('dealer') || $this->hasRole('dealer_staff');
        }

        return $this->hasRole('admin');
    }

    /**
     * Bu metodu override eden hiçbir broadcastOn() tanımlamayan bildirim
     * sınıfları (AppNotification, OfferClosedDueToSale vb.) için varsayılan
     * yayın kanalı. Frontend (useNotifications.js) 'user.{id}' özel
     * kanalını dinliyor — bu olmadan Laravel'in varsayılanı
     * (App.Models.User.{id}) kullanılır, frontend'in dinlediğiyle
     * eşleşmez, bildirimler anlık ulaşmaz.
     *
     * Kendi broadcastOn()'unu tanımlayan eski bildirim sınıfları
     * (NewOfferReceived vb.) bundan etkilenmez — Laravel önce bildirimin
     * kendi broadcastOn()'una bakar, yoksa bu fallback'e düşer.
     */
    public function receivesBroadcastNotificationsOn(): string
    {
        return 'user.' . $this->id;
    }

    /**
     * SMS/e-posta göndermeden önce, kullanıcının bu bildirim kategorisi
     * için o kanalı kapatıp kapatmadığını kontrol eder — bkz.
     * AppNotification::via(), NotificationType::category(),
     * /user/notification-preferences (SettingsPage.jsx > Bildirim
     * Tercihleri). Uygulama içi bildirim (database/broadcast) bundan
     * ETKİLENMEZ, her zaman gider — kullanıcı sadece "dışa dönük" SMS/
     * e-posta kanallarını susturabilir. Tercih hiç kaydedilmemişse
     * (varsayılan) kanal açık kabul edilir.
     */
    public function wantsChannel(string $category, string $channel): bool
    {
        $prefs = $this->notification_preferences ?? [];
        return (bool) ($prefs[$category][$channel] ?? true);
    }

    // ── Yasal onaylar (KVKK / Kullanıcı Sözleşmesi / Ticari İleti) ───

    public function consents(): HasMany
    {
        return $this->hasMany(UserConsent::class);
    }

    /**
     * Bu kullanıcının, verilen tip için en son onayladığı versiyon.
     * Hiç onaylamadıysa null (yeni bir zorunlu belge eklendiğinde de
     * bu durum oluşur — pendingConsents() bunu da yakalar).
     */
    public function latestConsentVersion(string $type): ?int
    {
        return $this->consents()
            ->where('legal_document_type', $type)
            ->max('version');
    }

    /**
     * Zorunlu (is_mandatory) yasal metinlerden, kullanıcının ya HİÇ
     * onaylamadığı ya da güncel versiyonun GERİSİNDE kaldığı olanlar —
     * frontend bunu görünce giriş sonrası bloklayıcı bir onay ekranı
     * gösterir (bkz. LegalReconsentGate.jsx, AuthController::userResponse()).
     * Mevcut (bu özellikten önce kaydolmuş) kullanıcılar için de doğal
     * olarak çalışır: hiç UserConsent satırı yoksa hepsi "bekliyor" sayılır.
     */
    public function pendingConsents(): \Illuminate\Support\Collection
    {
        return \App\Models\LegalDocument::query()
            ->where('is_mandatory', true)
            ->get()
            ->filter(fn (\App\Models\LegalDocument $doc) => $this->latestConsentVersion($doc->type) < $doc->version)
            ->map(fn (\App\Models\LegalDocument $doc) => [
                'type'    => $doc->type,
                'title'   => $doc->title,
                'version' => $doc->version,
            ])
            ->values();
    }

    // ── İlişkiler ─────────────────────────────────────────────

    public function addresses(): HasMany
    {
        return $this->hasMany(UserAddress::class)->orderByDesc('is_default');
    }

    public function defaultAddress(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(UserAddress::class)->where('is_default', true);
    }

    public function demands(): HasMany
    {
        return $this->hasMany(Demand::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    public function portfolioItems(): HasMany
    {
        return $this->hasMany(PortfolioItem::class);
    }

    public function agentDocuments(): HasMany
    {
        return $this->hasMany(AgentDocument::class);
    }

    public function smsLogs(): HasMany
    {
        return $this->hasMany(SmsLog::class, 'phone', 'phone');
    }

    public function emailVerifications(): HasMany
    {
        return $this->hasMany(EmailVerification::class);
    }

    /**
     * Kullanıcının hangi kategorilere, kaç adet portföy ekleyebileceğini
     * belirleyen grup (bkz. AccountTypeGroup). Bireysel kayıtta otomatik
     * atanır, ticari kayıtta kullanıcı seçer.
     */
    public function accountTypeGroup(): BelongsTo
    {
        return $this->belongsTo(AccountTypeGroup::class);
    }

    /** Kategori bazlı yetkilerin tek gerçek kaynağı — bkz. CategoryAccessService. */
    public function categoryPermissions(): HasMany
    {
        return $this->hasMany(UserCategoryPermission::class);
    }

    // ── Adres yardımcıları ────────────────────────────────────

    public function getFullLocationAttribute(): ?string
    {
        $parts = array_filter([
            $this->neighborhood,
            $this->district,
            $this->city,
        ]);
        return count($parts) ? implode(', ', $parts) : null;
    }

    public function hasAddress(): bool
    {
        return !empty($this->city) && !empty($this->district);
    }

    // ── Durum yardımcıları ────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === 'active' && !$this->is_banned;
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isBanned(): bool
    {
        return (bool) $this->is_banned;
    }

    public function isPhoneVerified(): bool
    {
        return $this->phone_verified_at !== null;
    }

    // ── Rol yardımcıları ─────────────────────────────────────

    public function isAdmin(): bool       { return $this->hasRole('admin'); }
    public function isBuyer(): bool       { return $this->hasRole('buyer'); }
    public function isAgent(): bool       { return $this->hasRole('agent'); }
    public function isDealer(): bool      { return $this->hasRole('dealer'); }
    public function isDealerStaff(): bool { return $this->hasRole('dealer_staff'); }

    /** Bu kullanıcının (dealer ise) il/ilçe bayilik atamaları. */
    public function regionDealerAssignments(): HasMany
    {
        return $this->hasMany(RegionDealer::class);
    }

    /** Bu kullanıcının yaptığı "bayi olmak istiyorum" başvuruları. */
    public function dealerApplications(): HasMany
    {
        return $this->hasMany(DealerApplication::class);
    }

    /** Bu kullanıcının (dealer_staff ise) departman personeli üyelikleri. */
    public function dealerStaffMemberships(): HasMany
    {
        return $this->hasMany(DealerStaff::class);
    }

    // ── Abonelik yardımcıları ─────────────────────────────────

    /**
     * @deprecated Yeni sistemde canOfferInCategory() kullan. Bu metod sadece
     * hasActiveSubscription() ile aynı adı taşıdığı ve başka yerlerden
     * (view/blade, eski frontend beklentisi) çağrılıyor olma ihtimaline karşı
     * yeni sisteme yönlendirilerek burada tutuluyor.
     */
    public function hasActiveSubscription(): bool
    {
        return $this->activeSubscription() !== null;
    }

    // ── Query Scope'lar ───────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active')->where('is_banned', false);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeBanned($query)
    {
        return $query->where('is_banned', true);
    }

    public function scopeAgents($query)
    {
        return $query->whereHas('roles', fn($q) => $q->where('name', 'agent'));
    }

    public function scopeBuyers($query)
    {
        return $query->whereHas('roles', fn($q) => $q->where('name', 'buyer'));
    }

    public function regions(): HasMany
    {
        return $this->hasMany(AgentRegion::class);
    }

    // ── Ödeme / Abonelik / Kontör (yeni entitlement sistemi) ──

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function itemOfferGrants(): HasMany
    {
        return $this->hasMany(ItemOfferGrant::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Şu an aktif olan abonelik (varsa). Birden fazla geçmiş kayıt olabilir,
     * en son başlayan aktif olan döner.
     */
    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->latest('starts_at')
            ->first();
    }

    /** Bireysel (agent olmayan) bir kullanıcının aynı anda kaç aktif
     *  "satış hakkı" (item_offer_grants) olabilir — bunun üstü kayıt dışı
     *  galericilik gibi görünür, engellenir. */
    private const MAX_ACTIVE_GRANTS_FOR_INDIVIDUAL = 3;

    /** Bir kontörün açtığı satış hakkının kaç gün geçerli olduğu. */
    private const OFFER_GRANT_DAYS = 30;

    /**
     * YENİ SİSTEM — role/agent_type'dan bağımsız yetki kontrolü.
     *
     * Kim olduğun (buyer/agent) değil, NE SATIN ALDIĞIN teklif verme
     * hakkını belirler. Normal bir kullanıcı da kontör alıp/abone olup
     * teklif verebilir — bu yüzden isAgent() kontrolü burada YOK.
     *
     * $portfolioItem verilirse (teklif bir portföy öğesiyle ilişkiliyse):
     *   1) Aktif abonelik bu kategoriyi kapsıyor mu + aylık kota dolmadı mı?
     *      → aboneliğin kapsamı hesap bazlı, öğe bazlı süre kısıtı yok.
     *   2) Bu öğe için zaten aktif bir "satış hakkı" (grant) var mı?
     *      → varsa kontör harcamadan izin ver (30 gün içinde tekrar tekrar
     *      farklı taleplere teklif verebilir).
     *   3) Yeni hak almak için: sahiplik belgesi yüklenmiş mi (agent
     *      değilse zorunlu) + bireysel kullanıcı için aktif hak sayısı
     *      limiti aşılmamış mı + kontör bakiyesi var mı?
     *
     * $portfolioItem verilmezse (öğesiz, serbest teklif): sadece abonelik
     * veya kontör bakiyesi kontrol edilir, süreli hak oluşturulmaz —
     * tek kullanımlık kontör harcaması (eski davranış).
     */
    public function canOfferInCategory(string $categorySlug, ?PortfolioItem $portfolioItem = null): bool
    {
        $subscription = $this->activeSubscription();

        if ($subscription) {
            $product = $subscription->billableProduct;
            $categoryOk = is_null($product->categories) || in_array($categorySlug, $product->categories, true);

            if ($categoryOk) {
                $quota = $product->offer_quota;
                if (is_null($quota) || $subscription->offers_used_this_period < $quota) {
                    return true;
                }
            }
        }

        if ($portfolioItem) {
            // İlan admin tarafından onaylanmamışsa hiç teklife konu olamaz —
            // ne agent ne bireysel, herkes için geçerli.
            if ($portfolioItem->moderation_status !== 'approved') {
                return false;
            }

            // Zaten aktif hakkı varsa kontöre hiç gerek yok.
            if ($portfolioItem->hasActiveOfferGrant()) {
                return true;
            }

            // Yeni hak alınacaksa: agent değilsen sahiplik belgesi şart.
            if (!$this->isAgent() && !$portfolioItem->isOwnershipVerified()) {
                return false;
            }

            // Kayıt dışı galericilik koruması — agent değilsen aktif hak sayısı sınırlı.
            if (!$this->isAgent()) {
                $activeGrantCount = ItemOfferGrant::where('user_id', $this->id)
                    ->where('ends_at', '>', now())
                    ->distinct('portfolio_item_id')
                    ->count('portfolio_item_id');

                if ($activeGrantCount >= self::MAX_ACTIVE_GRANTS_FOR_INDIVIDUAL) {
                    return false;
                }
            }
        }

        return $this->credit_balance > 0;
    }

    /**
     * Teklif başarıyla oluşturulduktan SONRA çağrılır — hangi kaynaktan
     * hak düşüldüyse (abonelik kotası / kontör) onu işler. $portfolioItem
     * verilirse ve zaten aktif bir grant'ı varsa kontör HARCANMAZ — sadece
     * yeni grant oluşturulurken 1 kontör düşer.
     */
    public function consumeOfferEntitlement(string $categorySlug, Offer $offer, ?PortfolioItem $portfolioItem = null): void
    {
        $subscription = $this->activeSubscription();

        if ($subscription) {
            $product = $subscription->billableProduct;
            $categoryOk = is_null($product->categories) || in_array($categorySlug, $product->categories, true);
            $quota = $product->offer_quota;

            if ($categoryOk && (is_null($quota) || $subscription->offers_used_this_period < $quota)) {
                $subscription->increment('offers_used_this_period');
                return;
            }
        }

        // Öğe bazlı: zaten aktif hakkı varsa hiçbir şey harcanmaz.
        if ($portfolioItem && $portfolioItem->hasActiveOfferGrant()) {
            return;
        }

        if ($this->credit_balance > 0) {
            \Illuminate\Support\Facades\DB::transaction(function () use ($offer, $portfolioItem) {
                $this->decrement('credit_balance');

                $tx = WalletTransaction::create([
                    'user_id'        => $this->id,
                    'type'           => 'offer_spend',
                    'amount'         => -1,
                    'balance_after'  => $this->fresh()->credit_balance,
                    'reference_type' => Offer::class,
                    'reference_id'   => $offer->id,
                    'description'    => $portfolioItem
                        ? 'Portföy öğesi için ' . self::OFFER_GRANT_DAYS . ' günlük teklif hakkı (1 kontör)'
                        : 'Teklif verme ücreti (1 kontör)',
                ]);

                // Öğe bazlı teklifse: 30 günlük süreli hak oluştur, tekrar
                // tekrar farklı taleplere teklif verebilsin diye.
                if ($portfolioItem) {
                    ItemOfferGrant::create([
                        'portfolio_item_id'     => $portfolioItem->id,
                        'user_id'               => $this->id,
                        'starts_at'              => now(),
                        'ends_at'                => now()->addDays(self::OFFER_GRANT_DAYS),
                        'wallet_transaction_id'  => $tx->id,
                    ]);
                }
            });
        }
    }

    public function addCredits(int $amount, ?string $description = null): WalletTransaction
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Yüklenecek kontör miktarı pozitif olmalı.');
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($amount, $description) {
            $this->increment('credit_balance', $amount);

            return WalletTransaction::create([
                'user_id'        => $this->id,
                'type'           => 'topup',
                'amount'         => $amount,
                'balance_after'  => $this->fresh()->credit_balance,
                'description'    => $description ?? "Admin tarafından {$amount} kontör yüklendi",
            ]);
        });
    }

    // ── Portföy limiti (grup + abonelik override) ─────────────

    /**
     * Bu kullanıcının verilen kategoride en fazla kaç portföy öğesi
     * ekleyebileceği. null = sınırsız.
     *
     * Öncelik sırası:
     *   1) Aktif aboneliğin ürünü unlimited_portfolio ise → null (sınırsız)
     *   2) Aktif aboneliğin ürünü portfolio_limit_override taşıyorsa → o sayı
     *   3) Yoksa: kullanıcının account_type_group'unun bu kategori için
     *      pivot'ta tanımlı limiti (o da null olabilir → sınırsız)
     *
     * Kullanıcının hiç grubu yoksa veya grup bu kategoriye izinli değilse,
     * bu metod 0 döner değil — çağıran taraf önce canAddPortfolioItem()
     * ile erişimi kontrol etmeli (limit ile "erişim yok" farklı şeyler).
     */
    public function portfolioLimitFor(Category $category): ?int
    {
        $subscription = $this->activeSubscription();

        if ($subscription) {
            $product = $subscription->billableProduct;

            if ($product->unlimited_portfolio) {
                return null;
            }

            if (!is_null($product->portfolio_limit_override)) {
                return $product->portfolio_limit_override;
            }
        }

        return $this->accountTypeGroup?->portfolioLimitFor($category);
    }

    /**
     * Bu kullanıcı, verilen kategoriye yeni bir portföy öğesi ekleyebilir mi?
     * Hem "bu kategoriye izinli mi" hem "limiti doldu mu" kontrolünü birlikte yapar.
     */
    public function canAddPortfolioItem(Category $category): bool
    {
        if (!$this->accountTypeGroup) {
            return false;
        }

        if (!$this->accountTypeGroup->isCategoryAllowed($category)) {
            return false;
        }

        $limit = $this->portfolioLimitFor($category);

        if (is_null($limit)) {
            return true; // sınırsız
        }

        $current = $this->portfolioItems()->where('category_id', $category->id)->count();

        return $current < $limit;
    }

    /**
     * /me endpoint'i ve dashboard'lar için tek, tutarlı özet. Eski
     * offer_limit/subscription_plan alanlarının yerini bu alıyor.
     */
    public function entitlementSummary(): array
    {
        $subscription = $this->activeSubscription();
        $product      = $subscription?->billableProduct;

        return [
            'credit_balance'      => $this->credit_balance,
            'active_subscription' => $subscription ? [
                'product_code'             => $product->code,
                'product_name'             => $product->name,
                'starts_at'                => $subscription->starts_at?->toDateString(),
                'ends_at'                  => $subscription->ends_at?->toDateString(),
                'offer_quota'              => $product->offer_quota, // null = sınırsız
                'offers_used_this_period'  => $subscription->offers_used_this_period,
                'offers_remaining'         => is_null($product->offer_quota)
                    ? null // sınırsız
                    : max(0, $product->offer_quota - $subscription->offers_used_this_period),
            ] : null,
        ];
    }

}
