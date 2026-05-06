<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
</head>
<body style="margin:0;padding:16px;background:#f5f5f5;font-family:system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;font-size:15px;line-height:1.6;color:#222;">
<div style="max-width:640px;margin:0 auto;background:#fff;padding:24px;border-radius:8px;border:1px solid #e0e0e0;">
    <div style="white-space:pre-wrap;word-break:break-word;">{!! $htmlBody !!}</div>
</div>
</body>
</html>
