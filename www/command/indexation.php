
<?php
require_once __DIR__ . '/../init.php';

$elastic = getElasticSearchClient();
$manager = getMongoDbManager();

if ($elastic === null) {
    die("❌ Erreur : Impossible de se connecter à ElasticSearch.\n");
}

echo "✅ Connexion à ElasticSearch établie.\n";

// Nom de l'index
$indexName = 'manuscrits';

// Supprimer l'index s'il existe déjà
try {
    $elastic->indices()->delete(['index' => $indexName]);
    echo "\uD83D\uDDD1️  Index existant supprimé.\n";
} catch (Exception $e) {
    echo "ℹ️  Aucun index à supprimer.\n";
}

// Créer l'index avec les paramètres appropriés
$params = [
    'index' => $indexName,
    'body' => [
        'settings' => [
            'number_of_shards' => 1,
            'number_of_replicas' => 0,
            'analysis' => [
                'analyzer' => [
                    'french_analyzer' => [
                        'type' => 'custom',
                        'tokenizer' => 'standard',
                        'filter' => [
                            'lowercase',
                            'french_elision',
                            'french_stop',
                            'french_stemmer',
                            'asciifolding'
                        ]
                    ]
                ],
                'filter' => [
                    'french_elision' => [
                        'type' => 'elision',
                        'articles_case' => true,
                        'articles' => ['l', 'm', 't', 'qu', 'n', 's', 'j', 'd', 'c', 'jusqu', 'quoiqu', 'lorsqu', 'puisqu']
                    ],
                    'french_stop' => [
                        'type' => 'stop',
                        'stopwords' => '_french_'
                    ],
                    'french_stemmer' => [
                        'type' => 'stemmer',
                        'language' => 'light_french'
                    ]
                ]
            ]
        ],
        'mappings' => [
            'properties' => [
                'manuscrit_id' => [
                    'type' => 'keyword'
                ],
                'objectid' => [
                    'type' => 'keyword'
                ],
                'titre' => [
                    'type' => 'text',
                    'analyzer' => 'french_analyzer',
                    'fields' => [
                        'keyword' => [
                            'type' => 'keyword'
                        ]
                    ]
                ],
                'auteur' => [
                    'type' => 'text',
                    'analyzer' => 'french_analyzer',
                    'fields' => [
                        'keyword' => [
                            'type' => 'keyword'
                        ]
                    ]
                ],
                'edition' => [
                    'type' => 'text',
                    'analyzer' => 'french_analyzer'
                ],
                'langue' => [
                    'type' => 'keyword'
                ],
                'cote' => [
                    'type' => 'keyword'
                ],
                'siecle' => [
                    'type' => 'keyword'
                ]
            ]
        ]
    ]
];

try {
    $elastic->indices()->create($params);
    echo "✅ Index créé avec succès.\n\n";
} catch (Exception $e) {
    die("❌ Erreur lors de la création de l'index : " . $e->getMessage() . "\n");
}

// Récupérer tous les manuscrits depuis MongoDB
$collection = $manager->selectCollection('manuscrits');
$manuscrits = $collection->find();

$count = 0;
$errors = 0;

echo "\uD83D\uDCDC Début de l'indexation des manuscrits...\n";
echo str_repeat("-", 70) . "\n";

foreach ($manuscrits as $manuscrit) {
    $manuscritId = (string)$manuscrit['_id'];
    $titre = $manuscrit['titre'] ?? 'Sans titre';
    $auteur = $manuscrit['auteur'] ?? 'Auteur inconnu';

    $params = [
        'index' => $indexName,
        'id' => $manuscritId,
        'body' => [
            'manuscrit_id' => $manuscritId,
            'objectid' => $manuscrit['objectid'] ?? '',
            'titre' => $titre,
            'auteur' => $auteur,
            'edition' => $manuscrit['edition'] ?? '',
            'langue' => $manuscrit['langue'] ?? '',
            'cote' => $manuscrit['cote'] ?? '',
            'siecle' => $manuscrit['siecle'] ?? ''
        ]
    ];

    try {
        $elastic->index($params);
        $count++;

        // Afficher les détails du manuscrit indexé
        $info = "{$titre} (par {$auteur})";
        if (!empty($manuscrit['siecle'])) {
            $info .= " - {$manuscrit['siecle']}";
        }
        echo "✓ Manuscrit indexé : {$info}\n";

    } catch (Exception $e) {
        $errors++;
        echo "✗ Erreur lors de l'indexation de '{$titre}' : " . $e->getMessage() . "\n";
    }
}

echo str_repeat("-", 70) . "\n";
echo "\n\uD83D\uDCCA RÉSUMÉ DE L'INDEXATION\n";
echo str_repeat("=", 70) . "\n";
echo "✅ Manuscrits indexés avec succès : {$count}\n";
if ($errors > 0) {
    echo "❌ Erreurs rencontrées : {$errors}\n";
}
echo str_repeat("=", 70) . "\n";

// Attendre que l'indexation soit complète
sleep(1);

// Vérifier l'état de l'index
try {
    $stats = $elastic->indices()->stats(['index' => $indexName]);
    $docCount = $stats['indices'][$indexName]['total']['docs']['count'] ?? 0;
    echo "\n\uD83D\uDCC8 Nombre de documents dans l'index : {$docCount}\n";
} catch (Exception $e) {
    echo "⚠️  Impossible de vérifier les statistiques de l'index.\n";
}

echo "\n✅ Indexation terminée !\n";
echo "\n\uD83D\uDCA1 Vous pouvez maintenant rechercher par :\n";
echo "   - Titre du manuscrit\n";
echo "   - Nom de l'auteur\n";
echo "   - Édition\n";
echo "   - Avec tolérance aux fautes de frappe\n";
echo "   - Avec gestion du pluriel/singulier\n";