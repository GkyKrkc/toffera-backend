<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\BillableProduct;
use App\Models\PaymentGatewaySetting;
use App\Services\PaymentService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentService $payments,
        private SubscriptionService $subscriptions,
    ) {}

    // ─────────────────────────────────────────────────────────
    // Abonelik satın alma (uzmanlar — basic/premium/pro, portföy limitli
    // paketler) — bekleyen bir Payment açar, PayTR'dan iframe token ister.
    // Frontend, dönen iframe_url'i bir <iframe> içinde göstermeli.
    // POST /api/subscription/checkout
    // Auth: auth-token + agent.approved
    // Body: { plan_code: 'basic_monthly' | 'premium_monthly' | 'pro_monthly' }
    // ─────────────────────────────────────────────────────────
    public function checkout(Request $request): JsonResponse
    {
        $request->validate([
            'plan_code' => 'required|string',
        ]);

        $product = $this->subscriptions->findPlan($request->plan_code);

        if (!$product) {
            return response()->json(['message' => 'Geçersiz veya pasif plan.'], 422);
        }

        return $this->startCheckout($request, $product);
    }

    // ─────────────────────────────────────────────────────────
    // Kontör paketi satın alma (bireysel/uzman olmayan kullanıcılar —
    // 1 kontör = 1 teklif verme hakkı). Abonelik gerektirmez, sadece
    // giriş yapmış olmak yeterli.
    // POST /api/credit-packs/checkout
    // Auth: auth-token
    // Body: { plan_code: 'credit_pack_10' | 'credit_pack_20' | 'credit_pack_30' }
    // ─────────────────────────────────────────────────────────
    public function creditPackCheckout(Request $request): JsonResponse
    {
        $request->validate([
            'plan_code' => 'required|string',
        ]);

        $product = BillableProduct::query()
            ->where('type', 'credit_pack')
            ->where('code', $request->plan_code)
            ->where('is_active', true)
            ->first();

        if (!$product) {
            return response()->json(['message' => 'Geçersiz veya pasif kontör paketi.'], 422);
        }

        return $this->startCheckout($request, $product);
    }

    private function startCheckout(Request $request, BillableProduct $product): JsonResponse
    {
        if ($request->input('payment_method') === 'havale_eft') {
            return $this->startHavaleCheckout($request, $product);
        }

        $result = $this->payments->createCheckout(
            $request->user(),
            $product,
            $request->ip(),
        );

        if (!$result['success']) {
            return response()->json(['message' => $result['error']], 422);
        }

        return response()->json([
            'method'       => 'paytr',
            'payment_id'   => $result['payment_id'],
            'merchant_oid' => $result['merchant_oid'],
            'iframe_token' => $result['iframe_token'],
            'iframe_url'   => $result['iframe_url'],
        ]);
    }

    // Havale/EFT dalı — PayTR gibi bir iframe token'ı YOK, bunun yerine
    // seçilen banka hesabının bilgileri döner ve kullanıcı ekranda bu
    // hesaba transfer yapması gerektiğini görür. Hak, admin panelden elle
    // onaylanana kadar tanımlanmaz (bkz. PaymentResource onay aksiyonu).
    private function startHavaleCheckout(Request $request, BillableProduct $product): JsonResponse
    {
        $havaleSetting = PaymentGatewaySetting::forGateway('havale_eft');
        if (!$havaleSetting || !$havaleSetting->is_active) {
            return response()->json(['message' => 'Havale/EFT ile ödeme şu anda kullanılamıyor.'], 422);
        }

        $request->validate([
            'bank_account_id' => 'required|integer|exists:bank_accounts,id',
            'note'            => 'nullable|string|max:255',
        ]);

        $bankAccount = BankAccount::where('is_active', true)->find($request->bank_account_id);
        if (!$bankAccount) {
            return response()->json(['message' => 'Geçersiz banka hesabı.'], 422);
        }

        $result = $this->payments->createHavaleCheckout(
            $request->user(),
            $product,
            $bankAccount,
            $request->input('note'),
        );

        if (!$result['success']) {
            return response()->json(['message' => $result['error']], 422);
        }

        return response()->json([
            'method'       => 'havale_eft',
            'payment_id'   => $result['payment_id'],
            'amount'       => $product->price,
            'product_name' => $product->name,
            'bank_account' => [
                'id'           => $bankAccount->id,
                'banka_adi'    => $bankAccount->banka_adi,
                'hesap_sahibi' => $bankAccount->hesap_sahibi,
                'iban'         => $bankAccount->iban,
                'sube'         => $bankAccount->sube,
                'aciklama'     => $bankAccount->aciklama,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // GET /api/bank-accounts — herkese açık. Havale/EFT ödeme yöntemi
    // seçildiğinde kullanıcıya gösterilecek aktif şirket hesapları.
    // ─────────────────────────────────────────────────────────
    public function bankAccounts(): JsonResponse
    {
        return response()->json([
            'data' => BankAccount::active()->get(['id', 'banka_adi', 'hesap_sahibi', 'iban', 'sube', 'aciklama']),
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // GET /api/payment-methods — herkese açık. Frontend'in hangi ödeme
    // yöntemi seçeneklerini göstereceğini belirler. PayTR her zaman
    // listede (tek çalışan kart gateway'i); Havale/EFT admin panelden
    // (Ödeme Sağlayıcıları) açılıp kapatılabilir.
    // ─────────────────────────────────────────────────────────
    public function paymentMethods(): JsonResponse
    {
        $havaleActive = (bool) (PaymentGatewaySetting::forGateway('havale_eft')?->is_active);

        return response()->json([
            'data' => array_values(array_filter(['paytr', $havaleActive ? 'havale_eft' : null])),
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // PayTR bildirim (callback) uç noktası — PayTR Mağaza Paneli'nde
    // "Bildirim URL" alanına bu adres tanımlanmalı:
    //   https://<domain>/api/payments/paytr/callback
    // Auth YOK — PayTR sunucu-sunucu POST atar, token/cookie taşımaz.
    // Cevap gövdesi TAM OLARAK "OK" olmalı, aksi halde PayTR bildirimi
    // tekrar tekrar dener.
    // POST /api/payments/paytr/callback
    // ─────────────────────────────────────────────────────────
    public function paytrCallback(Request $request): Response
    {
        $result = $this->payments->handlePayTrCallback($request->all());

        return response($result)->header('Content-Type', 'text/plain');
    }
}
