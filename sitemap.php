<?php
/**
 * Sitemap generado, no escrito a mano: así no se queda desfasado cuando se
 * añada o quite una casa. El .htaccess lo sirve como /sitemap.xml.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/casas.php';

header('Content-Type: application/xml; charset=utf-8');

$base = 'https://www.campingsopalmo.com';
$hoy  = date('Y-m-d');

/** Páginas fijas: ruta => [prioridad, frecuencia] */
$paginas = [
    '/'                  => ['1.0', 'weekly'],
    '/condiciones.html'  => ['0.3', 'yearly'],
    '/privacidad.html'   => ['0.3', 'yearly'],
];

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
<?php foreach ($paginas as $ruta => [$prio, $frec]): ?>
  <url>
    <loc><?= htmlspecialchars($base . $ruta, ENT_XML1) ?></loc>
    <lastmod><?= $hoy ?></lastmod>
    <changefreq><?= $frec ?></changefreq>
    <priority><?= $prio ?></priority>
<?php if ($ruta === '/'): ?>
    <image:image>
      <image:loc><?= $base ?>/fotos/dron/aereo-camping-dorado-1920.webp</image:loc>
      <image:title>Vista aérea del Camping Sopalmo, Mojácar</image:title>
    </image:image>
<?php endif; ?>
  </url>
<?php endforeach; ?>
<?php foreach (casas_datos() as $slug => $casa): ?>
  <url>
    <loc><?= htmlspecialchars($base . '/casa.php?casa=' . $slug, ENT_XML1) ?></loc>
    <lastmod><?= $hoy ?></lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.8</priority>
<?php
    $f = foto_srcset($casa['portada'], [1600]);
    if ($f['src'] !== ''):
?>
    <image:image>
      <image:loc><?= $base ?>/<?= htmlspecialchars($f['src'], ENT_XML1) ?></image:loc>
      <image:title><?= htmlspecialchars($casa['nombre'], ENT_XML1) ?> · Camping Sopalmo</image:title>
    </image:image>
<?php endif; ?>
  </url>
<?php endforeach; ?>
</urlset>
