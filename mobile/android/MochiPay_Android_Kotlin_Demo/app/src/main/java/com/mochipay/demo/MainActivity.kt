package com.mochipay.demo

import android.app.Activity
import android.app.AlertDialog
import android.app.Dialog
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.net.Uri
import android.content.Intent
import android.view.ViewGroup
import android.webkit.WebView
import android.webkit.WebViewClient
import android.webkit.WebResourceRequest
import android.widget.*
import org.json.JSONObject
import java.net.HttpURLConnection
import java.net.URL
import java.util.UUID
import java.util.concurrent.Executors

class MainActivity : Activity() {
    // Merchant backend, never the MochiPay API. Configure your own HTTPS staging origin.
    private val backend = Uri.parse("https://your-merchant.example")
    private val methods = listOf("USDT_TRC20", "USDC_ERC20", "BTC_BITCOIN", "ETH_ERC20", "SOL_SOLANA")
    private val languages = listOf("en", "zh", "es", "pt-br", "fr", "de", "nl", "fa", "ru", "ar")
    private val handler = Handler(Looper.getMainLooper())
    private val worker = Executors.newSingleThreadExecutor()
    private val prefs by lazy { getSharedPreferences("payment-demo", MODE_PRIVATE) }
    private lateinit var access: EditText
    private lateinit var method: Spinner
    private lateinit var mode: Spinner
    private lateinit var language: Spinner
    private lateinit var message: TextView
    private var busy = false
    private var active = false
    private var paymentDialog: Dialog? = null
    private fun sameOrigin(u: Uri): Boolean = u.scheme == "https" && u.host == backend.host && u.port == backend.port && u.userInfo == null
    private fun spinner(items: List<String>) = Spinner(this).apply { adapter = ArrayAdapter(this@MainActivity, android.R.layout.simple_spinner_dropdown_item, items) }
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        val layout = LinearLayout(this).apply { orientation = LinearLayout.VERTICAL; setPadding(32, 32, 32, 32) }
        fun label(text: String) { layout.addView(TextView(this).apply { this.text = text; textSize = 16f }) }
        label("MochiPay · merchant staging demo"); label(backend.toString())
        label("Staging access token (not API credentials)")
        access = EditText(this).apply { inputType = 129 }; layout.addView(access)
        label("Payment method"); method = spinner(methods); method.setSelection(methods.indexOf(prefs.getString("method", methods[0]) ?: methods[0]).coerceAtLeast(0)); layout.addView(method)
        label("Checkout mode"); mode = spinner(listOf("ON_SITE", "HPP")); layout.addView(mode)
        label("Buyer language"); language = spinner(languages); layout.addView(language)
        fun button(text: String, action: () -> Unit) { layout.addView(Button(this).apply { this.text = text; setOnClickListener { if (!busy) action() } }) }
        button("Create or recover payment") { create() }
        button("Open saved checkout") { open() }
        button("Check payment status") { check() }
        button("Start a new purchase") { AlertDialog.Builder(this).setTitle("New purchase?").setMessage("Your previous payment remains on the backend. Do not pay it twice.").setNegativeButton("Cancel", null).setPositiveButton("New purchase") { _, _ -> prefs.edit().clear().commit(); message.text = "Ready for a new purchase." }.show() }
        message = TextView(this).apply { text = "Configure the HTTPS backend and create a payment. This demo does not fulfill orders."; textSize = 16f }; layout.addView(message)
        setContentView(ScrollView(this).apply { addView(layout) })
    }
    private fun request(u: Uri, body: JSONObject? = null, token: String? = null): JSONObject {
        require(sameOrigin(u))
        val c = URL(u.toString()).openConnection() as HttpURLConnection
        try {
            c.instanceFollowRedirects = false; c.connectTimeout = 30000; c.readTimeout = 40000; c.useCaches = false
            if (body != null) { c.requestMethod = "POST"; c.doOutput = true; c.setRequestProperty("Content-Type", "application/json"); c.setRequestProperty("Authorization", "Bearer $token"); c.outputStream.use { it.write(body.toString().toByteArray(Charsets.UTF_8)) } }
            require(c.responseCode == 200)
            val text = c.inputStream.bufferedReader().use { it.readText() }; require(text.length <= 1048576)
            return JSONObject(text).also { require(it.optBoolean("success")) }
        } finally { c.disconnect() }
    }
    private fun work(action: () -> String) {
        if (busy) return
        busy = true; worker.execute { val result = try { action() } catch (_: Exception) { "Unable to verify or recover. Keep this request and method; check again." }; handler.post { if (!isDestroyed) { busy = false; message.text = result } } }
    }
    private fun create() {
        val m = methods[method.selectedItemPosition]; val previous = prefs.getString("method", null)
        if (previous != null && previous != m) { message.text = "Recover using the original method, or start a new purchase."; return }
        val r = prefs.getString("request", null) ?: UUID.randomUUID().toString()
        require(prefs.edit().putString("request", r).putString("method", m).commit())
        val token = access.text.toString()
        work {
            val d = request(backend.buildUpon().path("/payments").build(), JSONObject().put("request_id", r).put("payment_method", m), token)
            val u = Uri.parse(d.getString("checkout_url")).let { if (it.isRelative) backend.buildUpon().encodedPath(it.encodedPath).encodedQuery(it.encodedQuery).build() else it }
            require(sameOrigin(u) && u.path == "/checkout" && u.getQueryParameter("r") == r && !u.getQueryParameter("t").isNullOrEmpty())
            require(prefs.edit().putString("checkout", u.toString()).commit())
            "Payment saved. Both modes use the same order."
        }
    }
    private fun saved(): Uri? = prefs.getString("checkout", null)?.let { Uri.parse(it) }?.takeIf { sameOrigin(it) && it.path == "/checkout" }
    private fun open() {
        val original = saved() ?: run { message.text = "Create or recover first."; return }
        val selected = mode.selectedItem.toString()
        val u = original.buildUpon().clearQuery().appendQueryParameter("r", original.getQueryParameter("r")).appendQueryParameter("t", original.getQueryParameter("t")).appendQueryParameter("mode", selected).appendQueryParameter("lang", language.selectedItem.toString()).build()
        if (selected == "HPP") { try { startActivity(Intent(Intent.ACTION_VIEW, u)) } catch (_: Exception) { message.text = "No browser is available. Install or enable a browser." }; return }
        val web = WebView(this).apply {
            settings.javaScriptEnabled = true; settings.domStorageEnabled = true; settings.allowFileAccess = false; settings.allowContentAccess = false
            webViewClient = object : WebViewClient() { override fun shouldOverrideUrlLoading(view: WebView, request: WebResourceRequest): Boolean = !sameOrigin(request.url) }
        }
        val box = LinearLayout(this).apply { orientation = LinearLayout.VERTICAL }
        val dialog = Dialog(this); paymentDialog = dialog
        box.addView(Button(this).apply { text = "Close saved checkout"; setOnClickListener { dialog.dismiss() } })
        box.addView(web, LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, 0, 1f))
        dialog.setContentView(box); dialog.setOnDismissListener { web.stopLoading(); web.destroy(); paymentDialog = null; check() }
        dialog.show(); dialog.window?.setLayout(ViewGroup.LayoutParams.MATCH_PARENT, (resources.displayMetrics.heightPixels * 0.9).toInt())
        web.loadUrl(u.toString())
    }
    private fun check() {
        val u = saved() ?: return
        work {
            val status = u.buildUpon().path("/status").clearQuery().appendQueryParameter("r", u.getQueryParameter("r")).appendQueryParameter("t", u.getQueryParameter("t")).build()
            val d = request(status).getJSONObject("data")
            if (d.getString("status") == "PAID") "Payment verified by your server." else "Verified status: ${d.getString("status")} · ${d.getString("payAmount")} ${d.getString("asset")} / ${d.getString("network")}"
        }
    }
    private val poll = object : Runnable { override fun run() { if (active) { if (paymentDialog == null) check(); handler.postDelayed(this, 15000) } } }
    override fun onResume() { super.onResume(); active = true; handler.removeCallbacks(poll); handler.post(poll) }
    override fun onPause() { active = false; handler.removeCallbacks(poll); super.onPause() }
    override fun onDestroy() { active = false; handler.removeCallbacks(poll); paymentDialog?.dismiss(); worker.shutdownNow(); super.onDestroy() }
}
