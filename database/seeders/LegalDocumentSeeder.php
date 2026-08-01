<?php

namespace Database\Seeders;

use App\Models\LegalDocument;
use Illuminate\Database\Seeder;

/**
 * Dört zorunlu/isteğe-bağlı yasal metnin ilk sürümünü oluşturur. Standart
 * form taslak metinlerdir — {sirket_unvani} gibi yer tutucular Kurumsal
 * Bilgiler sayfasından (bkz. CompanySettingsPage) doldurulur.
 *
 * ÖNEMLİ: Bu metinler genel/standart taslaklardır, YAYINA ALMADAN ÖNCE
 * bir avukat tarafından incelenmesi ve platformun gerçek veri işleme
 * pratikleriyle (üçüncü taraf paylaşımları, saklama süreleri vb.)
 * karşılaştırılması önerilir.
 *
 * Çalıştırma: php artisan db:seed --class=LegalDocumentSeeder
 * (updateOrCreate kullanır — tekrar çalıştırmak güvenlidir, ama admin
 * panelden yapılmış içerik değişikliklerinin ÜZERİNE YAZAR; sadece ilk
 * kurulumda veya bilerek sıfırlamak istendiğinde çalıştırılmalı.)
 */
class LegalDocumentSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->documents() as $doc) {
            LegalDocument::updateOrCreate(
                ['type' => $doc['type']],
                [
                    'title'        => $doc['title'],
                    'body'         => $doc['body'],
                    'is_mandatory' => $doc['is_mandatory'],
                    'version'      => 1,
                    'published_at' => now(),
                ]
            );
        }
    }

    private function documents(): array
    {
        return [
            [
                'type'         => LegalDocument::TYPE_USER_AGREEMENT,
                'title'        => 'Kullanıcı Sözleşmesi',
                'is_mandatory' => true,
                'body'         => $this->userAgreement(),
            ],
            [
                'type'         => LegalDocument::TYPE_KVKK_DISCLOSURE,
                'title'        => 'KVKK Aydınlatma Metni',
                'is_mandatory' => true,
                'body'         => $this->kvkkDisclosure(),
            ],
            [
                'type'         => LegalDocument::TYPE_EXPLICIT_CONSENT,
                'title'        => 'Açık Rıza Metni',
                'is_mandatory' => false,
                'body'         => $this->explicitConsent(),
            ],
            [
                'type'         => LegalDocument::TYPE_COMMERCIAL_MSG,
                'title'        => 'Ticari Elektronik İleti Onayı',
                'is_mandatory' => false,
                'body'         => $this->commercialMessageConsent(),
            ],
        ];
    }

    private function userAgreement(): string
    {
        return <<<'TEXT'
KULLANICI SÖZLEŞMESİ

Son güncelleme: {bugun}

1. TARAFLAR

Bu Kullanıcı Sözleşmesi ("Sözleşme"), bir tarafta {sirket_unvani} (Vergi Dairesi: {vergi_dairesi}, Vergi No: {vergi_no}, MERSİS No: {mersis_no}, Adres: {sirket_adresi}) ("Platform" veya "Şirket") ile diğer tarafta Platform'a üye olan gerçek veya tüzel kişi ("Kullanıcı") arasında, Kullanıcı'nın Platform'u kullanmaya başlamasıyla birlikte elektronik ortamda kurulmuştur.

2. TANIMLAR

"Platform": Şirket'e ait, gayrimenkul ve vasıta alanında talep-teklif eşleştirmesi yapılmasına imkân veren internet sitesi ve mobil uygulamaları.
"Talep": Bir Kullanıcı'nın satın almak/kiralamak istediği bir gayrimenkul veya vasıta için Platform üzerinden oluşturduğu ilan.
"Teklif": Bir Uzman/Bayi Kullanıcı'nın, kendi portföyünde yer alan bir ürünle bir Talep'e karşılık verdiği fiyat teklifi.
"Uzman/Bayi": Platform üzerinde portföy oluşturup Talep'lere Teklif verebilen, ticari veya bireysel Kullanıcı.

3. SÖZLEŞMENİN KONUSU VE PLATFORM'UN ROLÜ

Platform, alıcı ile satıcı/uzman arasında Talep ve Teklif'lerin bir araya gelmesini sağlayan bir aracı hizmet sağlayıcıdır. Platform, Kullanıcılar arasında kurulan hiçbir alım-satım, kiralama veya benzeri sözleşmenin tarafı değildir; bu işlemlerin içeriğinden, ifasından, ürün/hizmet kalitesinden ve taraflar arasındaki uyuşmazlıklardan sorumlu tutulamaz. Talep ve Teklif'lerde yer alan bilgilerin doğruluğundan, ilgili içeriği oluşturan Kullanıcı sorumludur.

4. ÜYELİK ŞARTLARI

4.1. Üyelik, gerçek kişiler için 18 yaşını doldurmuş ve fiil ehliyetine sahip kişilerce; tüzel kişiler için ise yetkili temsilcileri aracılığıyla gerçekleştirilebilir.
4.2. Kullanıcı, üyelik sırasında verdiği bilgilerin doğru, güncel ve eksiksiz olduğunu kabul eder. Yanlış veya eksik bilgi verilmesinden doğacak sonuçlardan Kullanıcı sorumludur.
4.3. Kullanıcı, hesabının güvenliğinden (şifre, doğrulama kodu vb.) bizzat sorumludur; hesabı üzerinden gerçekleştirilen işlemler Kullanıcı'ya aittir.

5. TARAFLARIN HAK VE YÜKÜMLÜLÜKLERİ

5.1. Kullanıcı, Platform'u yürürlükteki mevzuata, ahlaka ve dürüstlük kurallarına uygun şekilde kullanacağını; yanıltıcı, hukuka aykırı, üçüncü kişilerin haklarını ihlal eden içerik paylaşmayacağını kabul eder.
5.2. Uzman/Bayi Kullanıcılar, portföylerine ekledikleri ürünlerin satışa/kiraya konu edilebilir nitelikte olduğunu ve gerekli yasal izin/belgelere sahip olduklarını beyan eder.
5.3. Platform, teknik nedenlerle hizmetin geçici olarak durdurulması, içeriklerin incelenmesi, mevzuata veya işbu Sözleşme'ye aykırı kullanımların tespiti hâlinde ilgili hesabı askıya alma veya sonlandırma hakkını saklı tutar.
5.4. Platform, Kullanıcılar tarafından oluşturulan Talep ve Teklif içeriklerini, moderasyon amacıyla incelemeye, uygunsuz bulduğu içerikleri yayından kaldırmaya yetkilidir.

6. ÜCRETLENDİRME

Platform üzerinde Uzman/Bayi Kullanıcıların Talep'lere Teklif verebilmesi, kontör satın alma veya abonelik planlarına tabi olabilir. Güncel fiyatlandırma ve kapsam, Platform'un ilgili sayfalarında ilan edilir ve Kullanıcı, Teklif verme işlemini gerçekleştirdiğinde geçerli fiyatlandırmayı kabul etmiş sayılır.

7. FİKRİ MÜLKİYET

Platform'un tasarımı, yazılımı, marka ve logoları ile Platform tarafından oluşturulan tüm içerikler Şirket'e aittir. Kullanıcı'nın kendi oluşturduğu içerikler (ilan metni, fotoğraf vb.) üzerindeki hakları saklı kalmak kaydıyla, Kullanıcı bu içerikleri Platform'da yayınlanmak üzere Platform'a kullanma izni verir.

8. KİŞİSEL VERİLERİN KORUNMASI

Kullanıcı'ya ait kişisel veriler, ayrıca sunulan KVKK Aydınlatma Metni kapsamında işlenir. İşbu Sözleşme'nin kişisel verilere ilişkin hükümleri, KVKK Aydınlatma Metni ile birlikte yorumlanır.

9. SORUMLULUĞUN SINIRLANDIRILMASI

Platform, kesintisiz veya hatasız hizmet sunacağını taahhüt etmez. Platform, Kullanıcılar arasındaki işlemlerden, üçüncü kişi hizmet sağlayıcılardan (ödeme kuruluşu, kargo vb.) kaynaklanan aksaklıklardan ve mücbir sebeplerden doğan zararlardan sorumlu tutulamaz.

10. SÖZLEŞMENİN FESHİ

Kullanıcı, dilediği zaman hesabını kapatarak işbu Sözleşme'yi feshedebilir. Platform, işbu Sözleşme'nin ihlali hâlinde Kullanıcı hesabını askıya alma veya kalıcı olarak kapatma hakkına sahiptir.

11. UYGULANACAK HUKUK VE UYUŞMAZLIKLARIN ÇÖZÜMÜ

İşbu Sözleşme'den doğan uyuşmazlıklarda Türk hukuku uygulanır. Tüketici sıfatını haiz Kullanıcılar bakımından, ilgili mevzuatta belirlenen parasal sınırlar dâhilinde Tüketici Hakem Heyetleri, üzerindeki uyuşmazlıklarda ise Tüketici Mahkemeleri yetkilidir. Diğer uyuşmazlıklarda Şirket'in merkezinin bulunduğu yer mahkemeleri ve icra daireleri yetkilidir.

12. YÜRÜRLÜK

İşbu Sözleşme, Kullanıcı'nın üyelik sırasında elektronik ortamda onaylaması ile yürürlüğe girer ve Kullanıcı'nın üyeliği devam ettiği sürece yürürlükte kalır.

İletişim: {sirket_telefon} — {sirket_email}
TEXT;
    }

    private function kvkkDisclosure(): string
    {
        return <<<'TEXT'
KVKK AYDINLATMA METNİ

Son güncelleme: {bugun}

1. VERİ SORUMLUSUNUN KİMLİĞİ

6698 sayılı Kişisel Verilerin Korunması Kanunu ("KVKK") uyarınca, kişisel verileriniz; veri sorumlusu sıfatıyla {sirket_unvani} (Adres: {sirket_adresi}, Vergi Dairesi: {vergi_dairesi}, Vergi No: {vergi_no}, MERSİS No: {mersis_no}, KEP: {kep_adresi}) tarafından, aşağıda açıklanan kapsamda işlenmektedir.

2. KİŞİSEL VERİLERİNİZİN HANGİ AMAÇLA İŞLENECEĞİ

Toplanan kişisel verileriniz (ad-soyad, telefon numarası, e-posta adresi, adres bilgisi, şirket unvanı, işlem güvenliği bilgileri, portföy/talep/teklif kayıtları, mesajlaşma içerikleri, ödeme/fatura bilgileri, IP kaydı gibi işlem güvenliği bilgileri);

- Üyelik işlemlerinin gerçekleştirilmesi ve hesabınızın yönetimi,
- Talep ve Teklif eşleştirme hizmetinin sunulması,
- Kimlik/telefon doğrulaması (SMS OTP) yapılması,
- Kullanıcılar arası mesajlaşma altyapısının çalıştırılması,
- Ödeme, abonelik ve kontör işlemlerinin yürütülmesi,
- Yasal yükümlülüklerin (fatura düzenleme, vergi mevzuatı vb.) yerine getirilmesi,
- Hizmet kalitesinin artırılması, hata/uyuşmazlık analizinin yapılması,
- Bildirim gönderilmesi (uygulama içi, SMS, e-posta),
- Hukuki uyuşmazlıkların çözümü ve yetkili kurumların taleplerinin karşılanması,

amaçlarıyla işlenmektedir.

3. İŞLENEN KİŞİSEL VERİLERİN KİMLERE VE HANGİ AMAÇLA AKTARILABİLECEĞİ

Kişisel verileriniz; yukarıda belirtilen amaçların gerçekleştirilmesiyle sınırlı olarak, hizmet aldığımız barındırma (hosting), SMS/e-posta gönderim, ödeme altyapısı (banka/ödeme kuruluşu) sağlayıcılarına, yasal olarak yetkili kamu kurum ve kuruluşlarına, talebiniz üzerine ilgili Talep/Teklif'in karşı tarafına (yalnızca işlemin gerçekleştirilmesi için gerekli ölçüde) ve gerektiğinde hukuki danışmanlarımıza, KVKK'nın 8. ve 9. maddelerinde belirtilen şartlara uygun olarak aktarılabilir.

4. KİŞİSEL VERİ TOPLAMANIN YÖNTEMİ VE HUKUKİ SEBEBİ

Kişisel verileriniz; üyelik formu, mobil/web uygulama kullanımı, çağrı merkezi/destek talepleri gibi kanallar aracılığıyla, elektronik ortamda toplanmaktadır. Verileriniz; KVKK'nın 5. maddesinde yer alan "bir sözleşmenin kurulması veya ifasıyla doğrudan ilgili olması", "hukuki yükümlülüğün yerine getirilmesi", "ilgili kişinin kendisi tarafından alenileştirilmiş olması", "bir hakkın tesisi, kullanılması veya korunması için veri işlemenin zorunlu olması" ve "veri sorumlusunun meşru menfaati için veri işlenmesinin zorunlu olması" hukuki sebeplerine dayanılarak işlenmektedir. Bu hukuki sebeplerin kapsamadığı, isteğe bağlı işleme faaliyetleri (ör. pazarlama amaçlı profilleme, ticari elektronik ileti gönderimi) için ayrıca açık rızanız alınmaktadır.

5. KVKK MADDE 11 KAPSAMINDAKİ HAKLARINIZ

KVKK'nın 11. maddesi uyarınca; kişisel verinizin işlenip işlenmediğini öğrenme, işlenmişse buna ilişkin bilgi talep etme, işlenme amacını ve amacına uygun kullanılıp kullanılmadığını öğrenme, yurt içinde/yurt dışında aktarıldığı üçüncü kişileri bilme, eksik/yanlış işlenmişse düzeltilmesini isteme, KVKK'nın 7. maddesindeki şartlar çerçevesinde silinmesini/yok edilmesini isteme, yapılan düzeltme/silme işlemlerinin verilerin aktarıldığı üçüncü kişilere bildirilmesini isteme, işlenen verilerin münhasıran otomatik sistemler ile analiz edilmesi suretiyle aleyhinize bir sonucun ortaya çıkmasına itiraz etme ve kanuna aykırı işleme sebebiyle zarara uğramanız hâlinde zararın giderilmesini talep etme haklarına sahipsiniz.

6. BAŞVURU YÖNTEMİ

Yukarıdaki haklarınızı kullanmak için talebinizi, kimliğinizi tevsik edici belgelerle birlikte {sirket_adresi} adresine yazılı olarak veya {kep_adresi} KEP adresi üzerinden, güvenli elektronik imza ile iletebilirsiniz. Talebiniz, niteliğine göre en kısa sürede ve en geç 30 (otuz) gün içinde ücretsiz olarak sonuçlandırılır.

İletişim: {sirket_telefon} — {sirket_email}
TEXT;
    }

    private function explicitConsent(): string
    {
        return <<<'TEXT'
AÇIK RIZA METNİ

Son güncelleme: {bugun}

KVKK Aydınlatma Metni'nde açıklanan, {sirket_unvani} tarafından yürütülen temel üyelik ve Talep/Teklif eşleştirme hizmetinin sunulması için kişisel verilerimin işlenmesi zaten hizmetin ifası için gerekli olup bu işleme ayrıca rızam aranmamaktadır.

Bu metin, YALNIZCA aşağıda belirtilen, temel hizmetin sunulması için ZORUNLU OLMAYAN, isteğe bağlı veri işleme faaliyetleri içindir:

- Platform içi ve Platform dışı (üçüncü taraf reklam ağları dâhil) kişiselleştirilmiş içerik/kampanya önerisi sunulması amacıyla kullanım alışkanlıklarımın (görüntülenen ilan/kategori, arama geçmişi vb.) analiz edilmesi (profilleme),
- Platform'un iş ortaklarıyla (örn. ekspertiz, sigorta, finansman hizmeti sunan üçüncü taraflarla), yalnızca bana özel teklif sunulabilmesi amacıyla, ilgi alanlarımla sınırlı verilerimin paylaşılması,
- Anonimleştirilmemiş kullanım verilerimin, hizmet geliştirme amaçlı istatistiksel analiz ve raporlama çalışmalarında kullanılması.

Bu onayı vermemem veya dilediğim zaman geri almam, Platform'un temel hizmetlerinden (üyelik, Talep oluşturma, Teklif verme, mesajlaşma vb.) yararlanmamı hiçbir şekilde etkilemez.

Yukarıda açıklanan kapsamda kişisel verilerimin işlenmesine ve aktarılmasına açık rızam olduğunu onaylıyorum. Bu onayı istediğim zaman, Ayarlar sayfası üzerinden veya {sirket_email} adresine yazılı başvuruda bulunarak geri alabileceğimi biliyorum.
TEXT;
    }

    private function commercialMessageConsent(): string
    {
        return <<<'TEXT'
TİCARİ ELEKTRONİK İLETİ ONAYI

Son güncelleme: {bugun}

6563 sayılı Elektronik Ticaretin Düzenlenmesi Hakkında Kanun ve ilgili Ticari İletişim ve Ticari Elektronik İletiler Hakkında Yönetmelik uyarınca; {sirket_unvani} tarafından tarafıma kampanya, indirim, yeni ürün/hizmet duyurusu, anket ve benzeri pazarlama/tanıtım amaçlı ticari elektronik iletilerin (SMS, e-posta ve/veya sesli arama yoluyla) gönderilmesine onay veriyorum.

Bu onay, Platform'un işlem/hesap bildirimlerini (doğrulama kodu, teklif/talep durum bildirimleri, ödeme onayları vb.) almamı ETKİLEMEZ — bu tür bildirimler ticari ileti sayılmadığından zaten gönderilmeye devam eder.

Bu onayı vermemem, Platform'un temel hizmetlerinden yararlanmamı hiçbir şekilde etkilemez. Onayımı dilediğim zaman, Ayarlar sayfası üzerinden, gönderilen iletilerdeki "ret" bağlantısını kullanarak veya {sirket_telefon} / {sirket_email} üzerinden ücretsiz olarak geri alabilirim. Onayımın geri alınması talebim, mevzuatta öngörülen süre içinde işleme alınır.
TEXT;
    }
}
