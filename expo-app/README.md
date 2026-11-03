# App Android com Expo + EAS (sem Android Studio)

Mesmo caminho que vocês já usaram: o APK é gerado **na nuvem da Expo (EAS Build)**, só com Node.js e alguns comandos.
O app é uma casca que abre o **seu próprio site**; melhorias no site aparecem no app **sem gerar novo APK**.

## 1. Configurar (arquivo `app.json`)
Edite apenas estes campos:
| Campo | O que é |
|---|---|
| `expo.name` | Nome embaixo do ícone no celular |
| `expo.slug` | Identificador do projeto na Expo (minúsculas, sem espaços) |
| `expo.android.package` | ID único do app, ex.: `br.com.suaempresa.cursos` (não muda depois de publicado) |
| `expo.extra.siteUrl` | Endereço do seu site com **https**, sem barra no final (ex.: `https://cursos.seusite.com.br`) |
| `expo.extra.secureScreen` | `true` = **barra print e gravação de tela** no app inteiro (padrão) |
| `expo.extra.allowDocCapture` | `true` = só o **certificado** e a **carteirinha** liberam o print; `false` = nada libera |

Ícone: troque `assets/icon.png` (1024x1024) e `assets/adaptive-foreground.png` (1024x1024, fundo transparente, desenho no centro).

## 2. Gerar o APK
Instale o **Node.js 20+** (nodejs.org) e, dentro da pasta `expo-app`:
```bash
npm install
npm install -g eas-cli
eas login
eas build:configure      # o eas.json já vem pronto; se perguntar a plataforma, escolha Android
eas build -p android --profile preview
```
No fim o EAS mostra um **link (e QR Code) para baixar o `.apk`**; abra no celular e instale (permita "fontes desconhecidas").
Na primeira vez, quando perguntar *"Generate a new Android Keystore?"*, responda **Yes**. Depois rode `eas credentials`
e **baixe/guarde a keystore**: sem ela você não consegue lançar atualizações do mesmo app.

Para a Google Play use `eas build -p android --profile production` (gera `.aab`).
Testar sem build: `npx expo start` e abrir no Expo Go (o bloqueio de print e a impressão também funcionam).

## O que o app já faz
- Abre o site, mantém o login e trata o botão **voltar** do Android.
- **Bloqueio de print/gravação de tela** (FLAG_SECURE); certificado e carteirinha podem liberar o print (opção acima).
- **Imprimir / Salvar em PDF** do certificado: abre a impressão do Android ("Salvar como PDF").
- **Downloads** (PDF/PNG da carteirinha, dados LGPD) vão para a pasta Downloads.
- Envio de foto/arquivos pelo seletor do celular; vídeos em **tela cheia**.
- Links de fora do site (Zoom, Meet, WhatsApp) abrem no aplicativo correspondente.
- Sem internet: tela "Sem conexão" com botão para tentar de novo.
- **Sem venda**: o app se identifica ao servidor como `EADApp` e o servidor esconde vitrine pública, preços, cupons e compra
  (só quem já comprou fora acessa os cursos).

## Detalhes importantes
- A impressão do certificado no app envia a página ao serviço de impressão do Android. As fontes do certificado só carregam
  se o servidor permitir (já configurado no `.htaccess`: `Access-Control-Allow-Origin` para `.ttf`). Em **Nginx**, adicione
  esse cabeçalho para `/assets/fonts/`. Teste uma vez no celular.
- O app **não inclui notificações push**; o aviso de aula ao vivo continua por e-mail e agenda (.ics).
- Google Play: revisão é decisão do Google; informe que é "área de alunos para quem já adquiriu o curso" e dê uma conta de teste.
- A pasta `../android` é a alternativa com Android Studio; esta pasta (`expo-app`) é a via recomendada por não exigir instalar o Android Studio.
