<?php

include_once '../init.php';

use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

$twig = getTwig();
$manager = getMongoDbManager();
//$collection = $manager->selectCollection('tp');
$collection = $manager->selectCollection('manuscrits'); // un seul argument : le nom de la collection

$redis = getRedisClient();
// petite aide : https://github.com/VSG24/mongodb-php-examples
if (!empty($_POST)) {
    // @todo coder l'enregistrement d'un nouveau livre en lisant le contenu de $_POST
    $document = [
        'objectid' => (int)$_POST['objectid'] ?? null,
        'titre' => $_POST['title'] ?? null,
        'auteur' => $_POST['author'] ?? null,
        'siecle' => isset($_POST['century']) ? (int)$_POST['century'] : null,
    ];
    $collection->insertOne($document);
    $redis->del('list_items');
    header('Location: /index.php');
    exit;

} else {
// render template
    try {
        echo $twig->render('create.html.twig');
    } catch (LoaderError|RuntimeError|SyntaxError $e) {
        echo $e->getMessage();
    }
}

