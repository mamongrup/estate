<?php
declare(strict_types=1); header('Content-Type: application/xml; charset=utf-8');
$base=rtrim(getenv('SITE_URL')?:'https://mamonestate.com','/');$languages=['tr','en','de','ru','ar','fr'];$static=[''=>'1.0','ilanlar'=>'0.9','bolgeler'=>'0.8','hakkimizda'=>'0.6','iletisim'=>'0.6'];
echo '<?xml version="1.0" encoding="UTF-8"?>'.PHP_EOL.'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">'.PHP_EOL;
foreach($static as $path=>$priority){foreach($languages as $lang){$url=$base.'/'.$lang.'/'.($path?$path.'/':'');echo '<url><loc>'.htmlspecialchars($url,ENT_XML1).'</loc><lastmod>'.date('Y-m-d').'</lastmod><changefreq>'.($path?'weekly':'daily').'</changefreq><priority>'.$priority.'</priority>';foreach($languages as $alt){$href=$base.'/'.$alt.'/'.($path?$path.'/':'');echo '<xhtml:link rel="alternate" hreflang="'.$alt.'" href="'.htmlspecialchars($href,ENT_XML1).'" />';}echo '</url>'.PHP_EOL;}}echo '</urlset>';
