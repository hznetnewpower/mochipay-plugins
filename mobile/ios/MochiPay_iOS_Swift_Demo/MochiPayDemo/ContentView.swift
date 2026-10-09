import SwiftUI
import WebKit
import UIKit

// Merchant backend only. Never put MochiPay API credentials in an app.
enum DemoConfig {
    static let backend = URL(string: "https://your-merchant.example")!
    static let methods = ["USDT_TRC20", "USDC_ERC20", "BTC_BITCOIN", "ETH_ERC20", "SOL_SOLANA"]
    static let languages = ["en", "zh", "es", "pt-br", "fr", "de", "nl", "fa", "ru", "ar","ja","ko","it","tr","id"]
    static func sameOrigin(_ url: URL) -> Bool {
        url.scheme == "https" && url.host == backend.host && url.port == backend.port && url.user == nil && url.password == nil
    }
}

struct CreatedPayment: Decodable { let success: Bool; let checkout_url: String }
struct PaymentView: Decodable { let status: String; let payAmount: String; let asset: String; let network: String }
struct StatusResponse: Decodable { let success: Bool; let data: PaymentView }

@MainActor
final class PaymentModel: ObservableObject {
    @Published var method = DemoConfig.methods[0]
    @Published var mode = "ON_SITE"
    @Published var language = "en"
    @Published var accessToken = ""
    @Published var message = "Configure your merchant HTTPS backend, then create a demo payment."
    @Published var busy = false
    @Published var popup: URL?
    private(set) var checkout: URL?
    private let defaults = UserDefaults.standard

    init() {
        if let saved = defaults.string(forKey: "demoCheckout"), let u = URL(string: saved), DemoConfig.sameOrigin(u), u.path == "/checkout" { checkout = u }
        if let saved = defaults.string(forKey: "demoMethod"), DemoConfig.methods.contains(saved) { method = saved }
    }

    func createOrRecover(retry: Bool = false) async {
        guard !busy else { return }
        busy = true; defer { busy = false }
        do {
            guard DemoConfig.sameOrigin(DemoConfig.backend) else { throw URLError(.badURL) }
            let savedMethod = defaults.string(forKey: "demoMethod")
            guard !retry || savedMethod == nil || savedMethod == method else { message = "Recover with the original method, or start a new purchase."; return }
            let id = retry ? (defaults.string(forKey: "demoRequestID") ?? UUID().uuidString.lowercased()) : UUID().uuidString.lowercased()
            defaults.set(id, forKey: "demoRequestID"); defaults.set(method, forKey: "demoMethod")
            var request = URLRequest(url: DemoConfig.backend.appendingPathComponent("payments"))
            request.httpMethod = "POST"; request.timeoutInterval = 40
            request.setValue("application/json", forHTTPHeaderField: "Content-Type")
            // Staging credential only. Replace with your app's user session in production.
            request.setValue("Bearer " + accessToken, forHTTPHeaderField: "Authorization")
            request.httpBody = try JSONSerialization.data(withJSONObject: ["request_id": id, "payment_method": method])
            let (bytes, response) = try await URLSession.shared.data(for: request)
            guard (response as? HTTPURLResponse)?.statusCode == 200 else { throw URLError(.badServerResponse) }
            let result = try JSONDecoder().decode(CreatedPayment.self, from: bytes)
            guard result.success, let u = URL(string: result.checkout_url, relativeTo: DemoConfig.backend)?.absoluteURL, DemoConfig.sameOrigin(u), u.path == "/checkout", let c = URLComponents(url: u, resolvingAgainstBaseURL: false), c.queryItems?.first(where: { $0.name == "r" })?.value == id, c.queryItems?.first(where: { $0.name == "t" })?.value?.isEmpty == false else { throw URLError(.badURL) }
            checkout = u; defaults.set(u.absoluteString, forKey: "demoCheckout")
            message = "Payment saved. Open either interface for this same order."
        } catch { message = "Unable to create or recover. Keep this request and method, then try again." }
    }

    func open() {
        guard let checkout = checkout, var c = URLComponents(url: checkout, resolvingAgainstBaseURL: false) else { message = "Create or recover first."; return }
        c.queryItems = (c.queryItems ?? []).filter { !["mode", "lang"].contains($0.name) } + [URLQueryItem(name: "mode", value: mode), URLQueryItem(name: "lang", value: language)]
        guard let u = c.url, DemoConfig.sameOrigin(u) else { return }
        if mode == "ON_SITE" { popup = u } else { UIApplication.shared.open(u) }
    }

