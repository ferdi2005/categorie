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
	$wikidataid = basename($monument->item->value);
	$wlmid = $monument->value->value;
	$label = $monument->itemLabel->value;
	$city = $monument->citylabel->value;
	preg_match('/^Q\d+/', $monument->city->value, $id_arr);
	$cityid = $id_arr[0];
	$coords = $monument->coords->value;
	$commons_url =  $monument->sitelink->value;
	if (empty($commons_url)) {
					$response = $commons->fetch( [
							'action' => 'query',
							'list'   => 'search',
							'srsearch' => $wlmid,
							'srnamespace' => '6',
							'srlimit' => $limit,
							] );
			$pages = $response->query->search;
			$i = 0;
			foreach ($pages as $key => $page) {
				$i += 1;
			}

			if (empty($coords)) {
				$text = '{{Wikidata Infobox}}';
			} else {
				preg_match('/\d+.\d+\s\d+.\d+/', $coords, $id_arr);
				
				$latitude = explode(' ', $id_arr[0])[0];
				$longitude = explode(' ', $id_arr[0])[1];
				$text = '{{Object location dec'. '|'. $latitude . '|' . $longitude . '}} {{Wikidata Infobox}}';			
			}
			$catlabel = 'Category:' . $label;
			try {
				$response = $commons->edit( [
						'title'   => $catlabel,
						'text' => $text,
						'summary' => 'Creating new category, see' . $commonsbotlink,
						'createonly' => 'true',
						'bot' => 'true'
						] );
			} 
			catch(Exception $e) {
				continue;
			}
					$catforpage = ' [[' . $catlabel . ']]';
			foreach ($pages as $key => $page) {
				$response = $commons->edit( [
						'pageid'   => $page->pageid,
						'appendtext' => $catforpage,
						'summary' => 'Adding new category'. $catlabel . ', see' . $commonsbotlink,
						'bot' => 'true'
						] );
			}
			$data = new \wb\DataModel();
			$statement = new \wb\StatementCommonsCategory( 'P373', $label );
			$data->addClaim( $statement );
			$wikidata->editEntity( [
				'id' => $wikidataid,
				'data' => $data->getJSON()
			] );
	
	}
}
?>


