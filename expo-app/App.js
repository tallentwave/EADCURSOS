import React, { useCallback, useEffect, useRef } from 'react';
import { ActivityIndicator, Alert, BackHandler, Linking, Pressable, StyleSheet, Text, View } from 'react-native';
import { WebView } from 'react-native-webview';
import { StatusBar } from 'expo-status-bar';
import { SafeAreaProvider, useSafeAreaInsets } from 'react-native-safe-area-context';
import Constants from 'expo-constants';
import * as ScreenCapture from 'expo-screen-capture';
import * as Print from 'expo-print';

/**
 * Casca do app: abre o SEU site dentro de um WebView. Tudo que o site faz (login, cursos, vídeos,
 * aulas ao vivo, provas, certificado, carteirinha) continua valendo; aqui só ligamos o que o WebView
 * não faz sozinho: bloqueio de print, impressão/PDF, links externos (Zoom/Meet) e tela sem conexão.
 * Configuração: app.json -> expo.extra (siteUrl, secureScreen, allowDocCapture).
 */
const cfg = Constants.expoConfig?.extra ?? {};
const SITE = String(cfg.siteUrl || 'https://seudominio.com.br').trim().replace(/\/+$/, '');
const SECURE = cfg.secureScreen !== false;        // true = barra print/gravação de tela
const ALLOW_DOCS = cfg.allowDocCapture !== false; // true = só certificado/carteirinha liberam o print
const HOME = `${SITE}/home.php`;

// O servidor reconhece o app pelo sufixo "EADApp/" no User-Agent (esconde vitrine, preços e compra).
const USER_AGENT =
  'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Mobile Safari/537.36 EADApp/1.0';

