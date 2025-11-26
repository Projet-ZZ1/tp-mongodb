<?php
declare(strict_types=1);

include_once __DIR__ . '/../init.php';

use MongoDB\BSON\ObjectId;
use MongoDB\Driver\Exception\Exception as MongoException;

try {
    $manager = getMongoDbManager();
    $collectionName = $_ENV['MDB_COLLECTION'] ?? 'manuscrits';
    $collection = $manager->selectCollection($collectionName);

    if (!isset($_GET['objectid'])) {
        header('Location: /index.php?deleted=missing');
        exit;
    }

    $rawId = trim((string) $_GET['objectid']);
    $intId = (int) $rawId;

    // Essayer de trouver et supprimer
    $filter = ['objectid' => $intId];
    $doc = $collection->findOne($filter);

    if ($doc === null) {
        // essayer string
        $filter = ['objectid' => $rawId];
        $doc = $collection->findOne($filter);
    }

    if ($doc === null && preg_match('/^[a-f0-9]{24}$/i', $rawId)) {
        $filter = ['_id' => new ObjectId($rawId)];
        $doc = $collection->findOne($filter);
    }

    if ($doc === null) {
        header('Location: /index.php?deleted=0&reason=not_found');
        exit;
    }

    $result = $collection->deleteOne($filter);

    // Invalidation Redis (si présent)
    $redis = getRedisClient();
    if ($redis) {
        // 1) Supprimer cache du document individuel (si on utilise ce pattern)
        $keysToDelete = [];
        $keysToDelete[] = "item:{$rawId}";
        $keysToDelete[] = "item:{$intId}";

        // 2) Supprimer toutes les clés list_items* (scan pour éviter KEYS en prod)
        $patternList = "list_items*";

        // Gestion pour phpredis (Redis class) : use scan
        if ($redis instanceof Redis || method_exists($redis, 'scan')) {
            // phpredis scan interface: $it = null; $keys = []; while ($keys = $redis->scan($it, $pattern))
            $it = null;
            while (true) {
                $found = $redis->scan($it, $patternList, 100); // COUNT 100
                if ($found === false) break;
                foreach ($found as $k) $keysToDelete[] = $k;
                if ($it === 0) break;
            }
        } else {
            // Predis or other clients: try keys (ok en dev)
            try {
                if (method_exists($redis, 'keys')) {
                    $found = $redis->keys($patternList);
                    if (is_array($found)) {
                        foreach ($found as $k) $keysToDelete[] = $k;
                    }
                }
            } catch (Throwable $e) {
                // fallback: rien
            }
        }

        // Dédup keys
        $keysToDelete = array_values(array_unique($keysToDelete));

        // Supprimer les clés par batch
        foreach (array_chunk($keysToDelete, 50) as $chunk) {
            try {
                // phpredis: del(array) or call_user_func_array
                if ($redis instanceof Redis && method_exists($redis, 'del')) {
                    $redis->del($chunk);
                } elseif (method_exists($redis, 'del')) {
                    // Predis accepts array for del
                    $redis->del($chunk);
                } else {
                    // fallback: delete one by one
                    foreach ($chunk as $k) {
                        if ($k !== '') {
                            if (method_exists($redis, 'del')) {
                                $redis->del($k);
                            } elseif (method_exists($redis, 'delete')) {
                                $redis->delete($k);
                            }
                        }
                    }
                }
            } catch (Throwable $e) {
                error_log('Redis delete error: ' . $e->getMessage());
            }
        }
    }

    if ($result->getDeletedCount() > 0) {
        header('Location: /index.php?deleted=1&objectid=' . urlencode($rawId));
        exit;
    } else {
        header('Location: /index.php?deleted=0&objectid=' . urlencode($rawId));
        exit;
    }

} catch (MongoException $me) {
    error_log('MongoException in delete.php: ' . $me->getMessage());
    header('Location: /index.php?deleted=error&msg=mongo');
    exit;
} catch (Throwable $t) {
    error_log('Throwable in delete.php: ' . $t->getMessage());
    header('Location: /index.php?deleted=error&msg=exception');
    exit;
}
