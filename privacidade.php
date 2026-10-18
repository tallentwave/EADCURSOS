<?php
require __DIR__ . '/app/bootstrap.php';
$org = setting('company_name') ?: site_name();
$dpo = setting('dpo_email') ?: setting('mail_from');
$cnpj = setting('company_cnpj');
?>
<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Política de Privacidade · <?= e(site_name()) ?></title><link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>"></head>
<body>
<header class="nav"><a class="brand" href="<?= e(url('')) ?>"><span>&#9670;</span> <?= e(site_name()) ?></a></header>
<main><div class="pub-wrap" style="line-height:1.7">
<h1 style="color:var(--gold)">Política de Privacidade</h1>
<p class="hint">Em conformidade com a Lei Geral de Proteção de Dados (Lei nº 13.709/2018). Atualizada em <?= date('d/m/Y') ?>.</p>

<h2 style="margin:22px 0 6px;color:var(--gold2)">1. Quem é o controlador</h2>
<p><?= e($org) ?><?= $cnpj ? ', CNPJ ' . e($cnpj) : '' ?>.<?= $dpo ? ' Contato do encarregado (DPO): <a href="mailto:' . e($dpo) . '">' . e($dpo) . '</a>.' : '' ?></p>

<h2 style="margin:22px 0 6px;color:var(--gold2)">2. Dados que coletamos e para quê</h2>
<ul style="margin-left:22px">
<li><strong>Nome e e-mail</strong>: criar sua conta, acesso à plataforma e comunicações do curso (execução de contrato).</li>
<li><strong>CPF (opcional até a emissão do certificado)</strong>: identificar o titular do certificado e evitar fraudes. É armazenado <strong>criptografado</strong> e exibido somente de forma parcial (ex.: ***.123.456-**).</li>
<li><strong>Progresso, notas de provas e comentários</strong>: operar os cursos, avaliações e certificação.</li>
<li><strong>Pedidos e pagamentos</strong>: liberar o acesso e cumprir obrigações fiscais. Dados de cartão são tratados pelo Mercado Pago; não os recebemos nem guardamos.</li>
<li><strong>Dados técnicos</strong> (endereço IP, tentativas de login): segurança e prevenção a fraudes, por prazo curto.</li>
</ul>

<h2 style="margin:22px 0 6px;color:var(--gold2)">3. Página pública de validação do certificado</h2>
<p>Quem escaneia o QR Code ou digita o código do certificado vê apenas: nome do titular, CPF parcialmente oculto, curso, carga horária, aproveitamento, datas e instituição emissora. <strong>Nunca exibimos</strong> e-mail, CPF completo, telefone, endereço, IP, histórico de pagamentos ou notas por módulo. Você pode, a qualquer momento, em <em>Minha conta → Certificados</em>, optar por um modo reservado em que aparece apenas o nome abreviado, sem CPF. Essas páginas não são indexadas por buscadores.</p>

<h2 style="margin:22px 0 6px;color:var(--gold2)">4. Com quem compartilhamos</h2>
<p>Somente com operadores necessários ao serviço (processamento de pagamento, hospedagem, envio de e-mail e plataformas de vídeo YouTube/Vimeo, que podem registrar a reprodução). Não vendemos dados pessoais.</p>

<h2 style="margin:22px 0 6px;color:var(--gold2)">5. Por quanto tempo guardamos</h2>
<p>Enquanto sua conta existir. Registros de pedidos são mantidos pelo prazo exigido pela legislação fiscal, mesmo após a exclusão da conta, de forma anonimizada quanto aos seus dados de identificação.</p>

<h2 style="margin:22px 0 6px;color:var(--gold2)">6. Seus direitos (art. 18 da LGPD)</h2>
<p>Em <em>Minha conta</em> você pode <strong>baixar uma cópia dos seus dados</strong>, <strong>corrigir</strong> nome e CPF (antes da emissão do certificado), <strong>controlar a exibição pública</strong> do certificado e <strong>excluir sua conta</strong>. Ao excluir, seus dados pessoais, progresso, comentários e certificados são removidos e os registros de pedidos são anonimizados. Para outros pedidos (portabilidade, informação sobre compartilhamento, revogação de consentimento)<?= $dpo ? ', escreva para <a href="mailto:' . e($dpo) . '">' . e($dpo) . '</a>' : ', fale com o suporte' ?>.</p>

<h2 style="margin:22px 0 6px;color:var(--gold2)">7. Segurança</h2>
<p>Senhas com hash seguro, CPF criptografado, conexão HTTPS, controle de acesso por curso, proteção contra tentativas abusivas de login e consulta de códigos.</p>
<p style="margin-top:24px"><a class="btn btn-ghost btn-sm" href="<?= e(url('')) ?>" data-back>&laquo; Voltar</a></p>
</div></main>
<footer class="foot">&copy; <?= date('Y') ?> <?= e(site_name()) ?></footer>
</body></html>