    func check() async {
        guard !busy, let checkout = checkout, var c = URLComponents(url: checkout, resolvingAgainstBaseURL: false) else { return }
        busy = true; defer { busy = false }
        do {
            c.path = "/status"; c.queryItems = c.queryItems?.filter { ["r", "t"].contains($0.name) }
            guard let u = c.url, DemoConfig.sameOrigin(u) else { throw URLError(.badURL) }
            var request = URLRequest(url: u); request.cachePolicy = .reloadIgnoringLocalCacheData; request.timeoutInterval = 40
            let (bytes, response) = try await URLSession.shared.data(for: request)
            guard (response as? HTTPURLResponse)?.statusCode == 200 else { throw URLError(.badServerResponse) }
            let result = try JSONDecoder().decode(StatusResponse.self, from: bytes)
            guard result.success else { throw URLError(.badServerResponse) }
            message = result.data.status == "PAID" ? "Payment verified by your server." : "Verified status: \(result.data.status) · \(result.data.payAmount) \(result.data.asset) / \(result.data.network)"
        } catch { message = "Unable to verify. Your saved payment is kept; check again." }
    }

    func newPurchase() {
        for key in ["demoRequestID", "demoMethod", "demoCheckout"] { defaults.removeObject(forKey: key) }
        checkout = nil; popup = nil; message = "Ready for a new purchase. Do not pay your previous order twice."
    }
}

struct ContentView: View {
    @StateObject private var model = PaymentModel()
    @Environment(\.scenePhase) private var phase
    @State private var confirmNew = false
    private let timer = Timer.publish(every: 15, on: .main, in: .common).autoconnect()
    var body: some View {
        NavigationView {
            Form {
                Section(header: Text("Merchant staging backend")) {
                    Text(DemoConfig.backend.absoluteString).font(.footnote)
                    SecureField("Staging access token", text: $model.accessToken)
                    Picker("Payment method", selection: $model.method) { ForEach(DemoConfig.methods, id: \.self) { Text($0) } }
                    Picker("Checkout mode", selection: $model.mode) { Text("ON_SITE").tag("ON_SITE"); Text("HPP").tag("HPP") }
                    Picker("Buyer language", selection: $model.language) { ForEach(DemoConfig.languages, id: \.self) { Text($0) } }
                    Button("Create payment") { Task { await model.createOrRecover() } }.disabled(model.busy)
                    Button("Retry current payment request") { Task { await model.createOrRecover(retry: true) } }.disabled(model.busy)
                    Button("Open saved checkout") { model.open() }.disabled(model.busy)
                    Button("Check payment status") { Task { await model.check() } }.disabled(model.busy)
                }
                Section(header: Text("Server verification")) { Text(model.message); Text("Closing checkout keeps your order. Payment is verified on the merchant server; this demo does not fulfill goods.").font(.footnote) }
                Button("Start a new purchase") { confirmNew = true }.disabled(model.busy)
            }.navigationTitle("MochiPay Demo")
        }
        .sheet(isPresented: Binding(get: { model.popup != nil }, set: { if !$0 { model.popup = nil } })) {
            if let url = model.popup { NavigationView { MerchantWebView(url: url).navigationTitle("Saved payment").toolbar { Button("Close") { model.popup = nil; Task { await model.check() } } } } }
        }
        .alert("Start a new purchase?", isPresented: $confirmNew) { Button("Cancel", role: .cancel) { }; Button("New purchase") { model.newPurchase() } } message: { Text("The previous payment remains saved on your backend. Do not pay it twice.") }
        .onChange(of: phase) { p in if p == .active { Task { await model.check() } } }
        .onReceive(timer) { _ in if phase == .active && model.popup == nil { Task { await model.check() } } }
    }
}

struct MerchantWebView: UIViewRepresentable {
    let url: URL
    func makeCoordinator() -> Coordinator { Coordinator() }
    func makeUIView(context: Context) -> WKWebView {
        let view = WKWebView(); view.navigationDelegate = context.coordinator; view.load(URLRequest(url: url)); return view
    }
    func updateUIView(_ uiView: WKWebView, context: Context) { }
    final class Coordinator: NSObject, WKNavigationDelegate {
        func webView(_ webView: WKWebView, decidePolicyFor action: WKNavigationAction, decisionHandler: @escaping (WKNavigationActionPolicy) -> Void) {
            guard let url = action.request.url, DemoConfig.sameOrigin(url) else { decisionHandler(.cancel); return }
            decisionHandler(.allow)
        }
    }
}
