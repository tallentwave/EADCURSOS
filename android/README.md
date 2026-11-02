# App Android da plataforma EAD

O app é uma "casca" que abre o **seu próprio site** dentro do Android (WebView). Tudo o que existe no site
(login, cursos, vídeos, aulas ao vivo, provas, certificado, carteirinha, compra) funciona igual, e **qualquer
melhoria no site aparece no app sem gerar um novo APK**. O APK só precisa ser refeito se você mudar o ícone,
o nome ou o endereço.

## Como gerar o APK (uma vez)
1. Instale o **Android Studio** (gratuito): https://developer.android.com/studio
2. Abra o Android Studio → **Open** → escolha esta pasta `android`. Aguarde o "Gradle Sync" (baixa o necessário sozinho).
3. Edite o arquivo **`gradle.properties`** (único que você precisa mexer):
   - `SITE_URL` = endereço do seu site com **https** e sem barra no final (ex.: `https://cursos.seusite.com.br`)
   - `APP_NAME` = nome embaixo do ícone
   - `APP_ID` = identificador único (ex.: `br.com.suaempresa.cursos`). Não muda depois de publicado.
4. **APK para testar**: menu *Build → Build Bundle(s) / APK(s) → Build APK(s)*. O arquivo fica em
   `app/build/outputs/apk/debug/app-debug.apk`. Mande para o celular e instale (permita "fontes desconhecidas").
5. **APK para distribuir**: *Build → Generate Signed Bundle / APK → APK → Create new keystore*.
   **Guarde o arquivo da keystore e a senha em lugar seguro**: sem eles você não consegue atualizar o app depois.
6. **Ícone próprio**: botão direito em `app/src/main/res` → *New → Image Asset* → escolha o seu logo.

> Pela linha de comando (sem abrir o Android Studio): instale o JDK 17 e o Android SDK, rode `gradle wrapper`
> uma vez nesta pasta e depois `./gradlew assembleDebug`.

## O que já está tratado
| Recurso do site | No app |
|---|---|
| Vídeo YouTube/Vimeo com player protegido | funciona; tela cheia vira landscape e volta ao sair |
| Aula ao vivo (Zoom/Meet/YouTube) | abre o aplicativo da reunião instalado, ou o navegador |
| Botão *Imprimir / Salvar em PDF* do certificado | usa a impressão do Android (escolha "Salvar como PDF") |
| PDF/PNG da carteirinha e dados LGPD | baixam para a pasta **Downloads** com notificação |
| Enviar foto (carteirinha, assinatura, capas) | abre o seletor de arquivos/galeria do celular |
| Pagamento Mercado Pago (PIX/cartão) | abre dentro do app; links `mercadopago://` abrem o app do Mercado Pago |
| Links do seu site em e-mails | abrem direto no app |
| Sem internet | tela "Sem conexão" com botão *Tentar novamente* |
| Botão voltar do Android | volta a página; na primeira, fecha o app |
| Vídeo ao sair do app | pausa (o player também pausa ao ocultar a tela) |

O app se identifica ao servidor com o sufixo `EADApp/1.0` no *User-Agent* (útil se quiser esconder/mostrar algo só no app).

## Antes de publicar na Google Play (importante)
- **Pagamentos**: a Play exige o **Google Play Billing** para vender conteúdo digital *dentro* do app, e cursos
  online costumam cair nessa regra. Compra pelo Mercado Pago dentro do app pode causar **reprovação ou bloqueio**.
  Opções: (a) distribuir o **APK direto** (site/WhatsApp) — sem essa regra; (b) publicar na Play só com
  acesso para quem já comprou pelo site (esconder os botões de compra quando o *User-Agent* tiver `EADApp`).
  Peça e eu faço o ajuste (b) no servidor.
- Exige conta de desenvolvedor (US$ 25, taxa única), política de privacidade (já existe em `/privacidade.php`)
  e o formulário de segurança de dados.
- O app **não inclui notificações push** (aviso de aula ao vivo). Hoje o lembrete vai por e-mail e calendário (.ics).

## Segurança
- Só `https` (o app recusa `http`, salvo `ALLOW_HTTP=true` para testes).
- Páginas de fora do seu site abrem fora do app; só o seu domínio e o checkout do Mercado Pago ficam dentro.
- `SECURE_SCREEN=true` bloqueia print/gravação de tela dentro do app (ajuda a proteger os vídeos), mas também
  impede o aluno de tirar print do certificado/carteirinha. Ele ainda pode baixar o PDF normalmente.

## Roteiro de teste rápido (após instalar)
1. Entrar com uma conta de aluno; rotacionar a tela; fechar e abrir (continua logado).
2. Assistir a um vídeo, ir para tela cheia, voltar.
3. Entrar em uma aula ao vivo (abre Zoom/Meet).
4. Em *Minha conta*, baixar os dados; no curso concluído, abrir o certificado → *Imprimir / Salvar em PDF*.
5. Carteirinha: enviar foto, baixar o PDF e os PNGs (pasta Downloads).
6. Desligar a internet e abrir o app (tela "Sem conexão").

## Alternativa sem APK (já incluída no site)
O site também é um **PWA**: no Chrome do Android, menu ⋮ → *Adicionar à tela inicial* instala um ícone que abre
a plataforma em tela cheia, sem Android Studio (exige HTTPS). Serve para quem não quiser baixar APK.
