// Keep the Google tag unloaded until an explicit, unexpired choice is available.
window.googleAnalyticsConsent = (id) => ({
    open: false,
    init() {
        try {
            const choice = JSON.parse(localStorage.getItem(`ga-consent:${id || 'privacy-notice'}`));
            if (choice && typeof choice.accepted === 'boolean' && choice.expires > Date.now()) {
                if (choice.accepted && id) this.load();
                return;
            }
        } catch (_) { /* Show choices when storage is unavailable. */ }
        this.open = true;
    },
    choose(accepted) {
        try {
            localStorage.setItem(`ga-consent:${id || 'privacy-notice'}`, JSON.stringify({
                accepted, expires: Date.now() + 180 * 24 * 60 * 60 * 1000,
            }));
        } catch (_) { /* The current page still honors the choice. */ }
        this.open = false;
        if (!id) return;
        window[`ga-disable-${id}`] = !accepted;
        if (accepted) {
            this.load();
        } else {
            if (window.gtag) window.gtag('consent', 'update', { analytics_storage: 'denied' });
            // Remove first-party GA cookies, including cookies set on a parent domain.
            for (const entry of document.cookie.split(';')) {
                const name = entry.split('=')[0].trim();
                if (name !== '_ga' && !name.startsWith('_ga_') && name !== '_gid' && !name.startsWith('_gat')) continue;
                document.cookie = `${name}=; Max-Age=0; path=/`;
                const labels = location.hostname.split('.');
                for (let i = 0; i < labels.length - 1; i++) {
                    document.cookie = `${name}=; Max-Age=0; path=/; domain=${labels.slice(i).join('.')}`;
                }
            }
        }
    },
    load() {
        window[`ga-disable-${id}`] = false;
        window.dataLayer = window.dataLayer || [];
        window.gtag = window.gtag || function () { window.dataLayer.push(arguments); };
        window.gtag('consent', 'default', {
            analytics_storage: 'denied', ad_storage: 'denied',
            ad_user_data: 'denied', ad_personalization: 'denied',
        });
        window.gtag('consent', 'update', {
            analytics_storage: 'granted', ad_storage: 'denied',
            ad_user_data: 'denied', ad_personalization: 'denied',
        });
        if (document.getElementById('blog-google-analytics')) return;
        window.gtag('js', new Date());
        window.gtag('config', id, { allow_google_signals: false, allow_ad_personalization_signals: false });
        const script = document.createElement('script');
        script.id = 'blog-google-analytics';
        script.async = true;
        script.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(id)}`;
        document.head.appendChild(script);
    },
});
