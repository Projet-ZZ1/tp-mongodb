<?php

include_once '../init.php';

use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use MongoDB\BSON\Regex;

$twig = getTwig();
$manager = getMongoDbManager();
$redis = getRedisClient();

// Pagination
$perPage = 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$skip = ($page - 1) * $perPage;

// Recherche
$search = $_GET['search'] ?? '';
$filter = [];
if ($search) {
    $filter = [
        '$or' => [
            ['titre' => new Regex($search, 'i')],
            ['auteur' => new Regex($search, 'i')]
        ]
    ];
}

// Cache Redis
$cacheKey = "list_items_page_{$page}_search_" . md5($search);
$cached = $redis ? $redis->get($cacheKey) : null;

if ($cached !== null) {
    $list = json_decode($cached, true);
} else {
    $collection = $manager->selectCollection('manuscrits');
    $cursor = $collection->find($filter, [
        'skip' => $skip,
        'limit' => $perPage
    ]);

    $list = [];
    foreach ($cursor as $document) {
        $list[] = $document->getArrayCopy();
    }

    if ($redis) {
        $redis->setex($cacheKey, 60, json_encode($list));
    }
}

// Nombre total pour pagination
$collection = $manager->selectCollection('manuscrits');
$totalDocuments = $collection->countDocuments($filter);
$totalPages = ceil($totalDocuments / $perPage);

// Render Twig
try {
    echo $twig->render('index.html.twig', [
        'list'       => $list,
        'page'       => $page,
        'totalPages' => $totalPages,
        'search'     => $search
    ]);
} catch (LoaderError|RuntimeError|SyntaxError $e) {
    echo $e->getMessage();
}
