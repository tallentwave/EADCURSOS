# ◆ Plataforma EAD — Área de Membros estilo Netflix

PHP 8+ · MySQL/MariaDB · sem Composer · tema Preto + Dourado + Azul-marinho.

## Recursos
- **Vitrine estilo Netflix**: banner de destaque, linhas por categoria, "Continue assistindo", "Meus cursos", cursos bloqueados com cadeado.
- **Controle de acesso por curso**: o aluno só abre aulas dos cursos que comprou (ou que o admin liberou). Aulas marcadas como "grátis" funcionam como degustação.
- **Pagamento Mercado Pago** (PIX, cartão, boleto) com liberação automática via webhook; conferência do valor direto na API; reembolso remove o acesso.
- **Vídeos YouTube/Vimeo** (apenas links desses domínios são aceitos; embed gerado pelo servidor).
- **Progresso** por aula, **materiais em PDF** (download protegido por login/matrícula), **certificado** imprimível com código verificável (`/verificar.php`), **comentários/dúvidas** com respostas do professor.
- **Admin**: painel com métricas, cursos/módulos/aulas, alunos (criar, liberar/remover cursos, expiração, bloquear, redefinir senha), pedidos (aprovação manual/reembolso), moderação de comentários, configurações do Mercado Pago.

## Novidades da versão 2
- **Player protegido** (YouTube/Vimeo): sem controles nativos, sem teclado, sem menu de contexto, sem velocidade, sem avançar. O link do vídeo **não aparece no HTML**: ele só é entregue por `video.php` a quem tem acesso e a aula liberada.
- **Mínimo de 90% assistido** para liberar a próxima aula. A contagem é feita **no servidor** com tempo real (pular, acelerar ou enviar pings em rajada não gera crédito além de ~1,05x do relógio). Pausar ou deixar a aba em segundo plano não conta. O botão "marcar como concluída" não existe para aulas em vídeo.
- **Duração**: cadastre os segundos exatos da aula (ou abra a aula como admin e dê play: a duração é gravada sozinha). Sem duração cadastrada o sistema usa a informada pelo player do aluno, que é menos confiável.
- **E-mail por SMTP** (Admin → Configurações): boas-vindas, recuperação de senha e confirmação de compra, com botão de teste. A senha SMTP e os tokens do Mercado Pago ficam **criptografados** (AES-256-GCM).
- **Cupons** (% ou valor fixo, por curso ou geral, limite de usos, validade, uso único por aluno). Cupom de 100% libera o curso sem passar pelo Mercado Pago.
- **Vouchers**: lotes de códigos pré-pagos `XXXX-XXXX-XXXX` por curso, com validade e exportação CSV. O aluno resgata em `/resgatar.php`. Resgate atômico, uso único e limite de tentativas.
- Bancos da versão 1 são atualizados automaticamente no primeiro acesso.

## Novidades da versão 3 — Provas e certificado
- **Prova por módulo** e **avaliação final** (Admin → Conteúdo → botão "Prova" em cada módulo / "Avaliação final").
- Cada prova define: nota mínima, nº de tentativas (0 = ilimitadas), tempo limite, sorteio de N questões de um banco maior, embaralhamento e se é **obrigatória**.
- **Prova obrigatória de módulo** trava o módulo seguinte até a aprovação. Prova opcional não trava nem entra no certificado.
- **Avaliação final** libera o certificado: o aluno precisa concluir 100% das aulas, passar nas provas obrigatórias dos módulos e ser aprovado na final. Sem prova final cadastrada, vale a regra anterior (100% das aulas). O certificado mostra o aproveitamento.
- Questões de **múltipla escolha com uma resposta correta** (2 a 6 alternativas).
- Segurança: a correção é só no servidor (o gabarito nunca vai para o navegador, e o aluno vê apenas certo/errado), o tempo é controlado no servidor com 30 s de tolerância (fora disso a nota é 0), cada tentativa só pode ser enviada uma vez, alternativas fora da ordem sorteada são ignoradas e o aluno só faz a prova se tiver o curso e tiver concluído as aulas.
- Admin vê resultados por prova (aprovados, média) e pode **zerar as tentativas** de um aluno.
- Certificados já emitidos continuam válidos se provas forem criadas depois. Alunos que já estavam adiantados num curso passam a precisar das provas obrigatórias novas para abrir os módulos seguintes.

