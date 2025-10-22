<?php

include_once '../init.php';

use MongoDB\BSON\ObjectId;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

$twig = getTwig();
$manager = getMongoDbManager();
$collection = $manager->selectCollection('tp');
// @todo implementez la récupération des données d'une entité et la passer au template
// petite aide : https://github.com/VSG24/mongodb-php-examples
//$entity = ['name' => 'test'];
// Récupération de l'ID dans l'URL
$id = $_GET['id'] ?? null;

$entity = null;
$error = null;

// Vérifie si l'ID est bien un ObjectId valide (24 caractères hexadécimaux)
try {
        $objectId = new ObjectId($id);
        $document = $collection->findOne(['_id' => $objectId]);

        if ($document === null) {
            $error = "Aucun document trouvé avec cet identifiant.";
        } else {
            // Convertit le document BSON en tableau
            $entity = (array) $document;
            $entity['_id'] = (string) $entity['_id']; // Pour affichage
        }
    } catch (Exception $e) {
        $error = "Erreur : " . $e->getMessage();
    }
var_dump($entity);
// render template
try {
    echo $twig->render('get.html.twig', ['entity' => $entity]);
} catch (LoaderError|RuntimeError|SyntaxError $e) {
    echo $e->getMessage();
}