# MochiPay Payment Assistant 1.0.4

Requires WEB82.6 or newer / DB21 on https://mochi.bz and desktop Chrome 116+. Deploy the matching WEB release, then load this `extension` directory using chrome://extensions → Developer mode → Load unpacked. Generate a one-time code in Merchant Dashboard → Chrome Payment Assistant. No API Secret belongs in the extension.

Features: create HPP payment requests, copy payment links, display a payment-link QR, recent orders, optional payment-status notifications and dashboard access. The popup supports en/zh/es/pt-BR/fr/de/nl/fa/ru/ar/ja/ko/it/tr/id with explicit choice and browser preference fallback. Setup documentation is English.

All executable code is packaged locally. API JSON and a QR PNG from https://mochi.bz are data only. No remote code, content scripts or third-party analytics are used.

`CHROME_STORE_GUIDE_zh.txt` contains the owner's submission steps. `STORE_LISTING_EN.txt` and `PRIVACY_FIELDS_EN.txt` contain copy-ready fields. `store-assets` has three UI/demo screenshots, a store icon and promotional image. Screenshots use clearly marked fixture data; they are not real payment evidence.

Do not upload the full website source or outer store-kit ZIP. Upload the extension-only ZIP with manifest.json at its root.

## 1.0.4 independent creation

Each explicit Create payment click uses a new request_id and creates its own invoice, even when an earlier attempt remains unpaid or uncertain. Retry current request resolves only its saved request_id with the identical payload. Refresh/status and sharing an existing link are read-only. Existing settings and connection are preserved. HPP cancel returns to a saved merchant destination when configured; return never proves payment.

Upgrade the extension in place by reloading the packaged extension folder. This release adds no permissions or DB SQL; Monitor15 is unchanged. The earlier connection/startup repair remains included.
