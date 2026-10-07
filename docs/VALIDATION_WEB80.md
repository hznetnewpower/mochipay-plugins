# Validation and remaining acceptance

Four new backends passed 264 local fixture assertions and 220 exact-byte signature checks. C# used local Mono with .NET4.6.1 references; Java compiled and ran on JDK17. Node/Python used their installed runtimes. No live payment was made.

PHP1.1.4 passed syntax/source checks, without an available PHP runtime. Mobile project/contract checks are source checks; no Xcode or Android SDK device/simulator build was available. Complete those native checks and a small actual staging payment before production. Windows VS2019/IIS/HttpListener URL ACL and callback reachability remain deployment acceptance.

This publication includes no MochiPay payment-system/Monitor/database/private configuration source. Native plugin package payment behavior is unchanged. Existing installed integrations remain compatible.
