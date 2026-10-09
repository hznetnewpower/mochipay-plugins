# MochiPay Android / Kotlin Demo 1.0.2 — ON_SITE + HPP

Android Studio supporting AGP 8.9.1, JDK 17, Gradle 8.11.1, Android SDK 35; device/emulator Android 8.0 / API 26+.

## Start

1. Run one of the matching Node.js, Python, C# / .NET Framework or Java backend demos. Configure its API credentials, active wallet, private file storage and random staging access token. Expose it through HTTPS and set APP_PUBLIC_URL to that origin.
2. Edit `app/src/main/java/com/mochipay/demo/MainActivity.kt → backend` to your HTTPS merchant origin. Do not use the MochiPay API as the app backend. Do not put a MochiPay API key/secret in this project.
3. Open this directory in Android Studio. Use Gradle 8.11.1 (local Gradle installation or generate a wrapper with gradle wrapper --gradle-version 8.11.1), sync, then Run on a device/emulator. The ZIP contains source, not an APK or bundled Gradle distribution.
4. Enter the backend's staging access token in the app. This is a separate demo access credential, held only in memory; replace it with your authenticated user-session flow for production. Choose a configured method and language; ON_SITE is selected by default.
5. Tap Create or recover payment. Both interfaces use the same order. ON_SITE loads your merchant checkout page in a native WebView with its payment dialog; HPP opens your merchant HPP redirect route in the external browser. On return to the app, re-query your backend. Browser/app return alone never marks it paid.

## Backend contract and asynchronous completion

POST /payments with Authorization: Bearer STAGING_TOKEN and JSON request_id/payment_method. The backend owns amount, currency, unique amount direction and callback URLs. It returns a saved merchant-local checkout_url; the app validates the HTTPS origin and /checkout path before using it.

GET /status with the checkout URL's r/t capability returns a minimal safe payment DTO (decimal strings). The backend authenticates to MochiPay, checks the saved order binding and received amount, then returns PAID only when verified. The app never calculates payment success from a local WebView event. It polls at most once every15 seconds while active and no request is in flight; background polling stops. Closing checkout keeps the saved request_id and capability URL.

HPP synchronous return uses redirect_url to the merchant /complete page. HPP and ON_SITE asynchronous completion use notify_url to POST /callback. Callback verification and idempotent local updates run on the backend even if the app is closed. Polling updates only the display. The demo records paid_verified once but does not fulfill orders. Implement production fulfillment atomically in your existing order database.

The saved request_id and original method survive app restarts. Recover using the same method and payload. Start a new purchase only for a genuinely new order. Never delete a saved attempt after a network timeout. Changing mode/language does not recreate an order.

The saved capability URL is sensitive. This sample saves it in app-local preferences for staging recovery; use your authenticated account/order model and protected storage for production. Do not log it, attach analytics or share it publicly. Navigation in the ON_SITE WebView is restricted to the configured HTTPS merchant origin; no JavaScript-to-native payment-success bridge or SSL-error bypass is installed.

## Languages and distribution

The app demo controls and instructions remain English. The payment dialog offers English, Chinese, Spanish, Brazilian Portuguese, French, German, Dutch, Persian, Russian, Arabic, Japanese, Korean, Italian, Turkish and Indonesian with RTL. HPP receives the chosen language on the same hosted order.

Check current Apple App Store / Google Play billing rules for your products, storefronts and region. Digital content/subscriptions may require platform billing; this integration is not a substitute for store approval. This is a staging source demo, not a production app or a native payment SDK. Add your own account authentication, accessibility, branding and app-store resources before distribution.

## Validation

See TESTING.md for local checks and limitations. Native device/simulator build and real payment acceptance must be completed in Xcode/Android Studio; this release does not claim device or app-store certification.
