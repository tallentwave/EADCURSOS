package br.com.eadcursos.app;

import android.app.Activity;
import android.app.DownloadManager;
import android.content.ActivityNotFoundException;
import android.content.Intent;
import android.content.pm.ActivityInfo;
import android.graphics.Color;
import android.net.Uri;
import android.os.Bundle;
import android.os.Environment;
import android.print.PrintAttributes;
import android.print.PrintDocumentAdapter;
import android.print.PrintManager;
import android.view.View;
import android.view.ViewGroup;
import android.view.WindowManager;
import android.webkit.CookieManager;
import android.webkit.JavascriptInterface;
import android.webkit.URLUtil;
import android.webkit.ValueCallback;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceError;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.webkit.RenderProcessGoneDetail;
import android.widget.FrameLayout;
import android.widget.Toast;

/**
 * Casca Android da plataforma EAD: carrega o próprio site num WebView.
 * Tudo que o site já faz (login, cursos, provas, certificado, carteirinha) continua valendo;
 * aqui só ligamos o que o WebView não faz sozinho: tela cheia de vídeo, envio de foto,
 * downloads, impressão/PDF, links externos (Zoom/Meet) e tela de "sem conexão".
 */
public class MainActivity extends Activity {

    private static final int REQ_FILE = 1001;
    private static final String HOME = BuildConfig.SITE_URL + "/home.php";

    private FrameLayout root;
    private WebView web;
    private WebChromeClient chrome;
    private View customView;
    private WebChromeClient.CustomViewCallback customCallback;
    private ValueCallback<Uri[]> filePathCallback;
    private String baseHost;

    @Override
    protected void onCreate(Bundle state) {
        super.onCreate(state);
        if (BuildConfig.SECURE_SCREEN) {
            getWindow().setFlags(WindowManager.LayoutParams.FLAG_SECURE, WindowManager.LayoutParams.FLAG_SECURE);
        }
        baseHost = Uri.parse(BuildConfig.SITE_URL).getHost();

        root = new FrameLayout(this);
        root.setBackgroundColor(Color.BLACK);
        web = new WebView(this);
        web.setBackgroundColor(Color.BLACK);
        root.addView(web, new FrameLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.MATCH_PARENT));
        setContentView(root);

        configureWebView();

