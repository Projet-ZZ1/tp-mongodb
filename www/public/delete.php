<?php

include_once '../init.php';

use MongoDB\BSON\ObjectId;

// Connexion à MongoDB
$manager = getMongoDbManager();
//$collection = $manager->selectCollection('tp');
$collection = $manager->selectCollection('manuscrits'); // un seul argument : le nom de la collection

$id = $_GET['id'] ?? null;
$error = null;

if ($id === null) {
    $error = "Aucun identifiant fourni.";
} else {
    try {
        // Suppression par objectid (champ numérique)
        if (ctype_digit($id)) {
            $result = $collection->deleteOne(['objectid' => (int)$id]);
        } else {
            // Optionnel : suppression par _id MongoDB
            $objectId = new ObjectId($id);
            $result = $collection->deleteOne(['_id' => $objectId]);
        }

        if ($result->getDeletedCount() > 0) {
            // Redirection vers la liste après suppression
            header('Location: /index.php?message=Suppression réussie');
            exit;
        } else {
            $error = "Aucun document trouvé avec cette référence ($id).";
        }
    } catch (Exception $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

// Affichage du message d'erreur si nécessaire
if ($error) {
    echo "<p style='color:red;'>$error</p>";
    echo "<p><a href='/index.php'>Retourner à la liste</a></p>";
}
