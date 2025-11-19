<?php

include_once '../init.php';

use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

$twig = getTwig();
$manager = getMongoDbManager();
$redis = getRedisClient();
//var_dump($redis->ping());

$cacheKey = "list_items";

$cached = $redis->get($cacheKey);

if ($cached !== null) {
    // Données récupérées depuis Redis
    $list = json_decode($cached, true);
    $fromCache = true;
} else {
    // Données non présentes en cache → requête MongoDB
    $collection = $manager->selectCollection('tp');
    $cursor = $collection->find();

    $list = [];
    foreach ($cursor as $document) {
        $list[] = $document->getArrayCopy(); // convertit BSONDocument en array
    }
    // Mise en cache (durée : 60 secondes)

    $redis->setex($cacheKey, 60, json_encode($list));
    $fromCache = false;
}

// @todo implementez la récupération des données dans la variable $list
// petite aide : https://github.com/VSG24/mongodb-php-examples

// render template
try {
    echo $twig->render('index.html.twig', ['list' => $list]);
} catch (LoaderError|RuntimeError|SyntaxError $e) {
    echo $e->getMessage();
}



