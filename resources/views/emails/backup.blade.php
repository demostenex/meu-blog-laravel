<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        h1 { color: #1a1a2e; border-bottom: 2px solid #6c63ff; padding-bottom: 8px; }
        .info-box { background: #f0f4ff; border-left: 4px solid #6c63ff; padding: 12px 16px; border-radius: 4px; margin: 16px 0; }
        .section { margin: 24px 0; }
        .section h2 { font-size: 16px; color: #555; margin-bottom: 8px; }
        .attachments { background: #f9f9f9; border: 1px solid #ddd; border-radius: 6px; padding: 12px 16px; }
        .attachment-item { display: flex; align-items: center; margin: 6px 0; font-size: 14px; }
        .videos { margin-top: 8px; }
        .video-url { display: block; font-size: 13px; color: #6c63ff; word-break: break-all; margin: 4px 0 4px 16px; }
        .footer { margin-top: 32px; font-size: 12px; color: #aaa; border-top: 1px solid #eee; padding-top: 12px; }
    </style>
</head>
<body>
    <h1>🔒 Backup do Blog</h1>

    <div class="info-box">
        Backup gerado automaticamente em <strong>{{ now()->format('d/m/Y \à\s H:i:s') }}</strong>.
    </div>

    <div class="section">
        <h2>📎 Arquivos Anexados</h2>
        <div class="attachments">
            <div class="attachment-item">💾 <strong>&nbsp;banco_{{ $timestamp }}.sql.gz</strong> — Dump completo do PostgreSQL (comprimido)</div>
            <div class="attachment-item">🖼️ <strong>&nbsp;imagens_{{ $timestamp }}.zip</strong> — Capas, perfis, avatares e imagens dos posts</div>
        </div>
    </div>

    @if(count($videoUrls) > 0)
    <div class="section">
        <h2>🎬 Vídeos (URLs Absolutas)</h2>
        <p style="font-size:13px;color:#666;">
            Os vídeos são pesados demais para anexar. Salve os links abaixo enquanto o servidor estiver online:
        </p>
        <div class="videos">
            @foreach($videoUrls as $url)
                <a class="video-url" href="{{ $url }}">{{ $url }}</a>
            @endforeach
        </div>
    </div>
    @endif

    <div class="footer">
        Gerado pelo comando <code>php artisan backup:run</code> — Blog demostenesalbert.com.br
    </div>
</body>
</html>
