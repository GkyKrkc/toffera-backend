<?php

namespace App\Enums;

/**
 * Sistemdeki TÜM bildirim tipleri burada tanımlanır. Yeni bir bildirim
 * ihtiyacı çıktığında (ör. "favori teklifinizde indirim talebiniz kabul
 * edildi") yeni bir Notification class'ı YAZILMAZ — sadece buraya yeni
 * bir case eklenir + template/kanal tanımlanır. Tek dosya, tek yer.
 */
enum NotificationType: string
{
    // ── Talep (Demand) ──
    case DEMAND_APPROVED         = 'demand_approved';
    case DEMAND_REJECTED         = 'demand_rejected';
    case DEMAND_MATCHED          = 'demand_matched';           // portföyünle eşleşen yeni talep var (acenteye)
    case DEMAND_EXPIRING_SOON    = 'demand_expiring_soon';

    // ── Hesap / Uzman Başvurusu ──
    case AGENT_APPROVED          = 'agent_approved';
    case AGENT_REJECTED          = 'agent_rejected';

    // ── Moderasyon (belge / ilan / teklif onayı) ──
    case DOCUMENT_APPROVED       = 'document_approved';
    case DOCUMENT_REJECTED       = 'document_rejected';
    case LISTING_APPROVED        = 'listing_approved';
    case LISTING_REJECTED        = 'listing_rejected';
    case OFFER_MODERATION_APPROVED = 'offer_moderation_approved';
    case OFFER_MODERATION_REJECTED = 'offer_moderation_rejected';

    // ── Teklif (Offer) ──
    case OFFER_RECEIVED          = 'offer_received';           // talep sahibine: yeni teklif geldi
    case OFFER_UPDATED           = 'offer_updated';             // teklif detayı güncellendi
    case OFFER_PRICE_UPDATED     = 'offer_price_updated';       // sadece fiyat değişti
    case OFFER_REVIEWING         = 'offer_reviewing';           // acenteye: teklifiniz değerlendiriliyor
    case OFFER_ACCEPTED          = 'offer_accepted';
    case OFFER_REJECTED          = 'offer_rejected';
    case OFFER_CLOSED_SOLD       = 'offer_closed_sold';         // araç/ev başkasına satıldığı için kapandı
    case OFFER_WITHDRAWN         = 'offer_withdrawn';           // acente teklifini geri çekti

    // ── İndirim Talebi (varsa/gelecekte) ──
    case DISCOUNT_REQUEST_SENT     = 'discount_request_sent';
    case DISCOUNT_REQUEST_ACCEPTED = 'discount_request_accepted';
    case DISCOUNT_REQUEST_REJECTED = 'discount_request_rejected';

    // ── Mesajlaşma ──
    case NEW_MESSAGE             = 'new_message';        // görüşmede yeni mesaj geldi

    // ── Ödeme / Abonelik ──
    case PAYMENT_SUCCESS         = 'payment_success';
    case PAYMENT_FAILED          = 'payment_failed';
    case SUBSCRIPTION_EXPIRING   = 'subscription_expiring';     // 3 gün kala uyarı
    case SUBSCRIPTION_EXPIRED    = 'subscription_expired';
    case CREDIT_LOW              = 'credit_low';                // kontör azaldı

    // ── Yasal Metinler ──
    case LEGAL_DOCUMENT_UPDATED  = 'legal_document_updated';   // zorunlu bir metin güncellendi, tekrar onay gerekiyor

