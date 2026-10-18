# ◆ Plataforma EAD — Área de Membros estilo Netflix

PHP 8+ · MySQL/MariaDB · sem Composer · tema Preto + Dourado + Azul-marinho.

## Recursos
- **Vitrine estilo Netflix**: banner de destaque, linhas por categoria, "Continue assistindo", "Meus cursos", cursos bloqueados com cadeado.
- **Controle de acesso por curso**: o aluno só abre aulas dos cursos que comprou (ou que o admin liberou). Aulas marcadas como "grátis" funcionam como degustação.
- **Pagamento Mercado Pago** (PIX, cartão, boleto) com liberação automática via webhook; conferência do valor direto na API; reembolso remove o acesso.
- **Vídeos YouTube/Vimeo** (apenas links desses domínios são aceitos; embed gerado pelo servidor).
- **Progresso** por aula, **materiais em PDF** (download protegido por login/matrícula), **certificado** imprimível com código verificável (`/verificar.php`), **comentários/dúvidas** com respostas do professor.
- **Admin**: painel com métricas, cursos/módulos/aulas, alunos (criar, liberar/remover cursos, expiração, bloquear, redefinir senha), pedidos (aprovação manual/reembolso), moderação de comentários, configurações do Mercado Pago.

## Instalação (cPanel / hospedagem compartilhada)
1. Crie um banco MySQL e um usuário no cPanel.
2. Envie todos os arquivos para `public_html` (ou subpasta).
3. Acesse `https://seudominio.com/install/`, confira os requisitos, preencha banco, URL e dados do administrador.
4. Pronto: o instalador cria as tabelas, o admin e o `config/config.php`, e se bloqueia sozinho. **Apague a pasta `/install`** depois.
5. Entre no `/admin`, vá em **Configurações** e informe o *Access Token* do Mercado Pago.

### Mercado Pago
- Painel de desenvolvedor → sua aplicação → credenciais → copie o **Access Token** (produção ou teste).
- Webhooks → URL `https://seudominio.com/webhook.php`, evento **Pagamentos**; copie a *chave secreta* e cole em Configurações (opcional, recomendado).
- Use HTTPS em produção (necessário para o retorno automático ao site).

## Segurança implementada
Senhas com `password_hash` (bcrypt/argon), consultas 100% preparadas (PDO), CSRF em todos os POST, escape de saída, sessão `HttpOnly`/`SameSite`/`Secure` com regeneração no login, bloqueio por tentativas de login, cabeçalhos CSP/X-Frame/nosniff/HSTS, uploads validados (imagem por `getimagesize`, PDF por assinatura + MIME) com nomes aleatórios, PDFs fora do acesso público, `.htaccess` bloqueando `config/`, `app/`, `storage/`, execução de scripts em `uploads/`, webhook validado por assinatura HMAC e por consulta direta à API.

> Em **Nginx** (sem `.htaccess`) bloqueie manualmente `/config`, `/app`, `/storage`, `/install/schema.sql` e a execução de PHP em `/uploads`.

## Desenvolvimento
`php -S 127.0.0.1:8080` na raiz e acesse `/install/`. Para testar o Mercado Pago sem credenciais, aponte `MP_API_BASE` para um servidor mock.
