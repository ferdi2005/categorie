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
$limit = '500';
$splimit = 500;

$commonsbotlink = '';
$wikidatabotlink = '';



// Script

require 'boz-mw/autoload.php';
$wikidata =  \wm\Wikidata::instance();
$wikidata->login( $user, $password );
$commons = \wm\Commons::instance();
$commons->login($user, $password);
echo('Inizializzzo connessione con username'. $user . "</br> \n");
$query = <<<EOT
	SELECT ?item ?itemLabel ?value ?city ?cityLabel ?sitelink ?coords ?citycat WHERE {
	?item wdt:P2186 ?value.
	?item wdt:P17 wd:Q38.
	?city wdt:P373 ?citycat
	FILTER NOT EXISTS { ?item wdt:P373 ?x. }
	FILTER NOT EXISTS { ?item wdt:P935 ?y. }
	SERVICE wikibase:label { bd:serviceParam wikibase:language "[AUTO_LANGUAGE], en, it". }
	?item wdt:P131 ?city.
	OPTIONAL { ?item wdt:P625 ?coords.}
	?city wdt:P373 ?citycat
	OPTIONAL {?sitelink schema:isPartOf <https://commons.wikimedia.org/>;schema:about ?item. }
	}
	LIMIT $splimit
EOT;
echo("Esecuzione della query SPAQRL \n");
$sparql = $wikidata->querySPARQL($query);
echo($query);
echo('</div> <br/> <ol>');
foreach ($sparql as $key => $monument) {
	$wikidataid = basename($monument->item->value);
	$wlmid = $monument->value->value;
	$label = $monument->itemLabel->value;
	echo("<li> Eseguo operazioni sul monumento <a href='". $monument->item->value . "'>" . $label . " (" . $wikidataid . ") </a> </br> \n");
	$city = $monument->citylabel->value;
	preg_match('/^Q\d+/', $monument->city->value, $id_arr);
	$cityid = $id_arr[0];
	$coords = $monument->coords->value;
	$commons_url =  $monument->sitelink->value;
	$citycat = $monument->citycat->value;
	if (empty($commons_url)) {
		echo("Cerco foto che abbiano il WLMID di quel monumento salvato \n");
		$wlmsearch = '"' . $wlmid . '"';
					$response = $commons->fetch( [
							'action' => 'query',
							'list'   => 'search',
							'srsearch' => $wlmsearch,
							'srnamespace' => '6',
							'srlimit' => $limit,
							'srwhat' => 'text'
							] );
			$pages = $response->query->search;
			$i = 0;
			foreach ($pages as $key => $page) {
				$i += 1;
			}
			if ($i == 0) {
				echo("Nessuna foto trovata, interrompo. \n");
				continue;
			}

			if (empty($coords)) {
				$text = '{{Wikidata Infobox|qid=' . $wikidataid . '}}';
			} else {
				preg_match('/\d+.\d+\s\d+.\d+/', $coords, $id_arr);
				
				$latitude = explode(' ', $id_arr[0])[0];
				$longitude = explode(' ', $id_arr[0])[1];
				$text = '{{Object location dec'. '|'. $latitude . '|' . $longitude . '}} {{Wikidata Infobox|qid='. $wikidataid . '}}';			
			}
			// cerco categorie che abbiano nome simile
			$looks = $commons->fetch( [
					'action' => 'query',
					'list'   => 'search',
					'srsearch' => 'intitle:' . $label,
					'srnamespace' => '14',
					'srlimit' => $limit,
					] );
			$lookup = $looks->query->search;
			$u = 0;
			foreach ($lookup as $key => $look) {
				$u += 1;
			}
			if ($u > 1) {
				echo('Abbiamo trovato delle categorie con nome simile a quello del monumento, ovvero ' . $label . "\n");
				foreach ($lookup as $key => $look) {
					echo($look->title . ' (https://commons.wikimedia.org/wiki/' . $look->title . ") \n");
					
				}
			} else {
				echo('Non abbiamo trovato categorie simili.');
			}
			$citycaturl = '[[Category:' . $citycat . ']]';
			$text = $text . ' ' . $citycaturl;
			$input = cli\Input::askInput( "Vuoi che il nome della categoria sia " . $label . '? Rispondi Y se sì oppure scrivi il nome che la categoria deve avere oppure scrivi city se vuoi che il nome della categoria sia "'. $label . ' (' . $city . '). Scrivi abort per stoppare ed andare avanti."');
			if ($input == 'city') {
				$label = $label . ' ('. $city . ')';
			} elseif($input != 'Y' && $input != 'abort') {
				$label = $input;
			} elseif($input == 'abort') {
				continue;
			}
			$catlabel = 'Category:' . $label;
			echo('La categoria sarà <a href="https://commons.wikimedia.org/wiki/'. $catlabel . '">'. $catlabel . "</a> </br> \n");
			try {
				echo('Creo la categoria<br/>');
				sleep(15);
				$response = $commons->edit( [
						'title'   => $catlabel,
						'text' => $text,
						'summary' => 'Creating new category, see' . $commonsbotlink,
						'createonly' => 'true',
						'bot' => 'true'
						] );
						var_dump($response);
						echo('</br> \n');
			} 
			catch(Exception $e) {
				echo('Questa categoria esiste già. Interrompo. </li></br> \n');
				continue;
			}
					$catforpage = ' [[' . $catlabel . ']]';
			foreach ($pages as $key => $page) {
				echo('Aggiungo la categoria alla foto' . $page->title . '</br> \n');
				$response = $commons->edit( [
						'pageid'   => $page->pageid,
						'appendtext' => $catforpage,
						'summary' => 'Adding new category'. $catlabel . ', see' . $commonsbotlink,
						] );
						var_dump($response);
						echo('</br> \n');
						sleep(5);
			}
			echo("Ho terminato l'aggiunta della categoria alle foto, proseguo all'inserimento della categoria nell'item (proprietà P373) </br> \n");
			
			$data = new \wb\DataModel();
			$statement = new \wb\StatementCommonsCategory( 'P373', $label );
			$data->addClaim( $statement );
			$wikidata->editEntity( [
				'id' => $wikidataid,
				'data' => $data->getJSON()
			] );
			echo("Procedo ad aggiungere /n");
			echo("Fatto, grazie. Vado avanti. ");
	
	}
	echo('</li> </br> \n');
}
?>


