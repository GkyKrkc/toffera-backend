<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Başvuru Hakkında Bilgi</title>
<style>
  body { margin: 0; padding: 0; background: #f4f4f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
  .wrapper { max-width: 560px; margin: 40px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
  .header { background: #1e3a5f; padding: 32px 40px; text-align: center; }
  .header h1 { color: #ffffff; margin: 0; font-size: 24px; letter-spacing: -0.5px; }
  .header span { color: #93c5fd; font-size: 13px; }
  .body { padding: 40px; }
  .body p { color: #374151; font-size: 15px; line-height: 1.7; margin: 0 0 16px; }
  .reason-box { background: #fef2f2; border-left: 3px solid #ef4444; border-radius: 6px; padding: 14px 18px; margin: 20px 0; }
  .reason-box p { margin: 0; font-size: 14px; color: #991b1b; }
  .support-box { background: #f9fafb; border-radius: 8px; padding: 16px 20px; margin-top: 20px; }
  .support-box p { margin: 0; font-size: 14px; color: #6b7280; }
  .footer { padding: 20px 40px; border-top: 1px solid #f3f4f6; text-align: center; font-size: 12px; color: #9ca3af; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="header">
    <h1>TOFFERA</h1>
    <span>Talep Odaklı Portföy Ağı</span>
  </div>
  <div class="body">
    <p>Merhaba <strong>{{ $user->name }}</strong>,</p>
    <p>Uzman başvurunuz incelendi. Ne yazık ki başvurunuz şu an için onaylanamadı.</p>

    <div class="reason-box">
      <p><strong>Sebep:</strong> {{ $reason }}</p>
    </div>

    <p>Eksik veya hatalı belgelerinizi düzelterek tekrar başvuru yapabilirsiniz.</p>

    <div class="support-box">
      <p>Sorularınız için destek ekibimize ulaşabilirsiniz:<br>
        <strong>destek@toffera.com</strong>
      </p>
    </div>
  </div>
  <div class="footer">
    © {{ date('Y') }} TOFFERA · Kahramanmaraş
  </div>
</div>
</body>
</html>