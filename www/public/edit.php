<?php

include_once '../init.php';

use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

$twig = getTwig();
$manager = getMongoDbManager();
$collection = $manager->selectCollection('manuscrits');
$redis = getRedisClient();

// Supprime le cache si Redis est actif
if ($redis) {
    $redis->del("list_items");
}

// Vérifie que l'identifiant est passé
if (empty($_GET['objectid'])) {
    header('Location: /index.php');
    exit;
}

// Récupère le document à modifier par objectid
$objectid = (int) $_GET['objectid'];
$entity = $collection->findOne(['objectid' => $objectid]);

if (!$entity) {
    echo "Aucun document trouvé avec objectid = $objectid";
    exit;
}

// Convertit en array et ajoute le _id en string pour le formulaire
$entityArray = json_decode(json_encode($entity), true);
$entityArray['_id'] = (string) $entity->_id;

// Affiche le formulaire Twig
try {
    echo $twig->render('update.html.twig', ['entity' => $entityArray]);
} catch (LoaderError|RuntimeError|SyntaxError $e) {
    echo $e->getMessage();
}
