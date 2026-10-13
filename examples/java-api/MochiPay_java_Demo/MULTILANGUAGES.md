# Buyer languages

The payment popup supports multiple languages, including English, Chinese, Spanish, and more, with RTL layout where appropriate. Explicit lang takes priority, then saved/browser preferences, then English. The language selector only changes presentation; amount, address, identity, mode and server verification do not change. Instructions and staging controls remain English. HPP receives the selected language on the same order.

WEB81 buyer language update: en, zh, es, pt-br, fr, de, nl, fa, ru, ar, ja, ko, it, tr, id. Merchant setup and code identifiers remain English. Update installed frontend assets and clear the store cache; HPP also requires the WEB81 host update.
