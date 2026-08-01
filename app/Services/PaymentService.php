<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\BillableProduct;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentGateways\PayTrGateway;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    public function __construct(
        private PayTrGateway $paytr,
        private SubscriptionService $subscriptionService,
        private RegionDealerService $regionDealerService,
    ) {}

    /**
     * Bir kullanıcı için herhangi bir ödenebilir ürün (abonelik VEYA kontör
     * paketi) checkout'unu başlatır: bekleyen bir Payment kaydı oluşturur
     * ve PayTR'dan iframe token'ı ister. Ürün tipine göre farklı bir işlem
     * YOK burada — hangi hakkın tanımlanacağı handlePayTrCallback()'te
     * $product->type'a göre belirleniyor.
     *
     * @return array{success:bool, payment_id?:int, iframe_token?:string, iframe_url?:string, error?:string}
     */
    public function createCheckout(User $user, BillableProduct $product, string $userIp): array
    {
        if (!in_array($product->type, ['subscription', 'credit_pack'], true) || !$product->is_active) {
            return ['success' => false, 'error' => 'Bu ürün şu anda satın alınamaz.'];
        }

        $payment = Payment::create([
            'user_id'             => $user->id,
            'billable_product_id' => $product->id,
            'amount'              => $product->price,
            'gateway'             => 'paytr',
            'status'              => 'pending',
        ]);

        // PayTR merchant_oid: sadece harf/rakam, benzersiz olmalı. Payment
        // id'sini içinde tutuyoruz ki callback geldiğinde ödemeyi tekrar
        // bulabilelim.
        $merchantOid = 'PAY' . $payment->id . strtoupper(bin2hex(random_bytes(4)));
        $payment->update(['gateway_transaction_id' => $merchantOid]);

        $amountKurus = (int) round($product->price * 100);

        $email = $user->email ?: "user{$user->id}@toffera-noemail.com";

        $result = $this->paytr->getIframeToken(
            merchantOid: $merchantOid,
            amountKurus: $amountKurus,
            buyer: [
                'name'    => $user->name,
                'email'   => $email,
                'phone'   => $user->phone,
                'address' => null,
            ],
            basketItems: [
                ['name' => $product->name, 'price' => $amountKurus, 'qty' => 1],
            ],
            userIp: $userIp,
        );

        if (!$result['success']) {
            $payment->update(['status' => 'failed', 'raw_response' => $result]);
            return ['success' => false, 'error' => $result['error']];
        }

        return [
            'success'      => true,
            'payment_id'   => $payment->id,
            'merchant_oid' => $merchantOid,
            'iframe_token' => $result['token'],
            'iframe_url'   => $result['iframe_url'],
        ];
    }

    /**
     * Havale/EFT checkout'u — PayTR'daki gibi bir gateway'e istek atılmaz,
     * çünkü banka tarafından otomatik bir bildirim (callback) gelmiyor.
     * Bunun yerine 'pending' durumda bir Payment kaydı açılır ve kullanıcıya
     * hangi hesaba göndermesi gerektiği gösterilir (bkz. PaymentController).
     * Hak (abonelik/kontör) ancak admin panelden elle onaylandığında
     * tanımlanır (bkz. approveManually()).
     *
     * @return array{success:bool, payment_id?:int, error?:string}
     */
    public function createHavaleCheckout(User $user, BillableProduct $product, BankAccount $bankAccount, ?string $note = null): array
    {
        if (!in_array($product->type, ['subscription', 'credit_pack'], true) || !$product->is_active) {
            return ['success' => false, 'error' => 'Bu ürün şu anda satın alınamaz.'];
        }

        $payment = Payment::create([
            'user_id'             => $user->id,
            'billable_product_id' => $product->id,
            'bank_account_id'     => $bankAccount->id,
            'amount'              => $product->price,
            'gateway'             => 'havale_eft',
            'status'              => 'pending',
            'customer_note'       => $note,
        ]);

        return ['success' => true, 'payment_id' => $payment->id];
    }

    /**
     * Admin panelden (PaymentResource) bir Havale/EFT ödemesini elle
     * onaylar — PayTR callback'inin başarı dalıyla AYNI hakkı tanımlama
     * mantığını (grantEntitlement) çalıştırır, tek fark tetikleyicinin
     * bir banka bildirimi değil admin tıklaması olması.
     */
    public function approveManually(Payment $payment): void
    {
        if ($payment->status !== 'pending') {
            return;
        }

        $payment->update([
            'status'  => 'success',
            'paid_at' => now(),
        ]);

        if ($payment->billableProduct) {
            $this->grantEntitlement($payment);
            $this->regionDealerService->recordRevenueShareForPayment($payment);
        }
    }

    /**
     * Admin panelden bir Havale/EFT ödemesi reddedilirse (ör. hesaba hiç
     * para geçmemişse) — hiçbir hak tanımlanmaz, sadece durum güncellenir.
     */
    public function rejectManually(Payment $payment): void
    {
        if ($payment->status !== 'pending') {
            return;
        }

        $payment->update(['status' => 'failed']);
    }

    /**
     * PayTR bildirim (callback) isteğini işler. Controller bu metodun
     * döndürdüğü string'i AYNEN response body'sine yazmalı — PayTR
     * "OK" dışındaki her cevapta bildirimi tekrar tekrar dener.
     */
    public function handlePayTrCallback(array $post): string
    {
        if (!$this->paytr->verifyCallbackHash($post)) {
            Log::warning('PayTR callback: hash doğrulaması başarısız', ['post' => $post]);
            return 'PAYTR notification failed: bad hash';
        }

        $payment = Payment::where('gateway_transaction_id', $post['merchant_oid'])->first();

        if (!$payment) {
            Log::warning('PayTR callback: eşleşen Payment bulunamadı', ['merchant_oid' => $post['merchant_oid']]);
            return 'PAYTR notification failed: order not found';
        }

        // Idempotency — PayTR aynı bildirimi birden fazla gönderebilir.
        if ($payment->status === 'success') {
            return 'OK';
        }

        $isSuccess = ($post['status'] ?? null) === 'success';

        $payment->update([
            'status'       => $isSuccess ? 'success' : 'failed',
            'paid_at'      => $isSuccess ? now() : null,
            'raw_response' => $post,
        ]);

        if ($isSuccess && $payment->billableProduct) {
            $this->grantEntitlement($payment);

            // İl bayilik gelir payı — abonelik VE kontör ödemelerinin
            // ikisinde de hesaplanır (bkz. RegionDealerService). Ödemeyi
            // yapan kullanıcının iline bayi atanmamışsa sessizce hiçbir
            // şey yapmaz, bu normal bir durum.
            $this->regionDealerService->recordRevenueShareForPayment($payment);
        }

        return 'OK';
    }

    /**
     * Başarılı ödemeden sonra ürün TİPİNE göre hakkı tanımlar:
     *  - 'subscription' → gerçek Subscription kaydı (portföy limiti/teklif
     *    kotası bundan okunur)
     *  - 'credit_pack'  → kullanıcının kontör bakiyesine ekleme
     * Bir hata olursa asla sessizce yutma — para alınmış ama hak
     * tanımlanmamış demek, manuel müdahale gerekir.
     *
     * public: hem handlePayTrCallback() hem de approveManually() (Havale/
     * EFT admin onayı) buradan çağırır — iki yol da AYNI hak tanımlama
     * mantığını kullanmalı.
     */
    public function grantEntitlement(Payment $payment): void
    {
        $product = $payment->billableProduct;

        try {
            if ($product->type === 'subscription') {
                $this->subscriptionService->activateFromPayment($payment);
                return;
            }

            if ($product->type === 'credit_pack') {
                $payment->user->addCredits(
                    (int) $product->credit_amount,
                    "PayTR ile satın alma: {$product->name} (Ödeme #{$payment->id})"
                );
                return;
            }
        } catch (\Throwable $e) {
            Log::error('PayTR callback: ödeme başarılı ama hak tanımlama başarısız', [
                'payment_id'   => $payment->id,
                'product_type' => $product->type,
                'error'        => $e->getMessage(),
            ]);
        }
    }
}
