<?php
include_once '../init.php';

use MongoDB\BSON\ObjectId;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

$twig = getTwig();
$manager = getMongoDbManager();
$collection = $manager->selectCollection('tp');

$id = $_GET['id'] ?? null;
$entity = null;
$error = null;

if ($id === null) {
    $error = "Aucun identifiant fourni.";
} else {
    try {
        // Récupération du document par _id
        $objectId = new ObjectId($id);
        $document = $collection->findOne(['_id' => $objectId]);

        if ($document === null) {
            $error = "Aucun document trouvé avec cet identifiant ($id).";
        } else {
            $entity = json_decode(json_encode($document), true);
            $entity['_id'] = (string) $document->_id;
        }
    } catch (Exception $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

try {
    echo $twig->render('update.html.twig', [
        'entity' => $entity,
        'error' => $error
    ]);
} catch (LoaderError|RuntimeError|SyntaxError $e) {
    echo $e->getMessage();
}
