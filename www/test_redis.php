<?php
require_once 'init.php'; // charge ton init.php où on a ajouté getRedisClient()

$redis = getRedisClient();

if ($redis) {
    echo "✅ Connexion Redis réussie !\n";

    // Test : écriture
    $redis->set('tp:test', 'Bonjour Redis !');

    // Test : lecture
    $value = $redis->get('tp:test');

    echo "Valeur lue depuis Redis : " . $value . "\n";
} else {
    echo "❌ Redis désactivé ou inaccessible.\n";
}
