<?php

namespace App\Services;

use App\Models\BillableProduct;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * NOT (2026-07, PayTR entegrasyonu sırasında yeniden yazıldı):
 *
 * Bu servis daha önce `users` tablosundaki eski/legacy `subscription_plan`,
 * `subscription_started_at`, `subscription_ends_at`, `offer_limit`
 * kolonlarını okuyup yazıyordu ve sabit bir PLANS dizisi kullanıyordu.
 * Ancak gerçek yetkilendirme motoru (User::activeSubscription(),
 * canOfferInCategory(), portfolioLimitFor()) çoktan `subscriptions` +
 * `billable_products` tablolarını kullanan yeni sisteme geçmişti — eski
 * servis artık HİÇBİR gerçek davranışı etkilemiyordu. Üstelik summary()
 * metodu var olmayan User::remainingOffers()'ı çağırdığı için
 * GET /api/subscription her zaman fatal hata veriyordu.
 *
 * Şimdi bu servis gerçek sisteme (Subscription/BillableProduct/Payment)
 * bağlandı; ödeme (Payment) başarılı olduğunda activateFromPayment(),
 * admin panelden ücretsiz tanımlama için grantByAdmin() kullanılır.
 */
class SubscriptionService
{
    /** Satın alınabilir tüm aktif abonelik ürünleri. */
    public function getActivePlans()
    {
        return BillableProduct::query()
            ->where('type', 'subscription')
            ->where('is_active', true)
            ->orderBy('price')
            ->get();
    }

    public function findPlan(string $code): ?BillableProduct
    {
        return BillableProduct::query()
            ->where('type', 'subscription')
            ->where('code', $code)
            ->first();
    }

    /**
     * Başarılı bir ödemeden sonra gerçek Subscription kaydını oluşturur.
     */
    public function activateFromPayment(Payment $payment): Subscription
    {
        $product = $payment->billableProduct;
        $user    = $payment->user;

        if (!$product) {
            throw new \RuntimeException("Payment #{$payment->id} bir BillableProduct'a bağlı değil.");
        }

        return $this->grant($user, $product, $payment);
    }

    /**
     * Admin panelden ödeme almadan direkt abonelik tanımlama
     * (AdminController::setSubscription).
     */
    public function grantByAdmin(User $user, BillableProduct $product): Subscription
    {
        return $this->grant($user, $product, null);
    }

    private function grant(User $user, BillableProduct $product, ?Payment $payment): Subscription
    {
        return DB::transaction(function () use ($user, $product, $payment) {
            // Önceki aktif abonelik(ler) varsa iptal et — aynı anda birden
            // fazla aktif abonelik olmasın.
            $user->subscriptions()
                ->where('status', 'active')
                ->update(['status' => 'cancelled']);

            $durationDays = $product->duration_days ?? 30;

            return Subscription::create([
                'user_id'                 => $user->id,
                'billable_product_id'     => $product->id,
                'status'                  => 'active',
                'starts_at'               => now(),
                'ends_at'                 => now()->addDays($durationDays),
                'auto_renew'              => false,
                'offers_used_this_period' => 0,
                'period_resets_at'        => now()->addDays($durationDays),
                'payment_id'              => $payment?->id,
            ]);
        });
    }

    /**
     * Kullanıcının aktif aboneliğini iptal eder. Kalan süre boyunca
     * haklar geçerli kalsın istenirse ends_at dokunulmaz; burada
     * "hemen kes" davranışı seçildi (eski servisle aynı davranış).
     */
    public function cancel(User $user): void
    {
        $subscription = $user->activeSubscription();

        if ($subscription) {
            $subscription->update(['auto_renew' => false, 'status' => 'cancelled', 'ends_at' => now()]);
        }
    }

    /**
     * GET /api/subscription ve admin kullanıcı detayı için tek, tutarlı
     * özet. User::entitlementSummary() (doğru/güncel kaynak) üzerine plan
     * fiyat/tarih bilgisini ekler.
     */
    public function summary(User $user): array
    {
        $entitlements = $user->entitlementSummary();
        $subscription = $user->activeSubscription();
        $product      = $subscription?->billableProduct;

        return [
            ...$entitlements,
            'plan_code'      => $product->code ?? null,
            'plan_price'     => $product->price ?? null,
            'starts_at'      => $subscription?->starts_at?->format('d.m.Y'),
            'ends_at'        => $subscription?->ends_at?->format('d.m.Y'),
            'days_remaining' => ($subscription?->ends_at && $subscription->ends_at->isFuture())
                ? (int) now()->diffInDays($subscription->ends_at)
                : 0,
            'auto_renew'     => $subscription?->auto_renew ?? false,
        ];
    }
}
