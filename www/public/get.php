<?php

include_once '../init.php';

use MongoDB\BSON\ObjectId;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

$twig = getTwig();
$manager = getMongoDbManager();
//$collection = $manager->selectCollection('tp');
$collection = $manager->selectCollection('manuscrits'); // un seul argument : le nom de la collection

$id = $_GET['id'] ?? null;
$entity = null;
$error = null;

if ($id === null) {
    $error = "Aucun identifiant fourni dans l'URL.";
} else {
    try {
        // Si c'est un nombre (ta référence "objectid")
        if (ctype_digit($id)) {
            $document = $collection->findOne(['objectid' => (int) $id]);
        } else {
            // Si c'est un identifiant MongoDB
            $objectId = new ObjectId($id);
            $document = $collection->findOne(['_id' => $objectId]);
        }

        if ($document === null) {
            $error = "Aucun document trouvé avec cet identifiant ($id).";
        } else {
            // Conversion BSON -> tableau PHP
            $entity = json_decode(json_encode($document), true);
            $entity['_id'] = (string) $document->_id;
        }
    } catch (Exception $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

try {
    echo $twig->render('get.html.twig', [
        'entity' => $entity,
        'error' => $error
    ]);
} catch (LoaderError | RuntimeError | SyntaxError $e) {
    echo "Erreur Twig : " . $e->getMessage();
}