const hostOf = (url) => ((String(url).match(/^[a-z][a-z0-9+.-]*:\/\/([^/?#:]+)/i) || [])[1] || '').toLowerCase();
const SITE_HOST = hostOf(SITE);
const isInternal = (host) =>
  host === SITE_HOST ||
  ['mercadopago.com', 'mercadopago.com.br', 'mercadolivre.com.br', 'mercadolibre.com', 'mlstatic.com'].some(
    (d) => host === d || host.endsWith('.' + d)
  );

// Cria window.AndroidApp (a mesma ponte do app nativo): o site chama print()/secure() sem saber qual app está rodando.
const BRIDGE = `
(function () {
  var send = function (o) { try { window.ReactNativeWebView.postMessage(JSON.stringify(o)); } catch (e) {} };
  window.AndroidApp = {
    print: function () {
      var c = document.documentElement.cloneNode(true);
      c.querySelectorAll('script,.no-print').forEach(function (n) { n.remove(); });
      var cert = c.querySelector('#cert'); if (cert) cert.style.transform = 'none';
      var vp = c.querySelector('#cert-viewport'); if (vp) { vp.style.width = ''; vp.style.height = ''; }
      var h = c.querySelector('head');
      if (h) { var b = document.createElement('base'); b.href = location.href; h.insertBefore(b, h.firstChild); }
      send({ t: 'print', html: '<!doctype html>' + c.outerHTML });
    },
    secure: function (on) { send({ t: 'secure', on: !!on }); },
    retry: function () { send({ t: 'retry' }); }
  };
  true;
})();`;

function Shell() {
  const insets = useSafeAreaInsets();
  const web = useRef(null);
  const canGoBack = useRef(false);
  const currentUrl = useRef(HOME);

  // Bloqueio de print/gravação (FLAG_SECURE). Toda página começa protegida.
  const protect = useCallback(() => { if (SECURE) ScreenCapture.preventScreenCaptureAsync().catch(() => {}); }, []);
  useEffect(() => { protect(); }, [protect]);

  // Botão voltar do Android navega no histórico do site; na primeira página, fecha o app.
  useEffect(() => {
    const sub = BackHandler.addEventListener('hardwareBackPress', () => {
      if (canGoBack.current && web.current) { web.current.goBack(); return true; }
      return false;
    });
    return () => sub.remove();
  }, []);

  const onMessage = useCallback((event) => {
    let m;
    try { m = JSON.parse(event.nativeEvent.data); } catch (e) { return; }
    if (m.t === 'secure' && SECURE) {
      if (m.on) protect();
      // Só libera o print se a página atual for do seu próprio site (nunca de terceiros/iframes).
      else if (ALLOW_DOCS && hostOf(currentUrl.current) === SITE_HOST) ScreenCapture.allowScreenCaptureAsync().catch(() => {});
    } else if (m.t === 'print' && typeof m.html === 'string') {
      Print.printAsync({ html: m.html, orientation: Print.Orientation.landscape }).catch(() => {
        Alert.alert('Não foi possível abrir a impressão', 'Tente novamente ou baixe o PDF pelo site.');
      });
    } else if (m.t === 'retry') {
      web.current?.reload();
    }
  }, [protect]);

  // Páginas do seu site (e o checkout do Mercado Pago) ficam no app; o resto abre fora (Zoom, Meet, WhatsApp...).
  const onShouldStart = useCallback((req) => {
    if (req.isTopFrame === false) return true;                          // iframes (YouTube/Vimeo)
    const url = req.url || '';
    if (/^(about:|data:|blob:|javascript:)/i.test(url)) return true;
    if (/^https?:\/\//i.test(url) && isInternal(hostOf(url))) return true;
    Linking.openURL(url).catch(() => Alert.alert('Nenhum aplicativo encontrado para abrir este link.'));
    return false;
  }, []);

  return (
    <View style={[styles.root, { paddingTop: insets.top, paddingBottom: insets.bottom }]}>
      <StatusBar style="light" backgroundColor="#07080C" />
      <WebView
        ref={web}
        source={{ uri: HOME }}
        userAgent={USER_AGENT}
        originWhitelist={['*']}
        injectedJavaScriptBeforeContentLoaded={BRIDGE}
        onMessage={onMessage}
        onShouldStartLoadWithRequest={onShouldStart}
        onLoadStart={protect}
        onNavigationStateChange={(nav) => { canGoBack.current = nav.canGoBack; currentUrl.current = nav.url; }}
        javaScriptEnabled
        domStorageEnabled
        sharedCookiesEnabled
        thirdPartyCookiesEnabled
        mediaPlaybackRequiresUserAction={false}
        allowsFullscreenVideo
        allowFileAccess={false}
        mixedContentMode="never"
        setSupportMultipleWindows={false}
        textZoom={100}
        downloadingMessage="Baixando arquivo (pasta Downloads)..."
        startInLoadingState
        renderLoading={() => (
          <View style={styles.center}><ActivityIndicator size="large" color="#D4AF37" /></View>
        )}
        renderError={() => (
          <View style={styles.center}>
            <Text style={styles.diamond}>◆</Text>
            <Text style={styles.title}>Sem conexão</Text>
            <Text style={styles.text}>Não foi possível carregar a plataforma. Verifique sua internet e tente novamente.</Text>
            <Pressable style={styles.btn} onPress={() => web.current?.reload()}>
              <Text style={styles.btnText}>Tentar novamente</Text>
            </Pressable>
          </View>
        )}
        style={styles.web}
      />
    </View>
  );
}

export default function App() {
  return (
    <SafeAreaProvider>
      <Shell />
    </SafeAreaProvider>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: '#07080C' },
  web: { flex: 1, backgroundColor: '#07080C' },
  center: { ...StyleSheet.absoluteFillObject, backgroundColor: '#07080C', alignItems: 'center', justifyContent: 'center', padding: 24 },
  diamond: { fontSize: 44, color: '#D4AF37' },
  title: { fontSize: 24, color: '#D4AF37', fontWeight: '700', marginVertical: 8 },
  text: { color: '#9AA4BD', textAlign: 'center', maxWidth: 320, lineHeight: 22, marginBottom: 24 },
  btn: { backgroundColor: '#D4AF37', borderRadius: 8, paddingVertical: 14, paddingHorizontal: 28 },
  btnText: { color: '#111', fontWeight: '700', fontSize: 16 },
});