    /** Bildirim listesinde/panelinde gösterilecek başlık */
    public function title(): string
    {
        return match ($this) {
            self::DEMAND_APPROVED         => 'Talebiniz Onaylandı',
            self::DEMAND_MATCHED          => 'Size Uygun Yeni Talep',
            self::DEMAND_EXPIRING_SOON    => 'Talebinizin Süresi Doluyor',
            self::AGENT_APPROVED          => 'Başvurunuz Onaylandı',
            self::AGENT_REJECTED          => 'Başvurunuz Reddedildi',
            self::DOCUMENT_APPROVED       => 'Belgeniz Onaylandı',
            self::DOCUMENT_REJECTED       => 'Belgeniz Reddedildi',
            self::LISTING_APPROVED        => 'İlanınız Yayınlandı',
            self::LISTING_REJECTED        => 'İlanınız Reddedildi',
            self::OFFER_MODERATION_APPROVED => 'Teklifiniz Yayınlandı',
            self::OFFER_MODERATION_REJECTED => 'Teklifiniz Onaylanmadı',
            self::OFFER_RECEIVED          => 'Yeni Teklif Aldınız',
            self::OFFER_UPDATED           => 'Teklif Güncellendi',
            self::OFFER_PRICE_UPDATED     => 'Teklif Fiyatı Güncellendi',
            self::OFFER_REVIEWING         => 'Teklifiniz Değerlendiriliyor',
            self::OFFER_ACCEPTED          => 'Teklifiniz Kabul Edildi',
            self::OFFER_REJECTED          => 'Teklifiniz Reddedildi',
            self::OFFER_CLOSED_SOLD       => 'Teklif Kapandı',
            self::OFFER_WITHDRAWN         => 'Teklif Geri Çekildi',
            self::DISCOUNT_REQUEST_SENT     => 'İndirim Talebi Gönderildi',
            self::DISCOUNT_REQUEST_ACCEPTED => 'İndirim Talebiniz Kabul Edildi',
            self::DISCOUNT_REQUEST_REJECTED => 'İndirim Talebiniz Reddedildi',
            self::NEW_MESSAGE             => 'Yeni Mesaj',
            self::PAYMENT_SUCCESS         => 'Ödeme Alındı',
            self::PAYMENT_FAILED          => 'Ödeme Başarısız',
            self::SUBSCRIPTION_EXPIRING   => 'Aboneliğiniz Sona Eriyor',
            self::SUBSCRIPTION_EXPIRED    => 'Aboneliğiniz Sona Erdi',
            self::CREDIT_LOW              => 'Kontör Bakiyeniz Azaldı',
            self::DEMAND_REJECTED         => 'Talebiniz Reddedildi',
            self::LEGAL_DOCUMENT_UPDATED  => 'Yasal Metin Güncellendi',
        };
    }

    /**
     * Mesaj şablonu — {placeholder} yer tutucuları AppNotification
     * tarafından payload'daki değerlerle değiştirilir.
     */
    public function template(): string
    {
        return match ($this) {
            self::OFFER_RECEIVED      => '{company_name}, "{demand_title}" talebinize {price} teklif verdi.',
            self::OFFER_ACCEPTED      => 'Teklifiniz kabul edildi! Artık iletişim bilgilerini görebilirsiniz.',
            self::OFFER_REJECTED      => 'Teklifiniz elendi.',
            self::OFFER_CLOSED_SOLD   => 'İlgilendiğiniz "{portfolio_title}" portföyü, {company_name} tarafından başka bir alıcıya satıldığı için kapatıldı.',
            self::OFFER_PRICE_UPDATED => '{company_name}, teklifini {price} olarak güncelledi.',
            self::DEMAND_MATCHED      => 'Portföyünüzle eşleşen yeni bir talep var: "{demand_title}"',
            self::AGENT_APPROVED      => 'Tebrikler! Uzman başvurunuz onaylandı, artık taleplere teklif verebilirsiniz.',
            self::AGENT_REJECTED      => 'Uzman başvurunuz reddedildi. Sebep: {reason}',
            self::DOCUMENT_APPROVED   => '"{document_label}" belgeniz onaylandı.',
            self::DOCUMENT_REJECTED   => '"{document_label}" belgeniz reddedildi. Sebep: {reason}',
            self::LISTING_APPROVED    => '"{item_title}" ilanınız incelendi ve yayınlandı.',
            self::LISTING_REJECTED    => '"{item_title}" ilanınız reddedildi. Sebep: {reason}',
            self::OFFER_MODERATION_APPROVED => 'Teklifiniz incelendi ve talep sahibine iletildi.',
            self::OFFER_MODERATION_REJECTED => 'Teklifiniz onaylanmadı. Sebep: {reason}',
            self::SUBSCRIPTION_EXPIRING => 'Aboneliğiniz {days} gün içinde sona erecek.',
            self::CREDIT_LOW          => 'Kontör bakiyeniz {balance} adet kaldı.',
            self::DEMAND_APPROVED     => '"{demand_title}" talebiniz onaylandı ve yayına alındı.',
            self::DEMAND_REJECTED     => '"{demand_title}" talebiniz reddedildi. Sebep: {reason}',
            self::NEW_MESSAGE         => '{sender_name}: {message_preview}',
            self::LEGAL_DOCUMENT_UPDATED => '"{document_title}" güncellendi. Devam edebilmek için yeniden onaylamanız gerekiyor.',
            default                   => '{message}',
        };
    }