## Novidades da versão 4 — Certificado com QR Code e LGPD
- **QR Code** no certificado (gerado no servidor, em SVG, funciona impresso/PDF) que abre a **página pública do certificado** em `/verificar.php?codigo=...`. Também dá para digitar o código manualmente.
- **Página pública**: mostra selo de válido/revogado, titular, **CPF parcial** (`***.123.456-**`), curso, carga horária, aproveitamento final, datas e instituição. **Nunca** mostra e-mail, CPF completo, telefone, IP, pagamentos ou notas por módulo. Não é indexada por buscadores (`noindex`) e as consultas têm limite contra varredura de códigos.
- **Dados congelados na emissão**: o certificado guarda nome, CPF parcial, curso, carga e nota do momento. Mudar o nome depois não altera um certificado já emitido. Certificados antigos são migrados automaticamente.
- **CPF**: validado, gravado **criptografado** (AES-256-GCM) e com hash para impedir duplicidade; só é exibido mascarado. Obrigatório apenas para emitir certificado.
- **LGPD**: aceite da Política de Privacidade no cadastro (com data registrada), página `/privacidade.php`, e em *Minha conta*: modo reservado do certificado (só nome abreviado, sem CPF), **baixar meus dados** (JSON) e **excluir minha conta** (dados pessoais, progresso, comentários e certificados são removidos; pedidos ficam anonimizados por obrigação fiscal).
- **Admin**: lista de certificados com busca, **revogação/reativação** e CPF sempre mascarado. Em Configurações: razão social, CNPJ, assinatura do certificado e e-mail do encarregado (DPO).
- Importante: configure o `base_url` correto (HTTPS) no instalador/`config.php`, pois é ele que vai dentro do QR Code.

## Novidades da versão 5 — Novo certificado e correção da impressão/PDF
- **Novo design** (A4 paisagem exato): tema **escuro** (preto, dourado e azul-marinho) ou **claro** (marfim, dourado e azul — economiza tinta), tipografia própria embutida (Cinzel, Great Vibes, Montserrat, Playfair; licença OFL, em `assets/fonts`), selo de autenticidade, QR Code, dados do curso (carga horária, aproveitamento, conclusão) e logo opcional.
- **Assinatura da responsável**: Admin → Configurações → *Aparência do certificado*. Envie foto/scan da assinatura (tinta escura em fundo claro; PNG transparente é o ideal): o sistema **remove o fundo, recorta e pinta** na cor do tema. Sem imagem, o nome aparece em letra cursiva. Nome e cargo vêm de *Instituição, certificado e LGPD*.
- **Prévia** do certificado com dados de exemplo (`Admin → Configurações → Pré-visualizar`), nos dois temas.
- **Correção do botão Imprimir/Salvar PDF**: o botão usava `onclick` embutido, que a política de segurança do site (CSP) bloqueia. Agora usa tratadores no JavaScript externo. O mesmo bloqueio afetava a confirmação de *excluir módulo* e *remover comentário* e a troca de privacidade do certificado — todos corrigidos.
- A impressão sai em **uma página A4 paisagem**, sem margens, com fontes embutidas e texto selecionável. Na janela de impressão escolha *Salvar como PDF*.

### Limite honesto da proteção de vídeo
Com YouTube/Vimeo o navegador do aluno precisa receber o ID do vídeo para tocá-lo; por isso é impossível impedir 100% que alguém experiente o descubra nas ferramentas do navegador ou grave a tela. As travas acima barram o uso comum e o relógio no servidor impede burlar o mínimo de 90%. Para reduzir o risco de link vazado, use vídeos **não listados** no YouTube ou, melhor, **Vimeo com restrição de domínio** (privacidade → "Onde incorporar" → apenas o seu site): assim o vídeo não toca fora da sua plataforma.

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