        if (state == null || web.restoreState(state) == null) {
            web.loadUrl(startUrl(getIntent()));
        }
    }

    private String startUrl(Intent intent) {
        Uri d = intent == null ? null : intent.getData();
        return (d != null && isInternal(d)) ? d.toString() : HOME;
    }

    private void configureWebView() {
        WebSettings s = web.getSettings();
        s.setJavaScriptEnabled(true);
        s.setDomStorageEnabled(true);
        s.setMediaPlaybackRequiresUserGesture(false);
        s.setAllowFileAccess(false);
        s.setSupportMultipleWindows(false);
        s.setMixedContentMode(WebSettings.MIXED_CONTENT_NEVER_ALLOW);
        s.setUserAgentString(s.getUserAgentString() + " EADApp/1.0"); // o servidor pode reconhecer o app por este sufixo

        CookieManager cookies = CookieManager.getInstance();
        cookies.setAcceptCookie(true);
        cookies.setAcceptThirdPartyCookies(web, true); // necessário para o player do YouTube

        web.addJavascriptInterface(new Bridge(), "AndroidApp");

        web.setWebViewClient(new WebViewClient() {
            @Override
            public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) {
                if (!request.isForMainFrame()) return false; // iframes (YouTube/Vimeo) seguem normalmente
                return handleUrl(request.getUrl());
            }

            @Override
            public void onReceivedError(WebView view, WebResourceRequest request, WebResourceError error) {
                if (request.isForMainFrame()) view.loadUrl("file:///android_asset/offline.html");
            }

            @Override
            public boolean onRenderProcessGone(WebView view, RenderProcessGoneDetail detail) {
                recreate(); // o processo do WebView caiu (pouca memória): reinicia a tela em vez de fechar o app
                return true;
            }
        });

        chrome = new WebChromeClient() {
            @Override
            public void onShowCustomView(View view, CustomViewCallback callback) {
                if (customView != null) { callback.onCustomViewHidden(); return; }
                customView = view;
                customCallback = callback;
                web.setVisibility(View.GONE);
                root.addView(view, new FrameLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.MATCH_PARENT));
                immersive(true);
                setRequestedOrientation(ActivityInfo.SCREEN_ORIENTATION_SENSOR_LANDSCAPE);
            }

            @Override
            public void onHideCustomView() {
                if (customView == null) return;
                root.removeView(customView);
                customView = null;
                web.setVisibility(View.VISIBLE);
                immersive(false);
                setRequestedOrientation(ActivityInfo.SCREEN_ORIENTATION_UNSPECIFIED);
                if (customCallback != null) customCallback.onCustomViewHidden();
                customCallback = null;
            }

            @Override
            public boolean onShowFileChooser(WebView view, ValueCallback<Uri[]> callback, FileChooserParams params) {
                if (filePathCallback != null) filePathCallback.onReceiveValue(null);
                filePathCallback = callback;
                try {
                    startActivityForResult(params.createIntent(), REQ_FILE);
                } catch (ActivityNotFoundException e) {
                    filePathCallback = null;
                    Toast.makeText(MainActivity.this, "Não foi possível abrir o seletor de arquivos.", Toast.LENGTH_LONG).show();
                    return false;
                }
                return true;
            }
        };
        web.setWebChromeClient(chrome);

        // PDFs e imagens do site (certificado, carteirinha, exportação) saem como "anexo": baixa para a pasta Downloads
        web.setDownloadListener((url, userAgent, contentDisposition, mimeType, length) -> {
            try {
                String name = URLUtil.guessFileName(url, contentDisposition, mimeType);
                DownloadManager.Request req = new DownloadManager.Request(Uri.parse(url));
                String cookie = CookieManager.getInstance().getCookie(url);
                if (cookie != null) req.addRequestHeader("Cookie", cookie);
                req.addRequestHeader("User-Agent", userAgent);
                req.setMimeType(mimeType);
                req.setTitle(name);
                req.setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED);
                req.setDestinationInExternalPublicDir(Environment.DIRECTORY_DOWNLOADS, name);
                ((DownloadManager) getSystemService(DOWNLOAD_SERVICE)).enqueue(req);
                Toast.makeText(this, "Baixando " + name + " (pasta Downloads)", Toast.LENGTH_LONG).show();
            } catch (Exception e) {
                Toast.makeText(this, "Não foi possível baixar o arquivo.", Toast.LENGTH_LONG).show();
            }
        });
    }

    /** Páginas do próprio site (e o checkout do Mercado Pago) ficam dentro do app; o resto abre fora. */
    private boolean isInternal(Uri u) {
        String h = u.getHost();
        if (h == null) return false;
        if (h.equalsIgnoreCase(baseHost)) return true;
        h = h.toLowerCase();
        return h.endsWith("mercadopago.com") || h.endsWith("mercadopago.com.br") || h.endsWith("mercadolivre.com.br")
                || h.endsWith("mercadolibre.com") || h.endsWith("mlstatic.com");
    }

    /** @return true se o app tratou a URL (não deve carregar no WebView). */
    private boolean handleUrl(Uri u) {
        String scheme = u.getScheme() == null ? "" : u.getScheme().toLowerCase();
        if (scheme.equals("http") || scheme.equals("https")) {
            if (isInternal(u)) return false;
            openExternal(new Intent(Intent.ACTION_VIEW, u));  // Zoom, Meet, YouTube ao vivo, WhatsApp...
            return true;
        }
        if (scheme.equals("file") || scheme.equals("about") || scheme.equals("data") || scheme.equals("javascript")) return false;
        try {
            Intent i = scheme.equals("intent") ? Intent.parseUri(u.toString(), Intent.URI_INTENT_SCHEME) : new Intent(Intent.ACTION_VIEW, u);
            i.setComponent(null);
            i.setSelector(null);
            i.addCategory(Intent.CATEGORY_BROWSABLE);
            openExternal(i);
        } catch (Exception e) {
            Toast.makeText(this, "Link não suportado.", Toast.LENGTH_SHORT).show();
        }
        return true;
    }

    private void openExternal(Intent i) {
        try {
            startActivity(i);
        } catch (ActivityNotFoundException e) {
            Toast.makeText(this, "Nenhum aplicativo encontrado para abrir este link.", Toast.LENGTH_LONG).show();
        }
    }

    @SuppressWarnings("deprecation")
    private void immersive(boolean on) {
        getWindow().getDecorView().setSystemUiVisibility(on
                ? View.SYSTEM_UI_FLAG_FULLSCREEN | View.SYSTEM_UI_FLAG_HIDE_NAVIGATION | View.SYSTEM_UI_FLAG_IMMERSIVE_STICKY
                    | View.SYSTEM_UI_FLAG_LAYOUT_STABLE | View.SYSTEM_UI_FLAG_LAYOUT_FULLSCREEN | View.SYSTEM_UI_FLAG_LAYOUT_HIDE_NAVIGATION
                : View.SYSTEM_UI_FLAG_VISIBLE);
    }

    /** Funções que as páginas do site podem chamar (window.AndroidApp.*). */
    private class Bridge {
        /** window.print() não existe no WebView: abre a impressão do Android (inclui "Salvar como PDF"). */
        @JavascriptInterface
        public void print() {
            runOnUiThread(() -> {
                PrintManager pm = (PrintManager) getSystemService(PRINT_SERVICE);
                PrintDocumentAdapter adapter = web.createPrintDocumentAdapter("documento");
                PrintAttributes attrs = new PrintAttributes.Builder()
                        .setMediaSize(PrintAttributes.MediaSize.ISO_A4.asLandscape())
                        .setMinMargins(PrintAttributes.Margins.NO_MARGINS)
                        .build();
                pm.print(getString(R.string.app_name) + " - documento", adapter, attrs);
            });
        }

        /** Botão "Tentar novamente" da tela sem conexão. */
        @JavascriptInterface
        public void retry() {
            runOnUiThread(() -> web.loadUrl(HOME));
        }
    }

    @Override
    protected void onNewIntent(Intent intent) {
        super.onNewIntent(intent);
        setIntent(intent);
        web.loadUrl(startUrl(intent));
    }

    @Override
    protected void onActivityResult(int requestCode, int resultCode, Intent data) {
        if (requestCode == REQ_FILE && filePathCallback != null) {
            filePathCallback.onReceiveValue(WebChromeClient.FileChooserParams.parseResult(resultCode, data));
            filePathCallback = null;
            return;
        }
        super.onActivityResult(requestCode, resultCode, data);
    }

    @Override
    @SuppressWarnings("deprecation")
    public void onBackPressed() {
        if (customView != null) chrome.onHideCustomView();
        else if (web.canGoBack()) web.goBack();
        else super.onBackPressed();
    }

    @Override
    protected void onSaveInstanceState(Bundle out) {
        super.onSaveInstanceState(out);
        web.saveState(out);
    }

    @Override protected void onPause() { web.onPause(); super.onPause(); }   // pausa o vídeo ao sair do app
    @Override protected void onResume() { super.onResume(); web.onResume(); }

    @Override
    protected void onDestroy() {
        root.removeAllViews();
        web.destroy();
        super.onDestroy();
    }
}
