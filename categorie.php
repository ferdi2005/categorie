<?php
/*
© 2019 FERDINANDO TRAVERSA - LICENZA MIT - VEDI FILE LICENSE.MD


	USARE QUESTA QUERY PER TROVARE INVECE I MONUMENTI CHE HANNO LA GALLERIA MA NON HANNO LA CATEGORIA, SARANNO MOLTO MENO
	SELECT ?item ?itemLabel WHERE {
	  	?item wdt:P2186 ?value.
		?item wdt:P17 wd:Q38.
		FILTER NOT EXISTS { ?item wdt:P373 ?x. }
	    ?item wdt:P935 ?gallery.
		SERVICE wikibase:label { bd:serviceParam wikibase:language "[AUTO_LANGUAGE], en, it". }
	}
*/
// Config values

$user = '';
$password = '';
$limit = '500'; //TODO: aumentare a 5000
$commonsbotlink = '';
$wikidatabotlink = '';



// Script

$esistenti = []
require 'boz-mw/autoload.php';
$wikidata =  \wm\Wikidata::instance();
$wikidata->login( $user, $password );
$commons = \wm\Commons::instance();
$commons->login($user, $password);
$query = <<<EOT
	SELECT ?item ?itemLabel ?value ?city ?cityLabel ?sitelink ?coords WHERE {
	?item wdt:P2186 ?value.
	?item wdt:P17 wd:Q38.
	FILTER NOT EXISTS { ?item wdt:P373 ?x. }
	FILTER NOT EXISTS { ?item wdt:P935 ?y. }
	SERVICE wikibase:label { bd:serviceParam wikibase:language "[AUTO_LANGUAGE], en, it". }
	OPTIONAL { ?item wdt:P131 ?city. }
	OPTIONAL { ?item wdt:P625 ?coords.}
	OPTIONAL {?sitelink schema:isPartOf <https://commons.wikimedia.org/>;schema:about ?item. }
	}
	LIMIT 10
EOT;
$sparql = $wikidata->querySPARQL($query);
echo($query);
foreach ($sparql as $key => $monument) {
	preg_match('/^Q\d+/', $monument->'item'->'value', $id_arr);
	$wikidataid = $id_arr[0];
	$wlmid = $monument->'value'->'value';
	$label = $monument->'itemLabel'->'value';
	$city = $monument->'citylabel'->'value';
	preg_match('/^Q\d+/', $monument->'city'->'value', $id_arr);
	$cityid = $id_arr[0];
	$coords = $monument->'coords'->'value'
	$commons_url =  $monument->'sitelink'->'value';
	echo($commons_url);
	if (empty($commons_url)) {
			if (empty($coords)) {
				$text = '{{Wikidata Infobox}}';
			} else {
				preg_match('\d+\.\d+', $coords, $id_arr);
				$latitude = $id_arr[0]	
				$longitude = $id_arr[1]
				$text = '{{Object location dec'. $latitude . '|' . $longitude . '}} {{Wikidata Infobox}}'			
			}
			$catlabel = 'Category:' . $label;
			$response = $commons->fetch( [
					'action' => 'edit',
					'title'   => $catlabel,
					'text' => $text,
					'summary' => 'Creating new category, see' . $commonsbotlink,
					'createonly' => 'true',
					'bot' => 'true'
					] );
					$catforpage = ' [[' . $catlabel . ']]';
			foreach ($pages as $key => $page) {
				$response = $commons->fetch( [
						'action' => 'edit',
						'pageid'   => $page->'pageid',
						'appendtext' => $catforpage,
						'summary' => 'Adding new category'. $catlabel . ', see' . $commonsbotlink,
						'bot' => 'true'
						] );
			}


	
}
?>


