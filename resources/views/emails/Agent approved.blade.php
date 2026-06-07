<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Başvuru Onaylandı</title>
<style>
  body { margin: 0; padding: 0; background: #f4f4f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
  .wrapper { max-width: 560px; margin: 40px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
  .header { background: #1e3a5f; padding: 32px 40px; text-align: center; }
  .header h1 { color: #ffffff; margin: 0; font-size: 24px; letter-spacing: -0.5px; }
  .header span { color: #93c5fd; font-size: 13px; }
  .badge { display: inline-block; margin-top: 16px; padding: 6px 18px; background: #d1fae5; color: #065f46; border-radius: 20px; font-size: 13px; font-weight: 600; }
  .body { padding: 40px; }
  .body p { color: #374151; font-size: 15px; line-height: 1.7; margin: 0 0 16px; }
  .info-box { background: #eff6ff; border-left: 3px solid #2563eb; border-radius: 6px; padding: 14px 18px; margin: 20px 0; }
  .info-box p { margin: 0; font-size: 14px; color: #1e40af; }
  .btn { display: inline-block; margin: 8px 0; padding: 14px 36px; background: #2563eb; color: #ffffff !important; text-decoration: none; border-radius: 8px; font-size: 15px; font-weight: 600; }
  .footer { padding: 20px 40px; border-top: 1px solid #f3f4f6; text-align: center; font-size: 12px; color: #9ca3af; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="header">
    <h1>TOFFERA</h1>
    <span>Talep Odaklı Portföy Ağı</span>
    <div><span class="badge">✓ Başvurunuz Onaylandı</span></div>
  </div>
  <div class="body">
    <p>Merhaba <strong>{{ $user->name }}</strong>,</p>
    <p>
      {{ $user->company_name ? $user->company_name . ' adına yaptığınız' : 'Yaptığınız' }}
      uzman başvurusu incelendi ve <strong>onaylandı</strong>. Artık TOFFERA'da taleplere teklif verebilirsiniz.
    </p>

    <div class="info-box">
      <p>🏢 Hesap türü: <strong>
        @switch($user->agent_type)
          @case('emlakci') Emlakçı @break
          @case('galerici') Galerici @break
          @case('her_ikisi') Emlakçı + Galerici @break
        @endswitch
      </strong></p>
    </div>

    <div style="text-align: center; margin-top: 28px;">
      <a href="{{ config('app.frontend_url') }}/dashboard" class="btn">Panele Git</a>
    </div>
  </div>
  <div class="footer">
    © {{ date('Y') }} TOFFERA · Kahramanmaraş
  </div>
</div>
</body>
</html>