<?php
include_once '../init.php';
use MongoDB\BSON\ObjectId;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

$twig = getTwig();
$manager = getMongoDbManager();
$collection = $manager->selectCollection('tp');

$error = null;

// Si formulaire soumis (POST) → mise à jour
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    if (!$id) die("Aucun identifiant fourni.");

    try {
        $objectId = new ObjectId($id);

        $updateData = [
            'titre'   => $_POST['titre'] ?? '',
            'auteur'  => $_POST['auteur'] ?? null,
            'edition' => $_POST['edition'] ?? '',
            'langue'  => $_POST['langue'] ?? '',
            'cote'    => $_POST['cote'] ?? '',
            'siecle'  => $_POST['siecle'] ?? ''
        ];

        $collection->updateOne(['_id' => $objectId], ['$set' => $updateData]);
        header('Location: /get.php?id=' . $id);
        exit;

    } catch (Exception $e) {
        $error = "Erreur lors de la mise à jour : " . $e->getMessage();
    }
}

// Si page appelée en GET → récupération du document pour pré-remplir le formulaire
else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = $_GET['id'] ?? null;
    if (!$id) $error = "Aucun identifiant fourni.";
    else {
        try {
            $objectId = new ObjectId($id);
            $document = $collection->findOne(['_id' => $objectId]);

            if ($document) {
                $entity = json_decode(json_encode($document), true);
                $entity['_id'] = (string)$document->_id;
            } else {
                $error = "Document introuvable.";
                $entity = null;
            }
        } catch (Exception $e) {
            $error = "Erreur : " . $e->getMessage();
            $entity = null;
        }
    }
}

// Affichage du formulaire
try {
    echo $twig->render('update.html.twig', [
        'entity' => $entity ?? null,
        'error'  => $error
    ]);
} catch (LoaderError|RuntimeError|SyntaxError $e) {
    echo $e->getMessage();
}
