<?php
// Manifesto PWA: permite "Adicionar à tela inicial" no Android/Chrome, sem precisar do APK.
require __DIR__ . '/app/bootstrap.php';
header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=3600');
$scope = BASE_URL_PATH . '/';
echo json_encode([
    'name' => site_name(), 'short_name' => mb_substr(site_name(), 0, 12),
    'description' => 'Área de membros: cursos, aulas ao vivo, provas e certificados.',
    'lang' => 'pt-BR', 'dir' => 'ltr', 'display' => 'standalone', 'orientation' => 'any',
    'start_url' => url('home.php'), 'scope' => $scope, 'id' => $scope,
    'background_color' => '#07080c', 'theme_color' => '#07080c',
    'icons' => [
        ['src' => url('pwa-icon.php?s=192'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'],
        ['src' => url('pwa-icon.php?s=512'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
