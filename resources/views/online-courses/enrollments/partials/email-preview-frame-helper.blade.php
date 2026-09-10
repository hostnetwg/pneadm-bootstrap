@once
    @push('scripts')
    <script>
        window.PneSetEmailPreviewFrame = function (frameEl, html) {
            if (!frameEl) {
                return;
            }
            if (frameEl._blobUrl) {
                URL.revokeObjectURL(frameEl._blobUrl);
                frameEl._blobUrl = null;
            }
            frameEl.removeAttribute('srcdoc');
            var source = html || '<p style="font-family:Arial,sans-serif;padding:1rem;">Brak treści wiadomości.</p>';
            try {
                var blob = new Blob([source], { type: 'text/html;charset=utf-8' });
                frameEl._blobUrl = URL.createObjectURL(blob);
                frameEl.src = frameEl._blobUrl;
            } catch (e) {
                frameEl.src = 'data:text/html;charset=utf-8,' + encodeURIComponent(source);
            }
        };
    </script>
    @endpush
@endonce
