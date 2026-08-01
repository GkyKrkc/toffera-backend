<?php

namespace App\Jobs;

use App\Models\Offer;
use App\Notifications\OfferClosedDueToSale;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Bir portföy öğesi satıldığında, ona bağlı (artık 'rejected' durumuna
 * çekilmiş) tüm tekliflerin sahiplerine bildirim gönderir.
 *
 * ÖLÇEKLENEBİLİRLİK NOTLARI:
 *
 * 1) chunkById(200) — 20 teklif de olsa 20.000 teklif de olsa RAM'e aynı
 *    anda hepsini yüklemez, 200'lük parçalar halinde işler. Job kuyrukta
 *    ne kadar sürede biterse bitsin, worker'ı boğmaz.
 *
 * 2) closed_notified_at (idempotency) — job bir sebeple (timeout, deploy,
 *    worker crash) yarıda kesilip retry edilirse, zaten bildirim
 *    gönderilmiş teklifleri TEKRAR bildirmez. Aynı müşteriye 2 kere
 *    "satıldı" bildirimi gitmesin diye bu alan kritik.
 *
 * 3) Bu job'ı tetikleyen controller tarafı SADECE dispatch eder, işin
 *    kendisi tamamen burada, HTTP isteğinin dışında çalışır.
 *
 * 4) Prod önerisi: queue driver olarak 'database' değil 'redis' kullan.
 *    Redis, yüksek hacimli dispatch/pop işlemlerinde veritabanı
 *    driver'ından çok daha performanslıdır ve Laravel Horizon ile
 *    kuyruk izleme/otomatik worker ölçekleme imkanı sağlar.
 *
 * 5) Bu job'ı ayrı bir 'notifications' kuyruğunda çalıştırıyoruz
 *    (bkz. PortfolioController::update → ->onQueue('notifications')),
 *    böylece kritik işler (ör. ödeme, resim işleme) için ayrı worker
 *    havuzu ayırabilir, bildirim yoğunluğu diğer işleri geciktirmez.
 */
class CloseOffersForSoldPortfolioItem implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Kalıcı hata durumunda kaç kere denenecek */
    public int $tries = 3;

    /** Denemeler arası bekleme (saniye) — üstel artan */
    public array $backoff = [10, 30, 60];

    /** Tek bir job çalışmasının azami süresi (saniye) */
    public int $timeout = 300;

    public function __construct(protected int $portfolioItemId)
    {
    }

    public function handle(): void
    {
        Offer::query()
            ->where('portfolio_item_id', $this->portfolioItemId)
            ->where('rejected_reason', 'sold_elsewhere')
            ->whereNull('closed_notified_at')
            ->with(['demand.user', 'portfolioItem', 'user'])
            ->chunkById(200, function ($offers) {
                foreach ($offers as $offer) {
                    if ($offer->demand?->user) {
                        $offer->demand->user->notify(new OfferClosedDueToSale($offer));
                    }

                    // saveQuietly: model event'lerini (observer'ları) tekrar
                    // tetiklemeden sessizce işaretle.
                    $offer->forceFill(['closed_notified_at' => now()])->saveQuietly();
                }
            });
    }

    /**
     * Job kalıcı olarak başarısız olursa (3 deneme de tükendiyse) buraya düşer.
     * En azından loglayıp gözden kaçırmayalım.
     */
    public function failed(\Throwable $exception): void
    {
        \Log::error('CloseOffersForSoldPortfolioItem başarısız oldu', [
            'portfolio_item_id' => $this->portfolioItemId,
            'error'             => $exception->getMessage(),
        ]);
    }
}
