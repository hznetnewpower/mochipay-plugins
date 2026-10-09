# Required deployment acceptance

The supplied tests use fixtures and do not replace Windows/IIS/SQL or real Chrome acceptance.

- Back up; execute only DB21 upgrade on DB20; verify MP_BrowserConnection exists. Check repeat execution makes no destructive changes. Restore NuGet and rebuild WEB to DLL 1.0.80.5.
- Load the extension folder into actual Chrome 116+. Verify the Manifest V3 service worker runs without errors, popup opens, alarm is recreated after restarting Chrome and the one-minute check works with the popup closed.
- With an authorized merchant, issue a code, connect and verify the same code cannot connect again. Test expired codes and generating a replacement code. Verify no raw code/token is saved in SQL. Do not put credentials in diagnostic logs.
- With merchant A and merchant B, verify A cannot read B's orders, request B's QR or revoke B's connection. A disabled account, expired/revoked token or changed password must fail authentication. Logged-out or incorrect-CSRF dashboard POSTs must not issue or revoke a connection.
- With an active subscription and wallet, create a small crypto request and a fiat-priced request with a configured rate. Check the HPP amount/network/address, copied link and decoded QR link. Disabled methods, unavailable rates and invalid precision must be rejected. Physical-goods shipping is outside this release's popup.
- Interrupt a create response; recover with the same request. Repeat concurrently: one saved order, immutable payload, REQUEST_IN_PROGRESS or original result. Changed payload with the same request must produce REQUEST_ID_CONFLICT. Check expired subscription prevents new orders but permits reading prior orders.
- Enable optional notifications, verify the Chrome/OS permission, then use an owner-approved small real confirmed payment. Pending, underpaid, expired and review orders must not be shown as confirmed paid. One observed exact PAID transition should notify once; initial paid history should not. Restart worker/Chrome and check no duplicate.
- Disconnect successfully; token/order cache clears and the server connection is revoked. Simulate offline revoke: access is not falsely reported revoked. Use dashboard revocation if a browser is unavailable.
- Open landing, English setup/privacy pages and merchant entry; verify production HTTPS links and MIME serving. Keep ChromeAssistantStoreID blank until approved and publicly published. Then enter the exact approved store ID and test Install from Chrome Web Store.

Do not send real cryptocurrency as part of an automated fixture test or use production account secrets for store reviewers. The owner should create a dedicated review merchant and supply its verified login in the Chrome dashboard test-instructions field.

## 1.0.4 connection regression

Automated fixture checks reproduce the original worker startup exception with the notifications namespace absent and verify the fixed startup, late permission grant, persistent-tab sender restrictions, reopen/paste/Connect, stalled initial get, and no-reply timeout unlocking. These are runtime fixtures and rendered UI checks, not installed native Chrome acceptance. On real Chrome, test initial install with no notification grant; Get connection code -> dashboard -> return to persistent form -> paste -> Connect; then reload/restart and enable notifications.
