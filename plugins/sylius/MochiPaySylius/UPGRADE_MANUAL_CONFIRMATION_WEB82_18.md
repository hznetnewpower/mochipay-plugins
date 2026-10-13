# WEB82.18 manual payment acceptance update

Upgrade in place; preserve gateway settings and all existing attempt/order records. Do not uninstall.
After a merchant or administrator explicitly accepts an UNDERPAID/OVERPAID payment in MochiPay, an authenticated Query Order response may include the Boolean manual_payment_accepted=true.
The plugin still verifies the original system order, merchant reference, fiat amount/currency, asset/network, payment address and payable amount before processing PAID. A received amount must be valid and positive. A string "true", number 1, callback field, browser flag or PAID status alone cannot authorize a mismatch.
Exact paid amounts continue to work on older servers; a mismatched amount without authenticated acceptance remains blocked. Use WEB82.14+ (WEB82.18 recommended) for acceptance proof.
Manual callback resends the original notification. Preserve the native duplicate-payment guards; never create a new checkout to resend a callback.
Validation in this package is source/offline fixture validation, not a claim of native store installation or real-payment acceptance for every supported core/PHP combination.