    /**
     * Bu bildirim tipi hangi kanallardan gitsin?
     * database + broadcast HER ZAMAN gider (uygulama içi bildirim çanı,
     * ucuz ve anlık). SMS sadece kritik olanlarda (kabul/red/satıldı) —
     * senin belirttiğin gibi maliyeti platform karşılıyor, o yüzden
     * her tipte değil sadece gerçekten önemli olanlarda kullanıyoruz.
     */
    public function channels(): array
    {
        return match ($this) {
            self::OFFER_ACCEPTED,
            self::OFFER_REJECTED,
            self::OFFER_CLOSED_SOLD,
            self::AGENT_APPROVED,
            self::AGENT_REJECTED,
            self::PAYMENT_FAILED,
            self::SUBSCRIPTION_EXPIRED   => ['database', 'broadcast', 'sms'],

            self::PAYMENT_SUCCESS,
            self::SUBSCRIPTION_EXPIRING,
            self::LEGAL_DOCUMENT_UPDATED  => ['database', 'broadcast', 'mail'],

            default                       => ['database', 'broadcast'],
        };
    }

    /**
     * Frontend'deki ICONS haritasıyla (NotificationsPage.jsx) birebir
     * eşleşmeli: 'inbox' | 'check-circle' | 'x-circle' | 'shield-check' |
     * 'target' | 'bell'. Eşleşmeyen bir değer frontend'de sessizce
     * 'bell'e düşer, hata vermez — ama anlamlı ikon kaybolur.
     */
    /**
     * SettingsPage.jsx > "Bildirim Tercihleri" sekmesindeki kategori
     * anahtarlarıyla birebir eşleşmeli. Kullanıcı bir kategoride SMS/
     * e-postayı kapatırsa AppNotification::via() bu değeri okuyup
     * ilgili kanalı filtreler (bkz. User::wantsChannel()) — database/
     * broadcast HER ZAMAN gider, sadece "dışa dönük" kanallar susturulur.
     *
     * 'account' kategorisinin ayarlar ekranında bir açma/kapama kontrolü
     * YOK (kilitli/her zaman açık gösteriliyor) — hesap onay/red gibi
     * kritik olaylar kullanıcı tarafından susturulamaz.
     */
    public function category(): string
    {
        return match ($this) {
            self::OFFER_RECEIVED => 'new_offer',

            self::OFFER_UPDATED,
            self::OFFER_PRICE_UPDATED,
            self::OFFER_REVIEWING,
            self::OFFER_ACCEPTED,
            self::OFFER_REJECTED,
            self::OFFER_CLOSED_SOLD,
            self::OFFER_WITHDRAWN,
            self::OFFER_MODERATION_APPROVED,
            self::OFFER_MODERATION_REJECTED,
            self::DISCOUNT_REQUEST_SENT,
            self::DISCOUNT_REQUEST_ACCEPTED,
            self::DISCOUNT_REQUEST_REJECTED => 'offer_status',

            self::DEMAND_APPROVED,
            self::DEMAND_REJECTED,
            self::DEMAND_EXPIRING_SOON => 'demand_status',

            self::DEMAND_MATCHED => 'region_activity',

            self::NEW_MESSAGE => 'messages',

            self::PAYMENT_SUCCESS,
            self::PAYMENT_FAILED,
            self::SUBSCRIPTION_EXPIRING,
            self::SUBSCRIPTION_EXPIRED,
            self::CREDIT_LOW => 'billing',

            self::AGENT_APPROVED,
            self::AGENT_REJECTED,
            self::DOCUMENT_APPROVED,
            self::DOCUMENT_REJECTED,
            self::LISTING_APPROVED,
            self::LISTING_REJECTED,
            self::LEGAL_DOCUMENT_UPDATED => 'account',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::AGENT_APPROVED,
            self::DOCUMENT_APPROVED,
            self::LISTING_APPROVED,
            self::OFFER_MODERATION_APPROVED,
            self::OFFER_ACCEPTED,
            self::DISCOUNT_REQUEST_ACCEPTED,
            self::PAYMENT_SUCCESS,
            self::DEMAND_APPROVED           => 'check-circle',

            self::AGENT_REJECTED,
            self::DOCUMENT_REJECTED,
            self::LISTING_REJECTED,
            self::OFFER_MODERATION_REJECTED,
            self::OFFER_REJECTED,
            self::OFFER_CLOSED_SOLD,
            self::OFFER_WITHDRAWN,
            self::DISCOUNT_REQUEST_REJECTED,
            self::PAYMENT_FAILED,
            self::SUBSCRIPTION_EXPIRED      => 'x-circle',

            self::OFFER_RECEIVED,
            self::NEW_MESSAGE,
            self::DISCOUNT_REQUEST_SENT     => 'inbox',

            self::DEMAND_MATCHED,
            self::OFFER_REVIEWING           => 'target',

            self::LEGAL_DOCUMENT_UPDATED     => 'shield-check',

            default                          => 'bell',
        };
    }
}
