<?php
include_once '../init.php';

use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use MongoDB\BSON\Regex;

$twig = getTwig();
$manager = getMongoDbManager();
$redis = getRedisClient();
$elastic = getElasticSearchClient();

// Pagination
$perPage = 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$skip = ($page - 1) * $perPage;

// Recherche
$search = $_GET['search'] ?? '';
$filter = [];
$manuscritIds = null;
$totalDocuments = 0;
$searchResults = null;

// Si une recherche est effectuée ET qu'ElasticSearch est disponible
if (!empty(trim($search)) && $elastic !== null) {
    try {
        // Recherche dans ElasticSearch
        $esParams = [
            'index' => 'manuscrits',
            'body' => [
                'query' => [
                    'multi_match' => [
                        'query' => $search,
                        'fields' => ['titre^2', 'auteur^1.5', 'edition'],
                        'fuzziness' => 'AUTO',
                        'operator' => 'or',
                        'type' => 'best_fields'
                    ]
                ],
                'from' => $skip,
                'size' => $perPage
            ]
        ];

        $response = $elastic->search($esParams);

        // Extraire les IDs des manuscrits trouvés
        $manuscritIds = [];
        foreach ($response['hits']['hits'] as $hit) {
            $manuscritIds[] = new MongoDB\BSON\ObjectId($hit['_source']['manuscrit_id']);
        }

        $totalDocuments = $response['hits']['total']['value'];
        $searchResults = [
            'total' => $totalDocuments,
            'using_elasticsearch' => true
        ];

    } catch (Exception $e) {
        // Si ElasticSearch échoue, fallback sur MongoDB regex
        error_log("Erreur ElasticSearch : " . $e->getMessage());
        $elastic = null; // Désactiver ElasticSearch pour cette requête
    }
}

// Si pas de recherche ElasticSearch OU si ElasticSearch a échoué
if ($manuscritIds === null) {
    // Fallback : recherche MongoDB classique avec regex
    if ($search) {
        $filter = [
            '$or' => [
                ['titre' => new Regex($search, 'i')],
                ['auteur' => new Regex($search, 'i')]
            ]
        ];
        if ($searchResults === null) {
            $searchResults = [
                'using_elasticsearch' => false
            ];
        }
    }
}

// Clé de cache unique pour cette page + recherche + mode
$cacheMode = ($manuscritIds !== null) ? 'es' : 'mongo';
$cacheKey = "list_items_{$cacheMode}_page_{$page}_search_" . md5($search);
$cached = $redis ? $redis->get($cacheKey) : null;

if ($cached !== null) {
    // Récupération depuis Redis
    $list = json_decode($cached, true);

    // Si on n'a pas encore le total, le calculer
    if ($totalDocuments === 0) {
        $collection = $manager->selectCollection('manuscrits');
        $totalDocuments = $collection->countDocuments($filter);
    }

} else {
    // Récupération depuis MongoDB
    $collection = $manager->selectCollection('manuscrits');

    if ($manuscritIds !== null && !empty($manuscritIds)) {
        // Cas ElasticSearch : récupérer dans l'ordre de pertinence
        $cursor = $collection->find([
            '_id' => ['$in' => $manuscritIds]
        ]);

        // Récupérer tous les documents
        $documents = [];
        foreach ($cursor as $document) {
            $documents[$document['_id']->__toString()] = $document->getArrayCopy();
        }

        // Réordonner selon l'ordre d'ElasticSearch
        $list = [];
        foreach ($manuscritIds as $id) {
            $idStr = $id->__toString();
            if (isset($documents[$idStr])) {
                $list[] = $documents[$idStr];
            }
        }

    } else {
        // Cas MongoDB classique ou pas de recherche
        $cursor = $collection->find($filter, [
            'skip' => $skip,
            'limit' => $perPage
        ]);

        $list = [];
        foreach ($cursor as $document) {
            $list[] = $document->getArrayCopy();
        }

        // Calculer le total pour la pagination
        $totalDocuments = $collection->countDocuments($filter);
    }

    // Sauvegarde dans Redis 60 secondes
    if ($redis) {
        $redis->setex($cacheKey, 60, json_encode($list));
    }
}

$totalPages = ceil($totalDocuments / $perPage);

// Rendu Twig
try {
    echo $twig->render('index.html.twig', [
        'list'          => $list,
        'page'          => $page,
        'totalPages'    => $totalPages,
        'search'        => $search,
        'totalResults'  => $totalDocuments,
        'searchResults' => $searchResults
    ]);
} catch (LoaderError|RuntimeError|SyntaxError $e) {
    echo $e->getMessage();
}