<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>E-posta Doğrulama</title>
<style>
  body { margin: 0; padding: 0; background: #f4f4f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
  .wrapper { max-width: 560px; margin: 40px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
  .header { background: #1e3a5f; padding: 32px 40px; text-align: center; }
  .header h1 { color: #ffffff; margin: 0; font-size: 24px; letter-spacing: -0.5px; }
  .header span { color: #93c5fd; font-size: 13px; }
  .body { padding: 40px; }
  .body p { color: #374151; font-size: 15px; line-height: 1.7; margin: 0 0 16px; }
  .btn { display: inline-block; margin: 24px 0; padding: 14px 36px; background: #2563eb; color: #ffffff !important; text-decoration: none; border-radius: 8px; font-size: 15px; font-weight: 600; }
  .note { font-size: 13px !important; color: #9ca3af !important; }
  .url-box { background: #f3f4f6; border-radius: 6px; padding: 10px 14px; font-size: 12px; color: #6b7280; word-break: break-all; margin-top: 8px; }
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
    <p>Merhaba <strong>{{ $userName }}</strong>,</p>
    <p>TOFFERA hesabınıza eklediğiniz e-posta adresini doğrulamak için aşağıdaki butona tıklayın.</p>

    <div style="text-align: center;">
      <a href="{{ $verificationUrl }}" class="btn">E-postamı Doğrula</a>
    </div>

    <p class="note">Bu link <strong>24 saat</strong> geçerlidir. Butona tıklayamıyorsanız aşağıdaki adresi tarayıcınıza yapıştırın:</p>
    <div class="url-box">{{ $verificationUrl }}</div>

    <p class="note" style="margin-top: 24px;">Bu e-postayı siz talep etmediyseniz güvenle görmezden gelebilirsiniz.</p>
  </div>
  <div class="footer">
    © {{ date('Y') }} TOFFERA · Kahramanmaraş
  </div>
</div>
</body>
</html>